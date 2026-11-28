<?php

namespace App\Utils;

class PathGuard
{
    /**
     * True when $path resolves to $base itself or to something inside it.
     *
     * Both paths go through realpath, so they must exist. The check compares
     * against $base plus a directory separator, so a sibling whose name only
     * starts with the same characters (shares/abc vs shares/abcevil) fails.
     */
    public static function isWithin(string $path, string $base): bool
    {
        $resolvedPath = realpath($path);
        $resolvedBase = realpath($base);

        if ($resolvedPath === false || $resolvedBase === false) {
            return false;
        }

        if ($resolvedPath === $resolvedBase) {
            return true;
        }

        return str_starts_with($resolvedPath, rtrim($resolvedBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    /**
     * Path on disk of the file in a single-file share, or null if it is
     * missing or resolves outside the share folder. Files uploaded with a
     * folder path are stored under full_path inside the share folder.
     */
    public static function singleShareFile(string $sharePath, ?string $fullPath, string $name): ?string
    {
        $filePath = $sharePath . '/' . ($fullPath ? $fullPath . '/' : '') . $name;

        if (!is_file($filePath) || !self::isWithin($filePath, $sharePath)) {
            return null;
        }

        return realpath($filePath);
    }
}
