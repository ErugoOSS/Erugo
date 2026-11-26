<?php

namespace App\Contracts\Mesh;

use App\Models\MeshNode;
use Illuminate\Support\Collection;

interface NodeRepository
{
    public function upsert(string $nodeId, string $endpoint, ?int $capacityBytes = null): MeshNode;

    public function find(string $nodeId): ?MeshNode;

    public function markSeen(string $nodeId, bool $healthy = true): void;

    public function healthyNodes(): Collection;

    public function enroll(array $attributes): MeshNode;

    public function revoke(string $nodeId): bool;

    public function enrolled(): Collection;
}
