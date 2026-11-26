<?php

namespace App\Repositories\Mesh;

use App\Contracts\Mesh\TombstoneRepository;
use App\Models\MeshTombstone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentTombstoneRepository implements TombstoneRepository
{
    public function enqueue(
        string $objectId,
        int $generation,
        CarbonInterface $availableAt
    ): MeshTombstone {
        return MeshTombstone::firstOrCreate(
            ['object_id' => $objectId, 'generation' => $generation],
            ['state' => 'pending', 'available_at' => $availableAt]
        );
    }

    public function pending(int $limit = 100): Collection
    {
        return MeshTombstone::whereIn('state', ['pending', 'failed'])
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->get();
    }

    public function markApplied(int $id): void
    {
        MeshTombstone::where('id', $id)->update([
            'state' => 'applied',
            'last_error' => null,
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $tombstone = MeshTombstone::findOrFail($id);
        $tombstone->state = 'failed';
        $tombstone->attempts++;
        $tombstone->last_error = $error;
        $tombstone->save();
    }
}
