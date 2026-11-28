#!/usr/bin/env python3
"""Check that an Erugo storage volume survives a switch to a new image.

Usage:
    python3 -I tests/container/upgrade_paths.py --to <candidate image> [--from <image> ...]

For each --from image (default: the --to image, a no-op upgrade) this starts a
throwaway container on a fresh volume, runs first-run setup, seeds users and
shares through the HTTP API, stops it, starts the --to image on the same volume
and runs a list of checks. Each check is PASS, FAIL or SKIP. The script exits 1
if any check fails.

Everything it creates is named erugo-upg-* and is removed at the end, even on
failure. Only the standard library is used. Docker must be on PATH.

Passwords and tokens are random, held in memory and never printed. Anything
that needs PHP inside the container (setting a known password, reading the
migrations table) is sent to `php` on stdin rather than on the command line,
so secrets don't show up in `ps` or `docker events`. Mail goes to a small SMTP
sink started inside each container, which is how the reverse-share invite
token is captured.

A reason ending in "[target lacks ...]" or "[target cannot ...]" means the --to
image is missing something the --from image's data relies on. It's a FAIL when
data is mishandled (a pending_deletion share still downloads) and a SKIP when
nothing breaks (lowercase login on an image without email normalisation).
Against 0.2.16 these are expected; against 0.3.0 they should be gone.
"""
import argparse
import base64
import datetime
import hashlib
import io
import json
import quopri
import re
import signal
import secrets
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile

RZA_MIGRATIONS = (
    '2026_07_11_200000_add_pending_deletion_to_shares_table',
    '2026_07_12_000000_add_storage_id_to_files_table',
    '2026_07_28_000001_normalize_user_emails_to_lowercase',
)
LOWERCASE_MIGRATION = RZA_MIGRATIONS[2]
PENDING_MIGRATION = RZA_MIGRATIONS[0]

ROUTE_CLONE = ('POST', 'api/shares/{id}/clone')
ROUTE_ADD_FILES = ('POST', 'api/shares/{id}/add-files')
ROUTE_REQUEST_DELETION = ('POST', 'api/shares/{id}/request-deletion')
ROUTE_DELETE_NOW = ('POST', 'api/shares/{id}/delete-immediately')

PREFIX = 'erugo-upg-'

# Erugo always sends mail over SMTP using the smtp_* settings, so MAIL_MAILER=log does nothing.
# This tiny sink runs inside the container (supervisord means python3 is always there) and
# appends every message to MAIL_FILE.
MAIL_FILE = '/tmp/erugo-upg-mail.log'
MAIL_PORT = 2525
SMTP_SINK = f'''
import socketserver
class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        def say(text):
            self.wfile.write(text.encode() + b"\\r\\n")
            self.wfile.flush()
        say("220 erugo-upg ESMTP")
        while True:
            line = self.rfile.readline()
            if not line:
                return
            cmd = line.decode(errors="replace").strip().upper()
            if cmd.startswith("EHLO"):
                say("250-erugo-upg")
                say("250 8BITMIME")
            elif cmd.startswith("DATA"):
                say("354 go ahead")
                body = []
                while True:
                    part = self.rfile.readline()
                    if not part or part in (b".\\r\\n", b".\\n"):
                        break
                    body.append(part)
                with open("{MAIL_FILE}", "ab") as out:
                    out.write(b"".join(body) + b"\\n--erugo-upg--\\n")
                say("250 queued")
            elif cmd.startswith("QUIT"):
                say("221 bye")
                return
            else:
                say("250 ok")
socketserver.ThreadingTCPServer.allow_reuse_address = True
socketserver.ThreadingTCPServer(("127.0.0.1", {MAIL_PORT}), Handler).serve_forever()
'''
SMTP_SETTINGS = [
    {'key': 'smtp_host', 'value': '127.0.0.1'},
    {'key': 'smtp_port', 'value': str(MAIL_PORT)},
    {'key': 'smtp_encryption', 'value': ''},
    {'key': 'smtp_username', 'value': ''},
    {'key': 'smtp_sender_address', 'value': 'erugo-upg@example.com'},
    {'key': 'smtp_sender_name', 'value': 'Erugo upgrade test'},
]
SECRETS = set()
JWT_RE = re.compile(r'eyJ[\w-]+\.[\w-]+\.[\w-]+')


def secret(value):
    SECRETS.add(value)
    return value


def scrub(text):
    text = str(text)
    for value in SECRETS:
        if value:
            text = text.replace(value, '***')
    return JWT_RE.sub('***', text)


def log(message):
    print(scrub(message), flush=True)


class HarnessError(Exception):
    pass


# ----------------------------------------------------------------------------
# Docker


def docker(*args, input=None, check=True, timeout=300):
    result = subprocess.run(['docker', *args], input=input, text=True,
                            capture_output=True, timeout=timeout)
    if check and result.returncode != 0:
        raise HarnessError(f'docker {args[0]} failed: {scrub(result.stderr.strip()[-400:])}')
    return result


