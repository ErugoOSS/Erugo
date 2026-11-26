<?php

namespace App\Contracts\Mesh;

use App\Models\MeshTombstone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface TombstoneRepository
{
    public function enqueue(
        string $objectId,
        int $generation,
        CarbonInterface $availableAt
    ): MeshTombstone;

    public function pending(int $limit = 100): Collection;

    public function markApplied(int $id): void;

    public function markFailed(int $id, string $error): void;
}
