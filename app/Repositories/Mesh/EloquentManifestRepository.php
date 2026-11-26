<?php

namespace App\Repositories\Mesh;

use App\Contracts\Mesh\ManifestRepository;
use App\Models\MeshChunk;
use App\Models\MeshObject;
use App\Models\MeshPlacement;
use App\Models\MeshUploadIntent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentManifestRepository implements ManifestRepository
{
    public function createIntent(array $attributes): MeshUploadIntent
    {
        return MeshUploadIntent::create($attributes);
    }

    public function findIntent(string $intentId): ?MeshUploadIntent
    {
        return MeshUploadIntent::where('intent_id', $intentId)->first();
    }

    public function setIntentStatus(string $intentId, string $status): void
    {
        MeshUploadIntent::where('intent_id', $intentId)->update(['status' => $status]);
    }

    public function expireStaleIntents(CarbonInterface $before): int
    {
        return MeshUploadIntent::where('status', 'pending')
            ->where('expires_at', '<', $before)
            ->update(['status' => 'expired']);
    }

    public function createObject(array $attributes): MeshObject
    {
        return MeshObject::create($attributes);
    }

    public function findObject(string $objectId): ?MeshObject
    {
        return MeshObject::where('object_id', $objectId)->first();
    }

    public function addChunk(string $objectId, int $index, string $hash, int $size): MeshChunk
    {
        $object = $this->findObject($objectId);

        if (!$object) {
            throw new \InvalidArgumentException('Unknown mesh object: ' . $objectId);
        }

        return MeshChunk::firstOrCreate(
            ['mesh_object_id' => $object->id, 'chunk_index' => $index],
            ['hash' => $hash, 'size' => $size]
        );
    }

    public function findChunk(string $objectId, int $index): ?MeshChunk
    {
        $object = $this->findObject($objectId);

        if (!$object) {
            return null;
        }

        return MeshChunk::where('mesh_object_id', $object->id)
            ->where('chunk_index', $index)
            ->first();
    }

    public function recordPlacement(
        int $chunkId,
        string $nodeId,
        string $state,
        ?string $verifiedHash = null
    ): MeshPlacement {
        $attributes = ['state' => $state];

        if ($state === 'acknowledged') {
            $attributes['verified_hash'] = $verifiedHash;
            $attributes['acknowledged_at'] = now();
        }

        return MeshPlacement::updateOrCreate(
            ['chunk_id' => $chunkId, 'node_id' => $nodeId],
            $attributes
        );
    }

    public function acknowledgedPlacementCount(int $chunkId): int
    {
        return MeshPlacement::where('chunk_id', $chunkId)
            ->where('state', 'acknowledged')
            ->count();
    }

    public function chunksFor(string $objectId): Collection
    {
        $object = $this->findObject($objectId);

        return $object ? $object->chunks()->orderBy('chunk_index')->get() : collect();
    }

    public function setObjectStatus(string $objectId, string $status): void
    {
        MeshObject::where('object_id', $objectId)->update(['status' => $status]);
    }

    public function advanceGeneration(string $objectId): int
    {
        $object = $this->findObject($objectId);

        if (!$object) {
            throw new \InvalidArgumentException('Unknown mesh object: ' . $objectId);
        }

        $object->increment('generation');

        return (int) $object->fresh()->generation;
    }
}
