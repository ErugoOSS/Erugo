<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Models\User;
use App\Models\File;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use ZipArchive;

class DiagnosticsController extends Controller
{
    /** Keys whose values must be redacted from the settings dump. */
    private const SENSITIVE_SETTING_KEYS = [
        'smtp_password', 'smtp_user', 'smtp_host', 'mail_password',
        'oauth_client_secret', 'oauth_secret', 'api_key', 'secret',
    ];

    /**
     * Generate a diagnostic bundle, cache it under a one-time token,
     * and return the token + decryption key so the frontend can trigger
     * the download separately.
     */
    public function generate()
    {
        $user = Auth::user();
        if (!$user || !$user->admin) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Random ZIP password shown once to the admin
        $password = strtoupper(Str::random(6)) . '-' .
                    strtolower(Str::random(6)) . '-' .
                    strtoupper(Str::random(6));

        $filename = 'erugo-diagnostics-' . now()->format('Y-m-d-His') . '.zip';
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        try {
            $files = $this->collectDiagnostics();
            $this->buildEncryptedZip($files, $tmpPath, $password);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to generate diagnostic bundle: ' . $e->getMessage(),
            ], 500);
        }

        // One-time download token, valid 15 minutes
        $token = Str::random(48);
        Cache::put('diag_' . $token, ['path' => $tmpPath, 'filename' => $filename], now()->addMinutes(15));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'token'    => $token,
                'key'      => $password,
                'filename' => $filename,
            ],
        ]);
    }

    /**
     * Stream the cached diagnostic bundle to the browser (one-time).
     */
    public function download(string $token)
    {
        $user = Auth::user();
        if (!$user || !$user->admin) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $bundle = Cache::get('diag_' . $token);
        if (!$bundle || !file_exists($bundle['path'])) {
            return response()->json(['status' => 'error', 'message' => 'Bundle not found or expired'], 404);
        }

        Cache::forget('diag_' . $token);

        return response()->download($bundle['path'], $bundle['filename'], [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $bundle['filename'] . '"',
        ])->deleteFileAfterSend(true);
    }

    // -------------------------------------------------------------------------
    // Data collection
    // -------------------------------------------------------------------------

    private function collectDiagnostics(): array
    {
        $files = [];

        $files['README.txt']      = $this->buildReadme();
        $files['system.json']     = $this->systemInfo();
        $files['environment.json']= $this->environmentInfo();
        $files['database.json']   = $this->databaseInfo();
        $files['shares.json']     = $this->sharesInfo();
        $files['users.json']      = $this->usersInfo();
        $files['settings.json']   = $this->settingsInfo();
        $files['storage.json']    = $this->storageInfo();
        $files['queue.json']      = $this->queueInfo();
        $files['logs/laravel.log']= $this->laravelLog();

        return $files;
    }

    private function buildReadme(): string
    {
        return <<<TXT
Erugo Diagnostic Bundle
=======================
Generated : {$this->now()}
App       : {$this->appVersion()}

This ZIP archive is password-protected with AES-256.
The decryption code was displayed once in the Erugo admin UI at the time
this bundle was generated.  Keep it secure.

Contents
--------
  README.txt        — this file
  system.json       — PHP, OS, memory, disk capacity
  environment.json  — sanitised runtime environment variables
  database.json     — DB engine, connection info, record counts
  shares.json       — per-share summary (no file paths or passwords)
  users.json        — user list (no password hashes)
  settings.json     — application settings (sensitive values redacted)
  storage.json      — storage directory tree and sizes
  queue.json        — queue driver and pending job count
  logs/laravel.log  — last 1 000 lines of the Laravel application log

TXT;
    }

    private function systemInfo(): string
    {
        $uptime = '';
        if (is_readable('/proc/uptime')) {
            $secs   = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
            $uptime = sprintf('%dd %dh %dm', intdiv($secs, 86400), intdiv($secs % 86400, 3600), intdiv($secs % 3600, 60));
        }

        return json_encode([
            'erugo_version'  => $this->appVersion(),
            'php_version'    => phpversion(),
            'php_extensions' => get_loaded_extensions(),
            'laravel_version'=> app()->version(),
            'os'             => php_uname(),
            'server_software'=> $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'uptime'         => $uptime ?: 'unavailable',
            'memory_limit'   => ini_get('memory_limit'),
            'max_exec_time'  => ini_get('max_execution_time'),
            'generated_at'   => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function environmentInfo(): string
    {
        $redactPatterns = ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'PRIVATE', 'CREDENTIAL'];
        $safe = [];
        foreach ($_ENV + getenv() as $k => $v) {
            $upper = strtoupper($k);
            $redact = false;
            foreach ($redactPatterns as $pat) {
                if (str_contains($upper, $pat)) { $redact = true; break; }
            }
            $safe[$k] = $redact ? '*** REDACTED ***' : $v;
        }
        ksort($safe);

        return json_encode(['environment' => $safe, 'generated_at' => $this->now()], JSON_PRETTY_PRINT);
    }

    private function databaseInfo(): string
    {
        $conn    = config('database.default');
        $driver  = config("database.connections.{$conn}.driver", 'unknown');
        $dbName  = config("database.connections.{$conn}.database", 'unknown');

        $dbSize = null;
        if ($driver === 'sqlite' && file_exists($dbName)) {
            $dbSize = filesize($dbName);
        }

        return json_encode([
            'connection'   => $conn,
            'driver'       => $driver,
            'database'     => basename((string) $dbName),
            'database_size_bytes' => $dbSize,
            'counts' => [
                'users'    => User::count(),
                'shares'   => Share::count(),
                'files'    => File::count(),
                'settings' => Setting::count(),
            ],
            'share_status_breakdown' => Share::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->pluck('count', 'status'),
            'generated_at' => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function sharesInfo(): string
    {
        $shares = Share::with('user:id,name,email')
            ->select('id', 'name', 'user_id', 'status', 'file_count', 'size',
                     'created_at', 'updated_at', 'expires_at', 'invite_id',
                     'deletion_requested_at', 'deletion_requested_by')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'owner'        => $s->user ? $s->user->email : null,
                'status'       => $s->status,
                'file_count'   => $s->file_count,
                'size_bytes'   => $s->size,
                'created_at'   => $s->created_at,
                'expires_at'   => $s->expires_at,
                'updated_at'   => $s->updated_at,
                'pending_deletion' => $s->deletion_requested_at ? [
                    'requested_at' => $s->deletion_requested_at,
                    'requested_by' => $s->deletion_requested_by,
                ] : null,
            ]);

        return json_encode([
            'total'        => $shares->count(),
            'shares'       => $shares,
            'generated_at' => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function usersInfo(): string
    {
        $users = User::select('id', 'name', 'email', 'admin', 'created_at', 'updated_at')
            ->withCount('shares')
            ->orderBy('id')
            ->get();

        return json_encode([
            'total'        => $users->count(),
            'users'        => $users,
            'generated_at' => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function settingsInfo(): string
    {
        $settings = Setting::all()->mapWithKeys(function ($s) {
            $redact = false;
            foreach (self::SENSITIVE_SETTING_KEYS as $pat) {
                if (str_contains(strtolower($s->key), $pat)) { $redact = true; break; }
            }
            return [$s->key => $redact ? '*** REDACTED ***' : $s->value];
        });

        return json_encode([
            'settings'     => $settings,
            'generated_at' => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function storageInfo(): string
    {
        $base    = storage_path('app');
        $info    = ['base_path' => $base, 'generated_at' => $this->now(), 'directories' => []];

        foreach (['shares', 'uploads', 'public', 'private'] as $dir) {
            $path = $base . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($path)) {
                $info['directories'][$dir] = [
                    'path'        => $path,
                    'size_bytes'  => $this->dirSize($path),
                    'file_count'  => $this->dirFileCount($path),
                ];
            }
        }

        $info['disk'] = [
            'total_bytes' => disk_total_space($base),
            'free_bytes'  => disk_free_space($base),
            'used_bytes'  => disk_total_space($base) - disk_free_space($base),
        ];

        return json_encode($info, JSON_PRETTY_PRINT);
    }

    private function queueInfo(): string
    {
        $driver = config('queue.default', 'sync');
        $pending = null;
        try {
            $pending = DB::table('jobs')->count();
        } catch (\Throwable) {}

        return json_encode([
            'driver'        => $driver,
            'pending_jobs'  => $pending,
            'generated_at'  => $this->now(),
        ], JSON_PRETTY_PRINT);
    }

    private function laravelLog(): string
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) {
            return "Log file not found: {$logPath}\n";
        }

        // Read last 1 000 lines efficiently
        $lines = [];
        $fp    = fopen($logPath, 'r');
        fseek($fp, 0, SEEK_END);
        $pos    = ftell($fp);
        $buffer = '';
        $count  = 0;

        while ($pos > 0 && $count < 1000) {
            $chunk   = min($pos, 8192);
            $pos    -= $chunk;
            fseek($fp, $pos);
            $buffer  = fread($fp, $chunk) . $buffer;
            $count   = substr_count($buffer, "\n");
        }
        fclose($fp);

        $all = explode("\n", $buffer);
        return implode("\n", array_slice($all, -1000));
    }

    // -------------------------------------------------------------------------
    // ZIP builder
    // -------------------------------------------------------------------------

    private function buildEncryptedZip(array $files, string $dest, string $password): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \RuntimeException("ZipArchive::open failed with code {$result}");
        }

        $zip->setPassword($password);

        foreach ($files as $name => $content) {
            // Ensure subdirectories exist inside the archive
            $dir = dirname($name);
            if ($dir !== '.') {
                $zip->addEmptyDir($dir);
            }
            $zip->addFromString($name, $content);
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
        }

        if (!$zip->close()) {
            throw new \RuntimeException('ZipArchive::close failed — archive may be incomplete');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function dirSize(string $path): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    private function dirFileCount(string $path): int
    {
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $count++;
        }
        return $count;
    }

    private function appVersion(): string
    {
        return env('APP_VERSION', config('app.version', 'unknown'));
    }

    private function now(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
