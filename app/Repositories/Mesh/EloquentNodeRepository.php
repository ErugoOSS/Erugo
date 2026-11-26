<?php

namespace App\Repositories\Mesh;

use App\Contracts\Mesh\NodeRepository;
use App\Models\MeshNode;
use Illuminate\Support\Collection;

class EloquentNodeRepository implements NodeRepository
{
    public function upsert(string $nodeId, string $endpoint, ?int $capacityBytes = null): MeshNode
    {
        return MeshNode::updateOrCreate(
            ['node_id' => $nodeId],
            ['endpoint' => $endpoint, 'capacity_bytes' => $capacityBytes]
        );
    }

    public function find(string $nodeId): ?MeshNode
    {
        return MeshNode::where('node_id', $nodeId)->first();
    }

    public function markSeen(string $nodeId, bool $healthy = true): void
    {
        MeshNode::where('node_id', $nodeId)->update([
            'healthy' => $healthy,
            'last_seen_at' => now(),
        ]);
    }

    public function healthyNodes(): Collection
    {
        return MeshNode::where('healthy', true)->orderBy('node_id')->get();
    }

    public function enroll(array $attributes): MeshNode
    {
        return MeshNode::updateOrCreate(
            ['node_id' => $attributes['node_id']],
            $attributes
        );
    }

    public function revoke(string $nodeId): bool
    {
        $node = $this->find($nodeId);
        if (!$node) {
            return false;
        }

        $node->status = 'revoked';
        $node->revoked_at = now();
        $node->healthy = false;
        $node->save();

        return true;
    }

    public function enrolled(): Collection
    {
        return MeshNode::whereIn('status', ['active', 'revoked'])
            ->orderBy('node_id')
            ->get();
    }
}
