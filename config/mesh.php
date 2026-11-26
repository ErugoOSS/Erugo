<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mesh storage (phase 1: dev / opt-in)
    |--------------------------------------------------------------------------
    |
    | The coordinator is the only public instance. Storage peers are reached
    | through node-specific paths on the same hostname. Nothing here is read
    | outside config files, so env() is safe in this file.
    |
    */

    'enabled' => filter_var(env('MESH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Independent of APP_KEY. Must be a long random value shared only by the
    // coordinator and the storage peers. Never expose it to browsers.
    'secret' => env('MESH_SECRET', ''),

    'node_id' => env('MESH_NODE_ID', 'local'),

    // Where peers send acknowledgement callbacks on the coordinator.
    'coordinator_url' => env('MESH_COORDINATOR_URL', 'http://localhost'),

    // Local chunk storage on a peer. Must be writable and persistent.
    'chunk_root' => env('MESH_CHUNK_ROOT', storage_path('app/mesh')),

    // Ed25519 identity file. Private keys live here only, mode 0600.
    'identity_path' => env('MESH_IDENTITY_PATH', storage_path('app/mesh/identity.json')),

    // Registration blobs are short-lived and single use.
    'enrollment_ttl' => max(30, (int) env('MESH_ENROLLMENT_TTL', 300)),

    // Map of node id => private base URL, e.g. {"peer1":"http://peer1:80"}.
    'nodes' => (function () {
        $raw = env('MESH_NODES', '');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    })(),

    'chunk_size' => max(1024 * 1024, (int) env('MESH_CHUNK_SIZE', 8 * 1024 * 1024)),

    // Production default is two acknowledgements so one node loss during
    // upload cannot lose the file. A two-host dev test can set this to 1.
    'write_copies' => max(1, (int) env('MESH_WRITE_COPIES', 2)),

    'max_upload_bytes' => max(1, (int) env('MESH_MAX_UPLOAD_BYTES', 4 * 1024 * 1024 * 1024)),

    'max_chunks' => max(1, (int) env('MESH_MAX_CHUNKS', 10000)),

    'token_ttl' => max(30, (int) env('MESH_TOKEN_TTL', 300)),

    // Upper bound accepted on verification; prevents a long-lived forged token
    // even if a peer's clock is wrong.
    'max_token_ttl' => max(60, (int) env('MESH_MAX_TOKEN_TTL', 900)),
];