class Container:
    """One running image on the shared volume."""

    def __init__(self, image, volume, created):
        self.image = image
        self.volume = volume
        self.name = PREFIX + uuid.uuid4().hex[:10]
        self.base = None
        self.php_bin = 'php'
        self.user = 'root'
        self.routes = set()
        created.append(self.name)

    def start(self):
        docker('run', '-d', '--name', self.name, '--cpus=2', '--memory=2g',
               '-p', '127.0.0.1::80', '-v', self.volume + ':/var/www/html/storage',
               '-e', 'MAIL_MAILER=log', self.image)
        # Docker Desktop hands out a new host port on every start, so always re-read it.
        listeners = docker('port', self.name, '80/tcp').stdout.split()
        listener = next((l for l in listeners if l.startswith('127.0.0.1:')), None)
        if not listener:
            raise HarnessError(f'no loopback listener for {self.name}: {listeners}')
        self.base = 'http://' + listener

    def wait_healthy(self, api, timeout=300):
        deadline = time.time() + timeout
        while time.time() < deadline:
            state = docker('inspect', '-f', '{{.State.Running}}', self.name, check=False).stdout.strip()
            if state != 'true':
                raise HarnessError(f'{self.image} exited during startup:\n' + self.logs()[-1500:])
            try:
                status, _, body = api.request('GET', '/api/health', retry_429=False)
                if status == 200 and json.loads(body).get('status') == 'success':
                    break
            except (OSError, ValueError):
                pass
            time.sleep(2)
        else:
            raise HarnessError(f'{self.image} not healthy after {timeout}s:\n' + self.logs()[-1500:])
        self.php_bin = docker('exec', self.name, 'sh', '-c',
                              'command -v php || command -v php84 || command -v php83').stdout.strip() or 'php'
        if docker('exec', self.name, 'id', '-u', 'erugo', check=False).returncode == 0:
            self.user = 'erugo'
        self.routes = self.read_routes()

    def start_mail_sink(self):
        self.exec('sh', '-c', 'cat > /tmp/erugo-upg-smtp.py', input=SMTP_SINK)
        docker('exec', '-d', '-u', self.user, self.name, 'python3', '/tmp/erugo-upg-smtp.py')

    def mail(self):
        raw = self.exec('sh', '-c', f'cat {MAIL_FILE} 2>/dev/null', check=False).stdout
        return quopri.decodestring(raw.encode(errors='replace')).decode(errors='replace')

    def logs(self):
        result = docker('logs', self.name, check=False)
        return result.stdout + result.stderr

    def exec(self, *command, input=None, check=True, timeout=300):
        return docker('exec', '-i', '-u', self.user, '-w', '/var/www/html', self.name, *command,
                      input=input, check=check, timeout=timeout)

    def php(self, body, data=None):
        """Run PHP inside the booted Laravel app and return its JSON result.

        The script, including any secrets in `data`, travels on stdin."""
        payload = base64.b64encode(json.dumps(data or {}).encode()).decode()
        script = (
            "<?php\n"
            "use Illuminate\\Support\\Facades\\DB;\n"
            "use Illuminate\\Support\\Facades\\Hash;\n"
            "use Illuminate\\Support\\Facades\\Schema;\n"
            "error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);\n"
            "chdir('/var/www/html');\n"
            "require 'vendor/autoload.php';\n"
            "$app = require 'bootstrap/app.php';\n"
            "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n"
            f"$IN = json_decode(base64_decode('{payload}'), true);\n"
            "$OUT = (function ($IN) {\n" + body + "\n})($IN);\n"
            "echo \"\\n@@UPG@@\" . json_encode($OUT);\n"
        )
        result = self.exec(self.php_bin, input=script, check=False)
        marker = result.stdout.rfind('@@UPG@@')
        if marker < 0:
            raise HarnessError('PHP helper failed: ' + scrub((result.stdout + result.stderr).strip()[-600:]))
        return json.loads(result.stdout[marker + len('@@UPG@@'):])

    def artisan(self, *args, check=True, timeout=300):
        return self.exec(self.php_bin, 'artisan', *args, check=check, timeout=timeout)

    def read_routes(self):
        result = self.artisan('route:list', '--json', check=False)
        try:
            data = json.loads(result.stdout[result.stdout.find('['):])
        except ValueError:
            return set()
        routes = set()
        for route in data:
            for method in route.get('method', '').split('|'):
                routes.add((method, route.get('uri', '')))
        return routes

    def has_route(self, route):
        return route in self.routes

    def migrations(self):
        rows = self.php("return DB::table('migrations')->orderBy('id')->get(['migration','batch']);")
        return [(r['migration'], r['batch']) for r in rows]

    def migration_files(self):
        out = self.exec('sh', '-c', 'ls database/migrations').stdout
        return {line[:-4] for line in out.split() if line.endswith('.php')}

    def wait_queue_empty(self, timeout=120):
        deadline = time.time() + timeout
        while time.time() < deadline:
            if self.php("return Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;") == 0:
                return True
            time.sleep(2)
        return False

    def stop_and_remove(self):
        docker('stop', '--time', '30', self.name, check=False, timeout=120)
        docker('rm', '-f', self.name, check=False)


# ----------------------------------------------------------------------------
# HTTP


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


OPENER = urllib.request.build_opener(NoRedirect)


