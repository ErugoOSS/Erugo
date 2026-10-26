#!/usr/bin/env python3
"""Exercise a disposable container, never an existing deployment. Python stdlib only."""
import argparse
import base64
import datetime
import hashlib
import io
import json
from pathlib import Path
import re
import secrets
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile

ROOT = Path(__file__).resolve().parents[2]
PROBE = '''<?php
header('Content-Type: application/json');
echo json_encode(['version' => PHP_VERSION, 'version_id' => PHP_VERSION_ID,
    'sapi' => PHP_SAPI, 'progress' => ini_get('session.upload_progress.enabled'),
    'upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'),
    'memory' => ini_get('memory_limit'), 'modules' => get_loaded_extensions()]);
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('image')
    parser.add_argument('--engine', default='docker', help='Docker, Podman, or an executable wrapper')
    parser.add_argument('--runtime-only', action='store_true', help='Test dev PHP/Caddy without the mounted app or Vite')
    args = parser.parse_args()
    name = 'erugo-smoke-' + uuid.uuid4().hex[:12]
    volume = name + '-storage'
    probe_path = '/php-smoke-' + uuid.uuid4().hex + '.php'
    base = None

    def engine(*command, input=None, check=True):
        return subprocess.run([args.engine, *command], input=input, text=True,
                              capture_output=True, check=check, timeout=120)

    def report(check, **values):
        print(json.dumps({'check': check, **values}), flush=True)

    def request(method, path, body=None, headers=None, expected=(200,)):
        hdr = {'Accept': 'application/json', **(headers or {})}
        if isinstance(body, dict):
            body = json.dumps(body).encode()
            hdr['Content-Type'] = 'application/json'
        req = urllib.request.Request(base + path, data=body, headers=hdr, method=method)
        try:
            response = urllib.request.urlopen(req, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            data = response.read()
            assert response.status in expected, f'{method}: HTTP {response.status}, expected {expected}'
            return response.headers, data

    def wait_ready():
        for _ in range(90):
            try:
                return json.loads(request('GET', probe_path)[1])
            except (OSError, ValueError, AssertionError):
                time.sleep(1)
        raise RuntimeError('HTTP/FPM readiness timed out')

    def verify_runtime(data, sapi):
        assert 80336 <= data['version_id'] < 80400, data['version']
        assert data['sapi'] == sapi, data['sapi']
        assert data['progress'] in ('', '0'), 'session upload progress must be disabled'
        assert data['upload'] == data['post'] == data['memory'] == '4G'
        assert 'session' in data['modules']
        report(sapi, **data)

    try:
        engine('volume', 'create', volume)
        command = ['run', '-d', '--name', name, '--cpus=2', '--memory=2g',
                   '-p', '127.0.0.1::80', '-v', volume + ':/var/www/html/storage',
                   '-e', 'APP_ENV=testing', '-e', 'APP_DEBUG=false', '-e', 'MAIL_MAILER=log']
        if args.runtime_only:
            command += ['--entrypoint', 'sh', args.image, '-c',
                        'php-fpm83 && exec caddy run --config /etc/caddy/Caddyfile']
        else:
            command += [args.image]
        engine(*command)
        listener = engine('port', name, '80/tcp').stdout.strip()
        assert re.fullmatch(r'127\.0\.0\.1:\d+', listener), 'Expected loopback-only listener'
        base = 'http://' + listener
        engine('exec', '-i', name, 'sh', '-c', 'cat > /var/www/html/public' + probe_path, input=PROBE)
        verify_runtime(wait_ready(), 'fpm-fcgi')
        cli = engine('exec', '-i', name, 'php83', input=PROBE)
        verify_runtime(json.loads(cli.stdout), 'cli')
        fpm = engine('exec', name, 'php-fpm83', '-v').stdout.splitlines()[0]
        syntax = engine('exec', name, 'php-fpm83', '-t')
        report('fpm-version-and-config', version=fpm, config_exit=syntax.returncode)
        variant = 'dev' if args.runtime_only else 'alpine'
        dockerfile = (ROOT / 'docker' / variant / 'Dockerfile').read_text()
        guard_match = re.search(r"php83 -r '(exit\(PHP_VERSION_ID[^']+)'", dockerfile)
        assert guard_match, 'Dockerfile must reject an older PHP runtime'
        guard = guard_match.group(1)
        for version, expected in ((80335, 1), (80336, 0), (80400, 1)):
            result = engine('exec', name, 'php83', '-r', guard.replace('PHP_VERSION_ID', str(version)), check=False)
            assert result.returncode == expected, f'Build guard accepted/rejected wrong version {version}'
        report('build-guard-boundaries', below_floor_rejected=True, floor_accepted=True, other_branch_rejected=True)
        if args.runtime_only:
            return

        credentials = {'email': 'smoke@example.invalid', 'name': 'Disposable smoke test',
                       'password': secrets.token_urlsafe(36)}
        _, data = request('POST', '/api/setup', {**credentials, 'password_confirmation': credentials['password']})
        assert json.loads(data)['status'] == 'success'
        _, page = request('GET', '/', headers={'Accept': 'text/html'})
        assert b'/build/assets/' in page
        assert b'setup_needed&quot;:false' in page or b'"setup_needed":false' in page or b'"setup_needed": false' in page
        # Fetch a built JavaScript asset too, not just the HTML shell.
        asset_match = re.search(rb'(?:src|href)="(/build/assets/[^" ]+\.js)"', page)
        assert asset_match, 'Login page must reference a built JavaScript asset'
        asset = asset_match.group(1).decode()
        assert len(request('GET', asset)[1]) > 0
        request('GET', '/api/users/me', expected=(401,))
        report('login-ui-and-built-asset', passed=True, anonymous_profile_rejected=True)

        def login():
            _, data = request('POST', '/api/auth/login', credentials)
            auth = {'Authorization': 'Bearer ' + json.loads(data)['data']['access_token']}
            _, profile = request('GET', '/api/users/me', headers=auth)
            assert json.loads(profile)['data']['user']['email'] == credentials['email']
            return auth

        headers = login()
        report('password-login', passed=True)
        files = {'smoke.bin': secrets.token_bytes(262144), 'smoke.txt': b'Erugo synthetic container smoke test.\n'}
        ids = []
        for filename, payload in files.items():
            metadata = ','.join(k + ' ' + base64.b64encode(v.encode()).decode() for k, v in
                                [('filename', filename), ('filetype', 'application/octet-stream')])
            hdr, _ = request('POST', '/files/', b'', {**headers, 'Tus-Resumable': '1.0.0',
                             'Upload-Length': str(len(payload)), 'Upload-Metadata': metadata}, expected=(201,))
            location = urllib.parse.urlparse(hdr['Location'])
            assert location.path.startswith('/files/')
            upload_id = location.path.rsplit('/', 1)[-1]
            assert re.fullmatch('[0-9a-f]{32}', upload_id)
            offset = 0
            while offset < len(payload):
                chunk = payload[offset:offset + 65536]
                hdr, _ = request('PATCH', location.path, chunk, {**headers, 'Tus-Resumable': '1.0.0',
                                 'Upload-Offset': str(offset), 'Content-Type': 'application/offset+octet-stream'}, expected=(204,))
                offset += len(chunk)
                assert int(hdr['Upload-Offset']) == offset
                # Each request uses a new connection; HEAD supplies the resume offset.
                hdr, _ = request('HEAD', location.path, headers={**headers, 'Tus-Resumable': '1.0.0'}, expected=(200, 204))
                assert int(hdr['Upload-Offset']) == offset
                offset = int(hdr['Upload-Offset'])
            ids.append(upload_id)
        report('authenticated-tus-resume', files=len(files), bytes=sum(map(len, files.values())))
        expiry = (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(hours=1)).isoformat()
        _, data = request('POST', '/api/uploads/create-share-from-uploads', {
            'upload_id': str(uuid.uuid4()), 'name': 'Disposable smoke share', 'description': 'Synthetic data only',
            'uploadIds': ids, 'expiry_date': expiry, 'filePaths': dict(zip(ids, files)), 'recipients': []}, headers)
        share_id = json.loads(data)['data']['share']['long_id']
        for _ in range(60):
            _, data = request('GET', '/api/shares', headers=headers)
            share = next(s for s in json.loads(data)['data']['shares'] if s['long_id'] == share_id)
            if share['status'] == 'ready':
                break
            time.sleep(1)
        else:
            raise RuntimeError('Queue did not finish the share ZIP')

        def verify_downloads():
            for filename, original in files.items():
                assert request('GET', '/api/shares/' + share_id + '/download/file/' + filename)[1] == original
            archive = request('GET', '/api/shares/' + share_id + '/download')[1]
            with zipfile.ZipFile(io.BytesIO(archive)) as z:
                assert sorted(z.namelist()) == sorted(files)
                for filename, original in files.items():
                    assert z.read(filename) == original
            return hashlib.sha256(archive).hexdigest()

        before = verify_downloads()
        report('queue-and-anonymous-file-zip-bytes', passed=True)
        engine('restart', '--time', '20', name)
        verify_runtime(wait_ready(), 'fpm-fcgi')
        headers = login()
        assert verify_downloads() == before
        request('POST', '/api/auth/logout', {}, headers)
        report('restart-persistence-and-password-login', passed=True, archive_sha256_unchanged=True)
    finally:
        engine('rm', '-f', name, check=False)
        engine('volume', 'rm', volume, check=False)
        assert name not in engine('ps', '-a', '--format', '{{.Names}}').stdout.splitlines()
        assert volume not in engine('volume', 'ls', '--format', '{{.Name}}').stdout.splitlines()
        if base:
            port = urllib.parse.urlparse(base).port
            with socket.socket() as sock:
                sock.settimeout(2)
                assert sock.connect_ex(('127.0.0.1', port)) != 0, 'Disposable listener remains open'
        report('cleanup', container_removed=True, volume_removed=True, listener_closed=True)


if __name__ == '__main__':
    main()
