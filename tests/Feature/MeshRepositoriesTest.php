<?php

namespace Tests\Feature;

use App\Contracts\Mesh\ManifestRepository;
use App\Contracts\Mesh\NodeRepository;
use App\Contracts\Mesh\ReplicationRepository;
use App\Contracts\Mesh\TombstoneRepository;
use App\Models\MeshTombstone;
use App\Repositories\Mesh\EloquentManifestRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeshRepositoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->run();
    }

    public function test_repository_interfaces_are_bound(): void
    {
        $this->assertInstanceOf(EloquentManifestRepository::class, app(ManifestRepository::class));
        $this->assertInstanceOf(NodeRepository::class, app(NodeRepository::class));
        $this->assertInstanceOf(ReplicationRepository::class, app(ReplicationRepository::class));
        $this->assertInstanceOf(TombstoneRepository::class, app(TombstoneRepository::class));
    }

    public function test_intent_status_and_expiry_are_persisted(): void
    {
        $manifests = app(ManifestRepository::class);

        $manifests->createIntent([
            'intent_id' => 'intent_1',
            'object_id' => 'obj_intent_1',
            'user_id' => 1,
            'filename' => 'clip.mp4',
            'size' => 1024,
            'total_hash' => str_repeat('a', 64),
            'chunk_size' => 512,
            'chunk_count' => 2,
            'chunk_hashes' => [str_repeat('a', 64), str_repeat('b', 64)],
            'expires_at' => now()->addMinute(),
        ]);

        $this->assertSame('pending', $manifests->findIntent('intent_1')->status);

        $manifests->setIntentStatus('intent_1', 'committed');
        $this->assertSame('committed', $manifests->findIntent('intent_1')->status);

        $manifests->createIntent([
            'intent_id' => 'intent_old',
            'object_id' => 'obj_intent_old',
            'user_id' => 1,
            'filename' => 'old.mp4',
            'size' => 10,
            'total_hash' => str_repeat('b', 64),
            'chunk_size' => 10,
            'chunk_count' => 1,
            'chunk_hashes' => [str_repeat('c', 64)],
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, $manifests->expireStaleIntents(now()));
        $this->assertSame('expired', $manifests->findIntent('intent_old')->status);
        $this->assertSame('committed', $manifests->findIntent('intent_1')->status);
    }

    public function test_chunks_are_idempotent_and_acknowledgements_are_counted(): void
    {
        $manifests = app(ManifestRepository::class);
        $manifests->createObject([
            'object_id' => 'object_1',
            'original_name' => 'clip.mp4',
            'size' => 1000,
            'chunk_size' => 500,
            'chunk_count' => 2,
            'total_hash' => str_repeat('c', 64),
        ]);

        $first = $manifests->addChunk('object_1', 0, str_repeat('d', 64), 500);
        $again = $manifests->addChunk('object_1', 0, str_repeat('e', 64), 500);

        $this->assertSame($first->id, $again->id);
        $this->assertCount(1, $manifests->chunksFor('object_1'));

        $manifests->recordPlacement($first->id, 'peer1', 'acknowledged', str_repeat('d', 64));
        $manifests->recordPlacement($first->id, 'peer2', 'acknowledged', str_repeat('d', 64));

        $this->assertSame(2, $manifests->acknowledgedPlacementCount($first->id));
    }

    public function test_generation_and_tombstone_are_idempotent(): void
    {
        $manifests = app(ManifestRepository::class);
        $tombstones = app(TombstoneRepository::class);

        $manifests->createObject([
            'object_id' => 'object_2',
            'original_name' => 'clip.mp4',
            'size' => 100,
            'chunk_size' => 100,
            'chunk_count' => 1,
            'total_hash' => str_repeat('f', 64),
        ]);

        $this->assertSame(2, $manifests->advanceGeneration('object_2'));
        $this->assertSame(3, $manifests->advanceGeneration('object_2'));

        $tombstones->enqueue('object_2', 3, now());
        $tombstones->enqueue('object_2', 3, now()->addMinute());

        $this->assertSame(1, MeshTombstone::count());
        $this->assertCount(1, $tombstones->pending());

        $tombstone = $tombstones->pending()->first();
        $tombstones->markApplied($tombstone->id);
        $this->assertCount(0, $tombstones->pending());
    }

    public function test_node_health_and_replication_queue(): void
    {
        $nodes = app(NodeRepository::class);
        $replications = app(ReplicationRepository::class);

        app(ManifestRepository::class)->createObject([
            'object_id' => 'object_1',
            'original_name' => 'clip.mp4',
            'size' => 100,
            'chunk_size' => 100,
            'chunk_count' => 1,
            'total_hash' => str_repeat('f', 64),
        ]);

        $nodes->upsert('peer1', 'http://peer1:80');
        $nodes->upsert('peer2', 'http://peer2:80');
        $nodes->markSeen('peer1');

        $this->assertCount(1, $nodes->healthyNodes());
        $this->assertSame('peer1', $nodes->healthyNodes()->first()->node_id);

        $replications->enqueue('object_1', 'peer2', now()->subMinute());
        $this->assertCount(1, $replications->pending());

        $job = $replications->pending()->first();
        $replications->mark($job->id, 'failed', 'peer offline');

        $this->assertCount(1, $replications->pending());
        $this->assertSame(1, $replications->pending()->first()->attempts);
    }
}