class Api:
    def __init__(self, container):
        self.container = container

    def request(self, method, path, body=None, headers=None, retry_429=True, form=False):
        hdr = {'Accept': 'application/json', **(headers or {})}
        if isinstance(body, dict):
            if form:
                body = urllib.parse.urlencode(body).encode()
                hdr['Content-Type'] = 'application/x-www-form-urlencoded'
            else:
                body = json.dumps(body).encode()
                hdr['Content-Type'] = 'application/json'
        for _ in range(10):
            req = urllib.request.Request(self.container.base + path, data=body, headers=hdr, method=method)
            try:
                response = OPENER.open(req, timeout=120)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                data = response.read()
                status, headers_out = response.status, response.headers
            if status == 429 and retry_429:
                # Login is limited to 10 a minute per IP; wait it out.
                time.sleep(int(headers_out.get('Retry-After') or 10) + 1)
                continue
            return status, headers_out, data
        return status, headers_out, data

    def json(self, method, path, body=None, token=None, expected=(200,), **kwargs):
        headers = {'Authorization': 'Bearer ' + token} if token else {}
        status, _, data = self.request(method, path, body, headers, **kwargs)
        if status not in expected:
            raise HarnessError(f'{method} {path}: HTTP {status}: {scrub(data[:300])}')
        try:
            return json.loads(data)
        except ValueError:
            return {}

    def login(self, email, password):
        status, headers, data = self.request('POST', '/api/auth/login', {'email': email, 'password': password})
        if status != 200:
            return status, None, None
        token = secret(json.loads(data)['data']['access_token'])
        cookie = headers.get('Set-Cookie', '')
        match = re.search(r'refresh_token=([^;]+)', cookie)
        if match:
            secret(match.group(1))
        return status, token, match.group(1) if match else None

    def tus_upload(self, token, filename, payload):
        auth = {'Authorization': 'Bearer ' + token, 'Tus-Resumable': '1.0.0'}
        metadata = ','.join(k + ' ' + base64.b64encode(v.encode()).decode() for k, v in
                            [('filename', filename), ('filetype', 'application/octet-stream')])
        status, hdr, data = self.request('POST', '/files/', b'', {**auth, 'Upload-Length': str(len(payload)),
                                                                   'Upload-Metadata': metadata})
        if status != 201:
            raise HarnessError(f'tus create for {filename}: HTTP {status}: {scrub(data[:200])}')
        path = urllib.parse.urlparse(hdr['Location']).path
        offset = 0
        while offset < len(payload):
            chunk = payload[offset:offset + 65536]
            status, hdr, data = self.request('PATCH', path, chunk, {
                **auth, 'Upload-Offset': str(offset), 'Content-Type': 'application/offset+octet-stream'})
            if status != 204:
                raise HarnessError(f'tus patch for {filename}: HTTP {status}: {scrub(data[:200])}')
            offset = int(hdr['Upload-Offset'])
        return path.rsplit('/', 1)[-1]

    def create_share(self, token, name, files, password=None):
        ids = [self.tus_upload(token, filename, payload) for filename, payload in files.items()]
        expiry = (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(days=3)).isoformat()
        body = {'upload_id': str(uuid.uuid4()), 'name': name, 'description': 'Upgrade test data',
                'uploadIds': ids, 'expiry_date': expiry, 'filePaths': dict(zip(ids, files)), 'recipients': []}
        if password:
            body.update(password=password, password_confirm=password)
        return self.json('POST', '/api/uploads/create-share-from-uploads', body, token)


# ----------------------------------------------------------------------------
# Seeding


def make_files(label, count):
    files = {}
    for i in range(count):
        if i % 2:
            files[f'{label}-{i}.txt'] = f'Erugo upgrade test, {label} file {i}, {uuid.uuid4()}\n'.encode() * 50
        else:
            files[f'{label}-{i}.bin'] = secrets.token_bytes(65536 + 4099 * i)
    return files


def sha(data):
    return hashlib.sha256(data).hexdigest()


def wait_share_status(c, share_id, wanted, timeout=180):
    deadline = time.time() + timeout
    status = None
    while time.time() < deadline:
        status = c.php("return DB::table('shares')->where('id', $IN['id'])->value('status');", {'id': share_id})
        if status in wanted:
            return status
        time.sleep(2)
    return status


def set_password(c, user_id, password):
    c.php("""
        $u = App\\Models\\User::find($IN['id']);
        $u->password = Hash::make($IN['pw']);
        if (Schema::hasColumn('users', 'must_change_password')) { $u->must_change_password = false; }
        $u->active = true;
        $u->save();
        return true;
    """, {'id': user_id, 'pw': password})


def create_user_php(c, name, email, password):
    return c.php("""
        $u = new App\\Models\\User;
        $u->forceFill(['name' => $IN['name'], 'email' => $IN['email'], 'password' => Hash::make($IN['pw']),
                       'admin' => false, 'active' => true]);
        if (Schema::hasColumn('users', 'must_change_password')) { $u->must_change_password = false; }
        $u->save();
        return $u->id;
    """, {'name': name, 'email': email, 'pw': password})


