<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Share;
use App\Models\Download;
use App\Models\User;
use App\Models\File;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Get system information for the About page
     * Available to all authenticated users
     */
    public function getSystemInfo()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'erugo_version' => config('app.version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database_driver' => config('database.default'),
                'database_version' => $this->getDatabaseVersion(),
                'server_os' => PHP_OS_FAMILY,
                'timezone' => config('app.timezone'),
                'max_upload_size' => $this->getMaxUploadSize(),
                'storage' => $this->getStorageStats(),
            ]
        ]);
    }

    /**
     * Get the database version string
     */
    private function getDatabaseVersion()
    {
        try {
            $driver = config('database.default');
            
            switch ($driver) {
                case 'sqlite':
                    $version = DB::select('SELECT sqlite_version() as version');
                    return $version[0]->version ?? 'Unknown';
                case 'mysql':
                    $version = DB::select('SELECT VERSION() as version');
                    return $version[0]->version ?? 'Unknown';
                case 'pgsql':
                    $version = DB::select('SHOW server_version');
                    return $version[0]->server_version ?? 'Unknown';
                default:
                    return 'Unknown';
            }
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get the maximum upload size based on PHP settings
     */
    private function getMaxUploadSize()
    {
        $uploadMax = $this->parseSize(ini_get('upload_max_filesize'));
        $postMax = $this->parseSize(ini_get('post_max_size'));
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));
        
        // -1 means no limit
        if ($memoryLimit == -1) {
            $memoryLimit = PHP_INT_MAX;
        }
        
        $maxBytes = min($uploadMax, $postMax, $memoryLimit);
        
        return [
            'bytes' => $maxBytes,
            'formatted' => $this->formatBytes($maxBytes),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }

    /**
     * Parse PHP size string (e.g., "128M") to bytes
     */
    private function parseSize($size)
    {
        $size = trim($size);
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }

    public function getStats(Request $request)
    {
        $days = (int) $request->input('days', 30);
        
        // Storage stats
        $storageStats = $this->getStorageStats();
        
        // Share stats
        $shareStats = $this->getShareStats();
        
        // Download stats
        $downloadStats = $this->getDownloadStats($days);
        
        // User stats
        $userStats = $this->getUserStats();
        
        // File type distribution
        $fileTypeStats = $this->getFileTypeStats();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'storage' => $storageStats,
                'shares' => $shareStats,
                'downloads' => $downloadStats,
                'users' => $userStats,
                'file_types' => $fileTypeStats,
            ]
        ]);
    }
    
    private function getStorageStats()
    {
        // Get storage path
        $storagePath = storage_path('app/shares');
        
        // Calculate used storage from shares table
        $usedBytes = Share::where('status', '!=', 'deleted')->sum('size');
        
        // If the shares directory doesn't exist, use the app directory or create it
        if (!file_exists($storagePath)) {
            $storagePath = storage_path('app');
        }
        
        // Get disk space info
        $totalDiskSpace = disk_total_space($storagePath);
        $freeDiskSpace = disk_free_space($storagePath);
        
        return [
            'used_bytes' => (int) $usedBytes,
            'used_formatted' => $this->formatBytes($usedBytes),
            'disk_total_bytes' => $totalDiskSpace,
            'disk_total_formatted' => $this->formatBytes($totalDiskSpace),
            'disk_free_bytes' => $freeDiskSpace,
            'disk_free_formatted' => $this->formatBytes($freeDiskSpace),
            'disk_used_bytes' => $totalDiskSpace - $freeDiskSpace,
            'disk_used_formatted' => $this->formatBytes($totalDiskSpace - $freeDiskSpace),
            'disk_usage_percent' => round((($totalDiskSpace - $freeDiskSpace) / $totalDiskSpace) * 100, 1),
            'shares_usage_percent' => $totalDiskSpace > 0 ? round(($usedBytes / $totalDiskSpace) * 100, 1) : 0,
        ];
    }
    
    private function getShareStats()
    {
        $totalShares = Share::count();
        $activeShares = Share::where('status', '!=', 'deleted')
            ->where('expires_at', '>', now())
            ->count();
        $expiredShares = Share::where('status', '!=', 'deleted')
            ->where('expires_at', '<=', now())
            ->count();
        $deletedShares = Share::where('status', 'deleted')->count();
        
        // Password protected shares
        $passwordProtectedShares = Share::where('status', '!=', 'deleted')
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->count();
        
        // Shares created in last 7 days
        $recentShares = Share::where('created_at', '>=', now()->subDays(7))->count();
        
        // Total files across all shares
        $totalFiles = File::count();
        
        // Average files per share
        $avgFilesPerShare = $totalShares > 0 ? round($totalFiles / $totalShares, 1) : 0;
        
        return [
            'total' => $totalShares,
            'active' => $activeShares,
            'expired' => $expiredShares,
            'deleted' => $deletedShares,
            'password_protected' => $passwordProtectedShares,
            'recent_7_days' => $recentShares,
            'total_files' => $totalFiles,
            'avg_files_per_share' => $avgFilesPerShare,
        ];
    }
    
    private function getDownloadStats($days)
    {
        $startDate = now()->subDays($days);
        
        // Total downloads in period
        $totalDownloads = Download::where('created_at', '>=', $startDate)->count();
        
        // Downloads by day for chart
        $downloadsByDay = Download::where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();
        
        // Fill in missing days with 0
        $filledDownloadsByDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $filledDownloadsByDay[$date] = $downloadsByDay[$date] ?? 0;
        }
        
        // Most downloaded shares
        $topShares = Download::where('downloads.created_at', '>=', $startDate)
            ->join('shares', 'downloads.share_id', '=', 'shares.id')
            ->select('shares.id', 'shares.name', 'shares.long_id', DB::raw('COUNT(downloads.id) as download_count'))
            ->groupBy('shares.id', 'shares.name', 'shares.long_id')
            ->orderByDesc('download_count')
            ->limit(5)
            ->get();
        
        // Unique IPs downloading
        $uniqueDownloaders = Download::where('created_at', '>=', $startDate)
            ->distinct('ip_address')
            ->count('ip_address');
        
        // All-time downloads
        $allTimeDownloads = Download::count();
        
        return [
            'period_days' => $days,
            'total_in_period' => $totalDownloads,
            'all_time' => $allTimeDownloads,
            'by_day' => $filledDownloadsByDay,
            'top_shares' => $topShares,
            'unique_downloaders' => $uniqueDownloaders,
        ];
    }
    
    private function getUserStats()
    {
        $totalUsers = User::count();
        $adminUsers = User::where('admin', true)->count();
        $activeUsers = User::where('active', true)->count();
        $guestUsers = User::where('is_guest', true)->count();
        
        // Users with shares
        $usersWithShares = Share::distinct('user_id')->count('user_id');
        
        // Most active users (by share count)
        $topUsers = User::withCount(['shares' => function ($query) {
                $query->where('status', '!=', 'deleted');
            }])
            ->orderByDesc('shares_count')
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'shares_count' => $user->shares_count,
                ];
            });
        
        return [
            'total' => $totalUsers,
            'admins' => $adminUsers,
            'active' => $activeUsers,
            'guests' => $guestUsers,
            'with_shares' => $usersWithShares,
            'top_users' => $topUsers,
        ];
    }
    
    private function getFileTypeStats()
    {
        // Get file type distribution
        $fileTypes = File::select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(size) as total_size'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Categorize by general type
        $categories = [
            'images' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp'],
            'documents' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/rtf'],
            'videos' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3', 'audio/flac'],
            'archives' => ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/gzip', 'application/x-tar'],
            'code' => ['text/html', 'text/css', 'text/javascript', 'application/javascript', 'application/json', 'text/xml'],
        ];
        
        $categorizedStats = [];
        foreach ($categories as $category => $mimeTypes) {
            $stats = File::whereIn('type', $mimeTypes)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(size), 0) as total_size')
                ->first();
            $categorizedStats[$category] = [
                'count' => (int) $stats->count,
                'total_size' => (int) $stats->total_size,
                'total_size_formatted' => $this->formatBytes($stats->total_size ?? 0),
            ];
        }
        
        // Other files (not in any category)
        $allCategorizedTypes = array_merge(...array_values($categories));
        $otherStats = File::whereNotIn('type', $allCategorizedTypes)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(size), 0) as total_size')
            ->first();
        $categorizedStats['other'] = [
            'count' => (int) $otherStats->count,
            'total_size' => (int) $otherStats->total_size,
            'total_size_formatted' => $this->formatBytes($otherStats->total_size ?? 0),
        ];
        
        return [
            'by_type' => $fileTypes,
            'by_category' => $categorizedStats,
        ];
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $base = log($bytes, 1024);
        $index = floor($base);
        
        if ($index >= count($units)) {
            $index = count($units) - 1;
        }
        
        return round(pow(1024, $base - $index), $precision) . ' ' . $units[$index];
    }
}

