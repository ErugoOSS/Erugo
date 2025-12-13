<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BackupsController extends Controller
{
    /**
     * Path to the backups directory
     */
    private function getBackupPath(): string
    {
        return storage_path('app/backups/');
    }

    /**
     * List all backup files with metadata
     */
    public function index()
    {
        $backupPath = $this->getBackupPath();
        
        // Ensure the backups directory exists
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0777, true);
        }
        
        $backups = [];
        $files = glob($backupPath . '*.sqlite');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'created_at_timestamp' => filemtime($file),
            ];
        }
        
        // Sort by creation time, newest first
        usort($backups, function ($a, $b) {
            return $b['created_at_timestamp'] - $a['created_at_timestamp'];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'backups' => $backups,
                'backup_path' => $backupPath,
            ]
        ]);
    }

    /**
     * Create a new backup on-demand
     */
    public function create()
    {
        // Only proceed if using SQLite
        if (config('database.default') !== 'sqlite') {
            return response()->json([
                'status' => 'error',
                'message' => 'Database backup is only available for SQLite databases'
            ], 400);
        }

        $backupPath = $this->getBackupPath();

        // Check the backups directory exists
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0777, true);
        }

        $backupName = 'database_backup_' . now()->format('Y-m-d_H-i-s') . '.sqlite';
        $backupFile = $backupPath . $backupName;

        try {
            DB::connection('sqlite')->statement("VACUUM INTO ?", [$backupFile]);
            Log::info('On-demand database backup created: ' . $backupName);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Backup created successfully',
                'data' => [
                    'backup' => [
                        'filename' => $backupName,
                        'size' => filesize($backupFile),
                        'size_formatted' => $this->formatBytes(filesize($backupFile)),
                        'created_at' => date('Y-m-d H:i:s', filemtime($backupFile)),
                        'created_at_timestamp' => filemtime($backupFile),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('On-demand database backup failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a specific backup file
     */
    public function download(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        
        // Validate filename format
        if (!preg_match('/^database_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sqlite$/', $filename)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid backup filename'
            ], 400);
        }
        
        $backupPath = $this->getBackupPath();
        $filePath = $backupPath . $filename;
        
        if (!file_exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backup file not found'
            ], 404);
        }
        
        Log::info('Backup downloaded: ' . $filename);
        
        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Delete a specific backup file
     */
    public function delete(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        
        // Validate filename format
        if (!preg_match('/^database_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sqlite$/', $filename)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid backup filename'
            ], 400);
        }
        
        $backupPath = $this->getBackupPath();
        $filePath = $backupPath . $filename;
        
        if (!file_exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Backup file not found'
            ], 404);
        }
        
        try {
            unlink($filePath);
            Log::info('Backup deleted: ' . $filename);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Backup deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete backup: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete backup'
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($bytes, 1024);
        $index = floor($base);
        
        if ($index >= count($units)) {
            $index = count($units) - 1;
        }
        
        return round(pow(1024, $base - $index), $precision) . ' ' . $units[$index];
    }
}