def seed(c, api):
    """Create the data the checks will look for. Returns a plain dict."""
    s = {'users': [], 'shares': {}, 'notes': [], 'features': {}}
    applied = {m for m, _ in c.migrations()}
    s['features'] = {
        'clone': c.has_route(ROUTE_CLONE),
        'add_files': c.has_route(ROUTE_ADD_FILES),
        'request_deletion': c.has_route(ROUTE_REQUEST_DELETION) and PENDING_MIGRATION in applied,
        'lowercase_migration': LOWERCASE_MIGRATION in applied,
    }

    admin = {'name': 'Upgrade Admin', 'email': 'Upgrade.Admin@Example.com',
             'password': secret(secrets.token_urlsafe(24) + 'aA1!'), 'role': 'admin'}
    body = {'name': admin['name'], 'email': admin['email'], 'password': admin['password'],
            'password_confirmation': admin['password']}
    if api.json('POST', '/api/setup', body).get('status') != 'success':
        raise HarnessError('setup did not succeed')
    _, token, _ = api.login(admin['email'], admin['password'])
    if not token:
        # Images with email normalisation may have stored it in lowercase.
        _, token, _ = api.login(admin['email'].lower(), admin['password'])
    if not token:
        raise HarnessError('cannot log in as the setup admin')
    admin['id'] = api.json('GET', '/api/users/me', token=token)['data']['user']['id']
    s['users'].append(admin)

    # Settings: point mail at the sink, store an SMTP password a normal user must not read,
    # and turn reverse shares on.
    smtp = secret('smtp-' + secrets.token_urlsafe(16))
    api.json('PUT', '/api/settings', {'settings': SMTP_SETTINGS + [
        {'key': 'smtp_password', 'value': smtp},
        {'key': 'allow_reverse_shares', 'value': 'true'},
    ]}, token)

    def add_user(name, email, role, allow_php=True):
        password = secret(secrets.token_urlsafe(24) + 'aA1!')
        status, _, data = api.request('POST', '/api/users', {'email': email, 'name': name, 'admin': False},
                                      {'Authorization': 'Bearer ' + token})
        how = 'api'
        if status == 200:
            user_id = json.loads(data)['data']['user']['id']
        elif allow_php:
            user_id = create_user_php(c, name, email, password)
            how = f'php (API said HTTP {status})'
        else:
            raise HarnessError(f'could not create {email}: HTTP {status}')
        set_password(c, user_id, password)
        user = {'name': name, 'email': email, 'password': password, 'role': role, 'id': user_id, 'how': how}
        s['users'].append(user)
        return user

    add_user('Mixed Case', 'Mixed.Case.User@Example.com', 'user')
    plain = add_user('Plain User', 'plain.user@example.com', 'user')
    if not s['features']['lowercase_migration']:
        # Two accounts differing only by case: the normalise migration has to sort these out.
        add_user('Dup Older', 'Dup.Person@Example.com', 'dup-older')
        add_user('Dup Newer', 'dup.person@example.com', 'dup-newer')
    s['nonadmin'] = plain['id']

    def share(label, count, password=None):
        files = make_files(label, count)
        data = api.create_share(token, f'Upgrade {label}', files, password)['data']['share']
        s['shares'][label] = {'id': data['id'], 'long_id': data['long_id'], 'files': files,
                              'sha256': {n: sha(b) for n, b in files.items()}, 'password': password}
        return s['shares'][label]

    share('normal', 3)
    share('password', 1, password=secret(secrets.token_urlsafe(12)))

    # RZA's extend endpoint lets an admin remove the expiry (expires_at = NULL). Older code
    # ignores the flag and just adds 7 days, which is harmless, so try it everywhere.
    forever = share('no-expiry', 2)
    api.request('POST', f"/api/shares/{forever['id']}/extend", {'unlimited': True},
                {'Authorization': 'Bearer ' + token})
    expiry = c.php("return DB::table('shares')->where('id', $IN['id'])->value('expires_at');", {'id': forever['id']})
    s['features']['no_expiry'] = expiry is None
    if expiry is not None:
        s['shares']['extended'] = s['shares'].pop('no-expiry')

    if s['features']['clone']:
        src = share('clone-source', 2)
        wait_share_status(c, src['id'], {'ready'})
        data = api.json('POST', f"/api/shares/{src['id']}/clone", {'name': 'Upgrade clone'}, token)['data']['share']
        s['shares']['clone'] = {'id': data['id'], 'long_id': data['long_id'], 'files': src['files'],
                                'sha256': src['sha256'], 'password': None}
        rows = c.php("return DB::table('files')->whereIn('share_id', $IN['ids'])->get(['share_id','name','storage_id']);",
                     {'ids': [src['id'], data['id']]})
        ids = {}
        for row in rows:
            ids.setdefault(row['name'], set()).add((row['share_id'], row['storage_id']))
        shared = all(len({sid for _, sid in v}) == 1 and None not in {sid for _, sid in v} for v in ids.values())
        s['notes'].append('clone files share storage_id with the source' if shared
                          else f'clone storage_ids do not match the source: {ids}')

    if s['features']['add_files']:
        target = share('add-files', 2)
        wait_share_status(c, target['id'], {'ready'})
        extra = make_files('added', 1)
        upload = api.tus_upload(token, *next(iter(extra.items())))
        api.json('POST', f"/api/shares/{target['id']}/add-files",
                 {'uploadIds': [upload], 'filePaths': {upload: next(iter(extra))}}, token)
        target['files'].update(extra)
        target['sha256'].update({n: sha(b) for n, b in extra.items()})

    if s['features']['request_deletion']:
        # Single file on purpose: older download code skips the status check for single-file shares.
        pending = share('pending-deletion', 1)
        wait_share_status(c, pending['id'], {'ready'})
        api.json('POST', f"/api/shares/{pending['id']}/request-deletion", {}, token)
        pending['path'] = c.php("return DB::table('shares')->where('id', $IN['id'])->value('path');",
                                {'id': pending['id']})

    # Reverse-share invite for someone without an account. The guest token only travels by email,
    # so dig it out of what the mail sink caught.
    invite = api.json('POST', '/api/reverse-shares/invite', {
        'recipient_name': 'Invited Guest', 'recipient_email': 'Invited.Guest@Example.com',
        'message': 'Upgrade test invite'}, token)['data']['invite']
    s['invite'] = {'id': invite['id'], 'token': None}
    c.wait_queue_empty()
    deadline = time.time() + 60
    while time.time() < deadline and not s['invite']['token']:
        match = re.search(r'invite_token=([A-Za-z0-9+/=]{40,})', c.mail())
        if match:
            s['invite']['token'] = secret(match.group(1))
        else:
            time.sleep(2)
    if not s['invite']['token']:
        s['notes'].append('could not find the invite token in the captured mail')

    for label, info in s['shares'].items():
        if label == 'pending-deletion':
            continue
        status = wait_share_status(c, info['id'], {'ready', 'failed'})
        if status != 'ready':
            raise HarnessError(f'share {label} ended up {status} before the upgrade')
    if s['features']['request_deletion']:
        status = c.php("return DB::table('shares')->where('id', $IN['id'])->value('status');",
                       {'id': s['shares']['pending-deletion']['id']})
        if status != 'pending_deletion':
            raise HarnessError(f'pending-deletion share is {status}, not pending_deletion')

    c.wait_queue_empty()
    s['migrations'] = c.migrations()
    s['failed_jobs'] = c.php("return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;")
    return s


