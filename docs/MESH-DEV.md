# Chunked mesh storage — development and test setup

**Status: experimental, dev/testing only.** This branch adds phase 1 of a sharded storage design. Uploads are split into chunks, written to at least two peers, acknowledged by hash, and committed only when every chunk is durable. Background replication to full copies and the fast chunked download path are later tasks; the normal single-node upload/download paths still work unchanged.

Do **not** put these settings on a production Erugo instance. Phase 1 keeps one SQLite database on the coordinator and does not provide application-level failover.

These files are on branch `feat/chunk-mesh-phase1`, not on `main`. If you cloned the repository normally, check out the branch first:

```sh
git fetch origin feat/chunk-mesh-phase1
git checkout -b feat/chunk-mesh-phase1 --track origin/feat/chunk-mesh-phase1
ls docker-compose.mesh-dev.yml docker-compose.mesh-node.yml
```

## Architecture in one paragraph

One **coordinator** serves the familiar Erugo UI/API and owns users, shares, manifests and download accounting. Two private **storage peers** hold chunks. All client traffic enters through one **gateway**: normal paths go to the coordinator, `/n/<node>/*` paths are routed to the named peer. Peers never make authorization decisions; they accept only short-lived HMAC tokens issued by the coordinator and verify the SHA-256 of the bytes they receive. After a peer stores a chunk it calls the coordinator back with a signed acknowledgement. The coordinator commits the upload only when every chunk has the configured number of ACKs.

## Local three-instance gateway (recommended main gate)

The compose file starts three app containers and one Caddy gateway. It uses ports 18091-18094 so it does not collide with a normal Erugo dev stack.

```sh
# from the repository root
docker compose -p erugo-mesh-dev -f docker-compose.mesh-dev.yml config
docker compose -p erugo-mesh-dev -f docker-compose.mesh-dev.yml up -d --build
```

| Service | Direct port | Purpose |
|---|---|---|
| coordinator | 18091 | UI/API, manifests, commit |
| peer1 | 18092 | chunk storage |
| peer2 | 18093 | chunk storage |
| **gateway** | **18094** | the only entry point clients should use |

Check the gate:

```sh
for node in coordinator peer1 peer2; do
  curl -sS http://127.0.0.1:18094/n/$node/health; echo
done
```

Each response should report the matching `node`. If a node name is wrong, the gateway routing is wrong.

Run the mesh tests through the coordinator container (they use an in-memory database and do not touch app data):

```sh
docker compose -p erugo-mesh-dev -f docker-compose.mesh-dev.yml exec coordinator \
  php artisan test --filter=Mesh
```

Stop the harness with:

```sh
docker compose -p erugo-mesh-dev -f docker-compose.mesh-dev.yml down
```

The dev image's startup script writes `storage/jwt.secret` and may rewrite the `name` field in `package-lock.json` in the bind mount. The JWT file is gitignored; revert the lockfile if it changes:

```sh
git checkout -- package-lock.json
```

## Multi-host test deployment

You can run one coordinator and two peers on separate hosts. Keep all peer ports private; only the gateway should be reachable by clients. Use `docker-compose.mesh-node.yml` on each host. It builds the production image (code is baked into the image, only `storage` is persistent) and takes all differences from `.env.mesh`:

```sh
cd Erugo
git fetch origin feat/chunk-mesh-phase1
git checkout -b feat/chunk-mesh-phase1 --track origin/feat/chunk-mesh-phase1
cp .env.mesh.example .env.mesh
# edit .env.mesh: unique APP_KEY/JWT_SECRET, shared MESH_SECRET,
# correct MESH_NODE_ID, MESH_COORDINATOR_URL and MESH_BIND
docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml up -d --build
docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml ps
```

On the coordinator host use `MESH_NODE_ID=coordinator`. On the other two hosts use `peer1` and `peer2`. The coordinator can also run through this file; the three-instance compose file is only for a single-host local gate.

Set these on **every** app instance:

| Variable | Value |
|---|---|
| `MESH_ENABLED` | `true` |
| `MESH_SECRET` | the same long random value on all three instances (at least 32 bytes) |
| `MESH_NODE_ID` | unique per instance: `coordinator`, `peer1`, `peer2` |
| `MESH_WRITE_COPIES` | `2` |
| `MESH_CHUNK_SIZE` | e.g. `8388608` (8 MiB) |
| `MESH_COORDINATOR_URL` | base URL of the coordinator API, reachable **from the peers** (used for ACK callbacks) |

On the coordinator also set `MESH_NODES` to the peer ids:

```json
{"peer1":"http://peer1:80","peer2":"http://peer2:80"}
```

On the gateway container set the real upstreams. `docker/mesh/Caddyfile.dev` reads these from the environment:

| Variable | Example |
|---|---|
| `MESH_PEER1_URL` | `http://10.0.0.11:80` |
| `MESH_PEER2_URL` | `http://10.0.0.12:80` |
| `MESH_COORDINATOR_URL` | `http://10.0.0.10:80` |

Connectivity required:

- gateway → coordinator, gateway → every peer
- every peer → coordinator (for placement ACKs)
- clients → gateway only

Do not reuse the throwaway secret in `docker-compose.mesh-dev.yml`. Do not share `APP_KEY` between instances; the mesh HMAC is intentionally independent of Laravel signed URLs.

Each instance keeps its own SQLite database and chunk directory. That is expected in phase 1; metadata portability and application failover are deliberately later work.

## Upload protocol (for testing with curl)

1. Log in on the coordinator to get a JWT using the existing `/api/auth/login` endpoint.
2. Split a file into `MESH_CHUNK_SIZE` pieces, computing SHA-256 for each chunk and for the whole file.
3. Create an intent:

```sh
curl -sS -X POST http://GATEWAY/api/mesh/uploads \
  -H 'Authorization: Bearer <jwt>' -H 'Content-Type: application/json' \
  -d '{"filename":"clip.mp4","size":12345678,"total_hash":"<sha256>","chunk_hashes":["<sha0>","<sha1>"]}'
```

The response contains `intent_id`, `object_id`, `chunk_size` and `chunk_count`.

4. Ask for signed PUT tokens:

```sh
curl -sS -X POST http://GATEWAY/api/mesh/uploads/<intent_id>/tokens \
  -H 'Authorization: Bearer <jwt>' -H 'Content-Type: application/json' \
  -d '{"indices":[0,1]}'
```

Tokens are returned per node and per chunk. They are single-use and short-lived; call this endpoint again if an upload takes longer than the TTL or needs a retry.

5. PUT each chunk to two different nodes through the gateway, using the token for that node and chunk:

```sh
curl -sS -X PUT http://GATEWAY/n/peer1/chunks/<intent_id>/0 \
  -H 'X-Erugo-Mesh-Token: <token>' \
  --data-binary @chunk-0.bin
```

A peer returns `200` with `acknowledged: true` only after it has verified the bytes and the coordinator has recorded the placement. A hash mismatch returns `422`; a failed ACK callback returns `503` so the client can retry.

6. Commit:

```sh
curl -sS -X POST http://GATEWAY/api/mesh/uploads/<intent_id>/commit \
  -H 'Authorization: Bearer <jwt>'
```

If any chunk is short of two ACKs the response is `409` and names the missing indexes. A successful commit returns `status: "sharded"`.

7. Abandoned intents are cleaned up by `php artisan mesh:gc`, which expires them and queues tombstones for peers.

## What is deliberately not here yet

- Chunked download assembly and the browser fast path (phase 1 task 1.5).
- Background replication to full copies on every peer (task 1.6).
- Delete/expiry fan-out to peers and DB-backed stats (task 1.7).
- Shared Postgres, full app failover, erasure coding, dynamic node discovery or migration of existing local shares.

See `specs/chunk-mesh-storage.md` for the full spec and `specs/planning-context.yaml` for the decisions behind it.
