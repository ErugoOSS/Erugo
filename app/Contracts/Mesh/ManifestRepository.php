<?php

namespace App\Contracts\Mesh;

use App\Models\MeshChunk;
use App\Models\MeshObject;
use App\Models\MeshPlacement;
use App\Models\MeshUploadIntent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ManifestRepository
{
    public function createIntent(array $attributes): MeshUploadIntent;

    public function findIntent(string $intentId): ?MeshUploadIntent;

    public function setIntentStatus(string $intentId, string $status): void;

    public function expireStaleIntents(CarbonInterface $before): int;

    public function createObject(array $attributes): MeshObject;

    public function findObject(string $objectId): ?MeshObject;

    public function addChunk(string $objectId, int $index, string $hash, int $size): MeshChunk;

    public function findChunk(string $objectId, int $index): ?MeshChunk;

    public function recordPlacement(
        int $chunkId,
        string $nodeId,
        string $state,
        ?string $verifiedHash = null
    ): MeshPlacement;

    public function acknowledgedPlacementCount(int $chunkId): int;

    public function chunksFor(string $objectId): Collection;

    public function setObjectStatus(string $objectId, string $status): void;

    public function advanceGeneration(string $objectId): int;
}