# ----------------------------------------------------------------------------
# Checks


class Results:
    def __init__(self):
        self.rows = []

    def add(self, check, result, reason=''):
        self.rows.append((check, result, scrub(reason)))
        log(f'  {result:<4}  {check}: {scrub(reason)}')

    def run(self, check, fn):
        try:
            outcome = fn()
        except Exception as error:  # a check that blows up is a failure, not a crash
            self.add(check, 'FAIL', f'{type(error).__name__}: {error}')
            return
        self.add(check, *outcome)


def download(api, share, password=None):
    path = f"/api/shares/{share['long_id']}/download"
    if password is not None:
        return api.request('POST', path, {'password': password}, form=True)
    return api.request('GET', path)


def matches(body, files):
    """True when a download body holds exactly these files."""
    if len(files) == 1:
        return body == next(iter(files.values()))
    try:
        with zipfile.ZipFile(io.BytesIO(body)) as z:
            return sorted(z.namelist()) == sorted(files) and all(z.read(n) == b for n, b in files.items())
    except zipfile.BadZipFile:
        return False


def describe(status, headers, body):
    if status in (301, 302, 303, 307, 308):
        return f'HTTP {status} -> {headers.get("Location", "?")}'
    kind = headers.get('Content-Type', '?').split(';')[0]
    return f'HTTP {status} {kind} {len(body)} bytes'


