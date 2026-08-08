<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\LiveshareInvite;
use App\Models\LiveshareTag;

class Liveshare extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'long_id',
        'path',
        'size',
        'file_count',
        'max_size_override',
        'max_files_per_user_override',
    ];

    protected $hidden = [
        'path',
    ];

    /**
     * The owner of the liveshare.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The members of the liveshare (excluding the owner).
     */
    public function members()
    {
        return $this->hasMany(LiveshareMember::class);
    }

    /**
     * The invites for the liveshare.
     */
    public function invites()
    {
        return $this->hasMany(LiveshareInvite::class);
    }

    /**
     * The files in the liveshare.
     */
    public function files()
    {
        return $this->hasMany(LiveshareFile::class);
    }

    /**
     * The tags in the liveshare.
     */
    public function tags()
    {
        return $this->hasMany(LiveshareTag::class);
    }

    /**
     * Check if a user is the owner of this liveshare.
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Get the membership record for a user, or null if not a member.
     */
    public function getMembership(User $user): ?LiveshareMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    /**
     * Check if a user has access to this liveshare (owner or member).
     */
    public function hasAccess(User $user): bool
    {
        return $this->isOwner($user) || $this->getMembership($user) !== null;
    }

    /**
     * Get the effective role for a user. Returns 'owner', a member role string, or null.
     */
    public function getUserRole(User $user): ?string
    {
        if ($this->isOwner($user)) {
            return 'owner';
        }

        $membership = $this->getMembership($user);
        return $membership?->role;
    }

    /**
     * Check if a user can manage this liveshare (owner or manager).
     */
    public function canManage(User $user): bool
    {
        $role = $this->getUserRole($user);
        return in_array($role, ['owner', 'manager']);
    }

    /**
     * Check if a user can add files (owner, manager, or collaborator).
     */
    public function canAddFiles(User $user): bool
    {
        $role = $this->getUserRole($user);
        return in_array($role, ['owner', 'manager', 'collaborator']);
    }

    /**
     * Check if a user can remove files (owner or manager).
     */
    public function canRemoveFiles(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Recalculate the size and file_count from the files relation.
     */
    public function recalculateStats(): void
    {
        $this->size = $this->files()->sum('size');
        $this->file_count = $this->files()->count();
        $this->save();
    }

    /**
     * Delete the liveshare and all associated storage.
     */
    public function deleteWithFiles(): bool
    {
        try {
            $storagePath = storage_path('app/liveshares/' . $this->id);

            if (is_dir($storagePath)) {
                $this->deleteDirectory($storagePath);
            }

            return $this->delete();
        } catch (\Exception $e) {
            \Log::error('Failed to delete liveshare: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursively delete a directory and its contents.
     */
    private function deleteDirectory(string $dir): void
    {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
