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

One coordinator and two peers run on separate hosts. `docker-compose.mesh-dev.yml` is a **single-host gate only**; use `docker-compose.mesh-node.yml` on each remote host. It builds the production image (code is in the image, only `storage` persists) and reads all host-specific values from `.env.mesh`.

### 0. Get the branch on every host

`main` does not contain these files. On each host:

```sh
cd ~/erugoshard/Erugo
git fetch origin feat/chunk-mesh-phase1
git checkout -B feat/chunk-mesh-phase1 origin/feat/chunk-mesh-phase1
git rev-parse --short HEAD   # must be f72fe81 or newer
ls -la .env.mesh.example docker-compose.mesh-node.yml scripts/mesh-bootstrap.sh
```

### 1. Generate a valid `.env.mesh` on each host

Do **not** copy `.env.mesh.example` by hand; it contains placeholders. Run:

```sh
sh scripts/mesh-bootstrap.sh
```

The script asks for:

- node id: `coordinator`, `peer1` or `peer2` (unique per host)
- this host's private IP/hostname — the address the gateway can reach
- the coordinator API URL reachable **from this host**, e.g. `http://100.x.y.z:8090`
- the shared mesh secret — the **same exact value on all three hosts**

It generates a unique `APP_KEY` and `JWT_SECRET` per host, writes `.env.mesh` with mode `600`, and prints a fingerprint of the shared secret. Never set `MESH_BIND=127.0.0.1:...` on a remote peer: the gateway could not reach it. Never use `http://coordinator:8090` on a remote host: `coordinator` is a Docker compose alias that exists only on the gateway host.

On the coordinator host use `MESH_NODE_ID=coordinator`. On the other two use `peer1` and `peer2`.

Verify the shared secret without printing it. Run this on all three hosts; the hashes must match:

```sh
grep '^MESH_SECRET=' .env.mesh | sha256sum
```

### 2. Start the node

```sh
sudo docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml up -d --build
sudo docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml ps
```

Health check locally (use the literal id, not `<node-id>`; angle brackets are shell redirection):

```sh
curl -sS http://127.0.0.1:8090/n/peer1/health
# expected: {"status":"ok","node":"peer1","mesh_enabled":true}
```

From the gateway host, confirm the same URL works there too:

```sh
curl -sS http://<EVERILL_PRIVATE_IP>:8090/n/peer1/health
```

From the peer host, confirm it can reach the coordinator for ACK callbacks:

```sh
curl -sS http://<COORDINATOR_REACHABLE_URL>/api/health
```

### 3. Point the gateway at the remote peers

On the gateway host, create an override file (do not edit the tracked compose file):

```sh
cat > docker-compose.mesh-gateway.override.yml <<'EOF'
services:
  gateway:
    environment:
      MESH_PEER1_URL: http://<EVERILL_PRIVATE_IP>:8090
      MESH_PEER2_URL: http://<THIRD_HOST_PRIVATE_IP>:8090
      MESH_COORDINATOR_URL: http://coordinator:80
EOF

sudo docker compose -p erugo-mesh-dev \
  -f docker-compose.mesh-dev.yml \
  -f docker-compose.mesh-gateway.override.yml up -d gateway
```

If the remote node is `peer1`, stop the local `peer1` container so two instances do not claim the same id:

```sh
sudo docker compose -p erugo-mesh-dev -f docker-compose.mesh-dev.yml stop peer1
```

Final gate check:

```sh
curl -sS http://127.0.0.1:18094/n/peer1/health
```

The response must report `"node":"peer1"` from the remote host. If it reports the local container, the gateway is still using `http://peer1:80`.

`docker/mesh/Caddyfile.dev` reads `MESH_PEER1_URL`, `MESH_PEER2_URL` and `MESH_COORDINATOR_URL` from the environment, so no Caddyfile edit is needed.

### 4. Connectivity summary

- gateway → coordinator, gateway → every peer
- every peer → coordinator (signed placement callbacks)
- clients → gateway only; never expose peer app ports publicly

Do not reuse the throwaway secret in `docker-compose.mesh-dev.yml`. Do not share `APP_KEY` between instances; the mesh HMAC is intentionally independent of Laravel signed URLs. Each instance keeps its own SQLite database and chunk directory — expected in phase 1.

### Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `cp: cannot stat '.env.mesh.example'` | Still on `main` or at commit `1db4cc8` | Checkout `f72fe81` or newer as in step 0 |
| `-bash: node-id: No such file or directory` | Literal `<node-id>` in a URL | Use `peer1` / `peer2` |
| Peer health works only on localhost | `MESH_BIND=127.0.0.1:8090` | Set the host's private IP in `.env.mesh`, recreate the container |
| Peer cannot ACK (`503 acknowledged: false`, commit stays 409) | `MESH_COORDINATOR_URL` uses compose alias `coordinator` | Use an IP/hostname reachable from the peer |
| Chunk PUT returns 401 | `MESH_SECRET` differs between hosts | Compare secret hashes as in step 1 |
| Gateway health reports local peer | Gateway still routes to `http://peer1:80` | Apply override and stop local `peer1` |
| `APP_KEY` / `JWT_SECRET` still say `generate-a-unique-...` | Placeholder never replaced | Re-run `scripts/mesh-bootstrap.sh` after moving the old file aside |

### Can a peer register itself automatically?

Not in this phase. The coordinator currently trusts a static `MESH_NODES` map plus one shared secret. Proper enrollment needs per-node keys: the peer generates a keypair, an operator registers its public key and endpoint on the coordinator, and the coordinator signs chunk tokens per node while verifying ACKs with that node's key. That is a protocol change, not a config tweak. The bootstrap script is the supported shortcut for this phase; do not paste the shared secret into tickets or chat.

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