def run_checks(c, api, s):
    r = Results()
    target_files = c.migration_files()
    has_lowercase = LOWERCASE_MIGRATION in target_files
    has_pending = PENDING_MIGRATION in target_files
    lacks_lower = '' if has_lowercase else ' [target lacks the email normalisation migration]'
    lacks_pending = '' if has_pending else ' [target lacks pending_deletion support]'

    def startup_logs():
        text = c.logs()
        end = text.find('Starting services')
        startup = text[:end if end >= 0 else len(text)]
        bad = [l.strip() for l in startup.splitlines()
               if re.search(r'SQLSTATE|\bFAIL\b|^\s*ERROR\b|Exception|could not find driver', l)]
        if bad:
            return 'FAIL', bad[0][:160]
        if 'Running migrations' not in startup:
            return 'FAIL', 'no "Running migrations" line in the startup log'
        return 'PASS', 'migrate and seed ran clean'
    r.run('startup log has no migration errors', startup_logs)

    def migrate_status():
        out = c.artisan('migrate:status', check=False).stdout
        pending = [l.split()[0] for l in out.splitlines() if 'Pending' in l]
        if pending:
            return 'FAIL', 'pending: ' + ', '.join(pending)
        orphans = sorted({m for m, _ in c.migrations()} - target_files)
        note = f'; {len(orphans)} ran but have no file in target: {", ".join(orphans)}' if orphans else ''
        return 'PASS', 'nothing pending' + note
    r.run('migrate:status shows everything ran', migrate_status)

    def not_rerun():
        before = dict(s['migrations'])
        after = c.migrations()
        counts = {}
        for m, _ in after:
            counts[m] = counts.get(m, 0) + 1
        problems = [f'{m} recorded {n} times' for m, n in counts.items() if n > 1]
        after_d = dict(after)
        for m, batch in before.items():
            if m in after_d and after_d[m] != batch:
                problems.append(f'{m} batch {batch} -> {after_d[m]}')
            if m not in after_d:
                problems.append(f'{m} vanished from the migrations table')
        had = [m for m in RZA_MIGRATIONS if m in before]
        if problems:
            return 'FAIL', '; '.join(problems)
        if not had:
            return 'SKIP', 'source ran none of the three RZA migrations; no earlier batch was changed'
        return 'PASS', f'{len(had)} RZA migration(s) kept their batch: ' + ', '.join(f'{m[:17]}=b{before[m]}' for m in had)
    r.run('RZA migrations not re-run', not_rerun)

    admin = next(u for u in s['users'] if u['role'] == 'admin')
    current_email = lambda uid: c.php("return DB::table('users')->where('id', $IN['id'])->value('email');", {'id': uid})
    _, admin_token, _ = api.login(current_email(admin['id']), admin['password'])

    for user in s['users']:
        def login_check(user=user):
            lower = user['email'].lower()
            if user['role'] == 'dup-newer':
                row = c.php("return DB::table('users')->where('id', $IN['id'])->first(['email','active']);",
                            {'id': user['id']})
                if has_lowercase:
                    if row['email'].endswith('__dup_' + str(user['id'])) and not row['active']:
                        return 'PASS', 'quarantined as expected (email suffixed, account disabled)'
                    return 'FAIL', f"expected quarantine, got email={row['email']} active={row['active']}"
                status, _, _ = api.login(lower, user['password'])
                if status == 200:
                    return 'PASS', 'still a separate lowercase account (no normalisation in target)'
                return 'FAIL', f'HTTP {status}' + lacks_lower
            stored = current_email(user['id'])
            fallback = stored != lower and not has_lowercase
            # Without the normalise migration a mixed-case address still has to be typed as stored.
            # That is how 0.2.x behaves, so it's a SKIP for the lowercase part, not an upgrade failure.
            status, token, _ = api.login(stored if fallback else lower, user['password'])
            if status != 200:
                return 'FAIL', f'login with {stored if fallback else lower} gave HTTP {status}'
            me = api.json('GET', '/api/users/me', token=token)['data']['user']
            if me['id'] != user['id']:
                return 'FAIL', f"logged in as user {me['id']}, expected {user['id']}"
            if fallback:
                return 'SKIP', f'login as stored ({stored}) works; lowercase login needs the email normalisation migration' + lacks_lower
            return 'PASS', f"stored email now {me['email']}"
        r.run(f"login with lowercase {user['email'].lower()} ({user['role']})", login_check)

    def share_lists():
        problems = []
        for path in ('/api/shares', '/api/shares/all'):
            status, _, body = api.request('GET', path, headers={'Authorization': 'Bearer ' + admin_token})
            if status != 200:
                problems.append(f'{path} HTTP {status}')
        for label, info in s['shares'].items():
            if label == 'pending-deletion':
                continue
            status, _, _ = api.request('GET', f"/api/shares/{info['long_id']}")
            if status != 200:
                problems.append(f'{label} share info HTTP {status}')
        if problems:
            note = ' [target cannot handle shares with no expiry]' if 'no-expiry' in s['shares'] else ''
            return 'FAIL', '; '.join(problems) + note
        return 'PASS', 'my shares, all shares and each share page load'
    r.run('share lists and share pages load', share_lists)

    for label, info in s['shares'].items():
        if label in ('pending-deletion', 'password'):
            continue

        def download_check(info=info, label=label):
            note = ' [target cannot handle shares with no expiry]' if label == 'no-expiry' else ''
            status, headers, body = download(api, info)
            if status != 200 or not matches(body, info['files']):
                return 'FAIL', describe(status, headers, body) + note
            if len(info['files']) > 1:
                for name, data in info['files'].items():
                    st, _, b = api.request('GET', f"/api/shares/{info['long_id']}/download/file/{urllib.parse.quote(name)}")
                    if st != 200 or b != data:
                        return 'FAIL', f'single-file download of {name}: HTTP {st}, {len(b)} bytes' + note
            return 'PASS', f"{len(info['files'])} file(s), sha256 match"
        r.run(f'share "{label}" downloads with matching bytes', download_check)

    def password_check():
        info = s['shares']['password']
        st, h, b = download(api, info)
        if st == 200 and matches(b, info['files']):
            return 'FAIL', 'downloaded without a password'
        st2, h2, b2 = download(api, info, 'wrong-' + secrets.token_hex(4))
        if st2 == 200 and matches(b2, info['files']):
            return 'FAIL', 'downloaded with the wrong password'
        st3, h3, b3 = download(api, info, info['password'])
        if st3 != 200 or not matches(b3, info['files']):
            return 'FAIL', 'with password: ' + describe(st3, h3, b3)
        return 'PASS', f'no password: {describe(st, h, b)}; with password: bytes match'
    r.run('password share needs its password', password_check)

    def clone_check():
        if 'clone' not in s['shares']:
            return 'SKIP', 'source image has no share cloning'
        src, clone = s['shares']['clone-source'], s['shares']['clone']
        if c.has_route(ROUTE_REQUEST_DELETION):
            api.json('POST', f"/api/shares/{src['id']}/request-deletion", {}, admin_token)
            if c.has_route(ROUTE_DELETE_NOW):
                api.json('POST', f"/api/shares/{src['id']}/delete-immediately", {'confirmation': 'DELETE'}, admin_token)
                how = 'request-deletion + delete-immediately'
            else:
                api.json('POST', '/api/shares/prune-expired', {}, admin_token)
                how = 'request-deletion + prune-expired'
        else:
            api.json('POST', f"/api/shares/{src['id']}/expire", {}, admin_token)
            # expire sets expires_at to now and prune wants expires_at < now, both to the second.
            time.sleep(2)
            api.json('POST', '/api/shares/prune-expired', {}, admin_token)
            how = 'expire + prune-expired'
        status = wait_share_status(c, src['id'], {'deleted'}, timeout=120)
        if status != 'deleted':
            return 'FAIL', f'original still {status} after {how}'
        st, h, b = download(api, clone)
        if st != 200 or not matches(b, clone['files']):
            return 'FAIL', f'original deleted via {how}; clone: {describe(st, h, b)}'
        for name, data in clone['files'].items():
            st, _, body = api.request('GET', f"/api/shares/{clone['long_id']}/download/file/{urllib.parse.quote(name)}")
            if st != 200 or body != data:
                return 'FAIL', f'clone file {name}: HTTP {st}'
        return 'PASS', f'original deleted via {how}; clone bytes match'
    r.run('clone survives deleting the original', clone_check)

    def pending_not_downloadable():
        if 'pending-deletion' not in s['shares']:
            return 'SKIP', 'source image has no pending_deletion'
        info = s['shares']['pending-deletion']
        st, h, b = download(api, info)
        if st == 200 and matches(b, info['files']):
            return 'FAIL', 'share download returned the file' + lacks_pending
        name = next(iter(info['files']))
        st2, h2, b2 = api.request('GET', f"/api/shares/{info['long_id']}/download/file/{urllib.parse.quote(name)}")
        if st2 == 200 and b2 == info['files'][name]:
            return 'FAIL', 'file download endpoint returned the file' + lacks_pending
        return 'PASS', f'{describe(st, h, b)}; file endpoint HTTP {st2}'
    r.run('pending_deletion share is not downloadable', pending_not_downloadable)

    def pending_cleaned():
        if 'pending-deletion' not in s['shares']:
            return 'SKIP', 'source image has no pending_deletion'
        info = s['shares']['pending-deletion']
        jobs = scheduled_cleanup_jobs(c)
        if not jobs:
            return 'FAIL', 'no scheduled cleanup job found in routes/console.php or app/Console'
        for job in jobs:
            code = f'dispatch_sync(new \\{job}());'
            out = c.artisan('tinker', '--execute=' + code, check=False)
            if out.returncode != 0:
                c.php(f'dispatch_sync(new \\{job}()); return true;')
        status = c.php("return DB::table('shares')->where('id', $IN['id'])->value('status');", {'id': info['id']})
        on_disk = c.exec('sh', '-c', f"test -e 'storage/app/shares/{info['path']}' && echo yes || echo no").stdout.strip()
        ran = ', '.join(j.rsplit('\\', 1)[-1] for j in jobs)
        if status == 'deleted' and on_disk == 'no':
            return 'PASS', f'ran {ran}; status deleted, files gone'
        return 'FAIL', f'ran {ran}; status still {status}, files on disk: {on_disk}' + lacks_pending
    r.run('cleanup job removes the pending_deletion share', pending_cleaned)

    def smtp_check():
        user = next(u for u in s['users'] if u['id'] == s['nonadmin'])
        _, token, _ = api.login(current_email(user['id']), user['password'])
        if not token:
            return 'FAIL', 'could not log in as the non-admin user'
        status, _, body = api.request('GET', '/api/settings/smtp_password', headers={'Authorization': 'Bearer ' + token})
        if status == 403:
            return 'PASS', 'HTTP 403'
        leaked = any(v.encode() in body for v in SECRETS if v.startswith('smtp-'))
        return 'FAIL', f'HTTP {status}' + (' and the value was in the body' if leaked else '')
    r.run('non-admin cannot read smtp_password', smtp_check)

    def reverse_check():
        token = s['invite']['token']
        if not token:
            return 'SKIP', 'no invite token was captured before the upgrade'
        if c.has_route(('POST', 'api/reverse-shares/accept')):
            status, _, body = api.request('POST', '/api/reverse-shares/accept', {'token': token})
        else:
            status, _, body = api.request('GET', '/api/reverse-shares/accept?' + urllib.parse.urlencode({'token': token}))
        if status != 200:
            return 'FAIL', f'accept gave HTTP {status}: {scrub(body[:120])}'
        guest = secret(json.loads(body)['data']['access_token'])
        files = make_files('reverse', 1)
        api.create_share(guest, 'Upgrade reverse upload', files)
        linked = c.php("return DB::table('shares')->where('invite_id', $IN['id'])->where('name', 'Upgrade reverse upload')->count();",
                       {'id': s['invite']['id']})
        if not linked:
            return 'FAIL', 'guest upload worked but the share is not linked to the invite'
        status, _, body = api.request('GET', '/api/shares', headers={'Authorization': 'Bearer ' + admin_token})
        if status != 200:
            # A broken share list is reported by its own row; the invite itself worked.
            return 'PASS', f'accepted, guest uploaded, share linked to the invite (inviter list HTTP {status}, see above)'
        if not any(sh.get('name') == 'Upgrade reverse upload' for sh in json.loads(body)['data']['shares']):
            return 'FAIL', 'share is linked to the invite but missing from the inviter list'
        return 'PASS', 'accepted, guest uploaded, inviter sees the share'
    r.run('reverse-share invite still works', reverse_check)

    def failed_jobs():
        c.wait_queue_empty(60)
        rows = c.php("""
            if (!Schema::hasTable('failed_jobs')) return [];
            return DB::table('failed_jobs')->orderBy('id')->get(['payload','exception'])->map(function ($j) {
                $p = json_decode($j->payload, true);
                return ($p['displayName'] ?? '?') . ': ' . strtok($j->exception, "\\n");
            });
        """)
        new = rows[s['failed_jobs']:]
        if new:
            return 'FAIL', f'{len(new)} new: ' + ' | '.join(x[:140] for x in new[:3])
        return 'PASS', 'none'
    r.run('no failed queue jobs after the upgrade', failed_jobs)

    return r


