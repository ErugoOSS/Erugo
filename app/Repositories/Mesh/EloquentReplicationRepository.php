<?php

namespace App\Repositories\Mesh;

use App\Contracts\Mesh\ReplicationRepository;
use App\Models\MeshObject;
use App\Models\MeshReplicationJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentReplicationRepository implements ReplicationRepository
{
    public function enqueue(
        string $objectId,
        string $nodeId,
        CarbonInterface $availableAt
    ): MeshReplicationJob {
        $object = MeshObject::where('object_id', $objectId)->firstOrFail();

        return MeshReplicationJob::firstOrCreate(
            ['mesh_object_id' => $object->id, 'node_id' => $nodeId],
            ['state' => 'queued', 'available_at' => $availableAt]
        );
    }

    public function pending(int $limit = 100): Collection
    {
        return MeshReplicationJob::whereIn('state', ['queued', 'failed'])
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->get();
    }

    public function mark(int $id, string $state, ?string $error = null): void
    {
        $job = MeshReplicationJob::findOrFail($id);
        $job->state = $state;

        if ($state === 'failed') {
            $job->attempts++;
            $job->last_error = $error;
        }

        $job->save();
    }
}
