<?php

namespace App\Contracts\Mesh;

use App\Models\MeshReplicationJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ReplicationRepository
{
    public function enqueue(
        string $objectId,
        string $nodeId,
        CarbonInterface $availableAt
    ): MeshReplicationJob;

    public function pending(int $limit = 100): Collection;

    public function mark(int $id, string $state, ?string $error = null): void;
}