def scheduled_cleanup_jobs(c):
    """Find the scheduled job classes that clean shares, from the target image's own source."""
    text = c.exec('sh', '-c', 'cat routes/console.php app/Console/Kernel.php 2>/dev/null', check=False).stdout
    uses = dict((m.group(1).rsplit('\\', 1)[-1], m.group(1)) for m in re.finditer(r'^use\s+([\w\\]+);', text, re.M))
    jobs = []
    for m in re.finditer(r'(?:Schedule::|->)job\(\s*(?:new\s+)?\\?([\w\\]+?)(?:::class|\s*\()', text):
        name = m.group(1)
        full = uses.get(name, name if '\\' in name else 'App\\Jobs\\' + name)
        if re.search(r'clean', full, re.I) and full not in jobs:
            jobs.append(full)
    return jobs


# ----------------------------------------------------------------------------
# Driver


def run_path(source, target):
    volume = PREFIX + uuid.uuid4().hex[:10] + '-storage'
    created = []
    results = Results()
    try:
        docker('volume', 'create', volume)
        log(f'\n=== {source} -> {target}  (volume {volume})')
        old = Container(source, volume, created)
        old.start()
        api = Api(old)
        old.wait_healthy(api)
        old.start_mail_sink()
        log(f'  seeding on {source}')
        data = seed(old, api)
        log('  features: ' + ', '.join(f'{k}={v}' for k, v in data['features'].items()))
        for u in data['users']:
            log(f"  user {u['id']}: {u['email']} ({u['role']}{', ' + u['how'] if 'how' in u else ''})")
        for label, info in data['shares'].items():
            sums = ', '.join(f'{n}={h[:12]}' for n, h in info['sha256'].items())
            log(f"  share {label}: id {info['id']} long_id {info['long_id']} [{sums}]")
        for note in data['notes']:
            log('  note: ' + note)
        old.stop_and_remove()

        new = Container(target, volume, created)
        new.start()
        api.container = new
        new.wait_healthy(api)
        new.start_mail_sink()
        log(f'  checking on {target}')
        results = run_checks(new, api, data)
    except Exception as error:
        results.add('harness', 'FAIL', f'{type(error).__name__}: {error}')
    finally:
        for name in created:
            docker('rm', '-f', name, check=False)
        docker('volume', 'rm', '-f', volume, check=False)
        leftovers = [n for n in docker('ps', '-a', '--format', '{{.Names}}').stdout.split() if n in created]
        if leftovers or volume in docker('volume', 'ls', '--format', '{{.Name}}').stdout.split():
            results.add('cleanup', 'FAIL', f'left behind: {leftovers} {volume}')
    return results


def print_table(source, target, results):
    width = max([len(c) for c, _, _ in results.rows] + [5])
    print(f'\n{source} -> {target}')
    print(f'{"Check".ljust(width)}  Result  Reason')
    print(f'{"-" * width}  ------  ------')
    for check, result, reason in results.rows:
        print(f'{check.ljust(width)}  {result:<6}  {reason}')
    counts = {k: sum(1 for _, r, _ in results.rows if r == k) for k in ('PASS', 'FAIL', 'SKIP')}
    print(f"{counts['PASS']} pass, {counts['FAIL']} fail, {counts['SKIP']} skip")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--to', required=True, help='candidate image to upgrade to')
    parser.add_argument('--from', dest='sources', action='append', default=[],
                        help='image to start from; repeat for several (default: the --to image)')
    args = parser.parse_args()
    # Turn a kill into SystemExit so the cleanup in run_path still happens.
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(143))
    sources = args.sources or [args.to]
    for image in {args.to, *sources}:
        if docker('image', 'inspect', image, check=False).returncode != 0:
            sys.exit(f'image not found locally: {image}')

    all_results = [(s, run_path(s, args.to)) for s in sources]
    for source, results in all_results:
        print_table(source, args.to, results)
    sys.exit(1 if any(r == 'FAIL' for _, res in all_results for _, r, _ in res.rows) else 0)


if __name__ == '__main__':
    main()
