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

One **coordinator** serves Erugo's UI/API and owns users, shares, manifests and download accounting. Each enrolled **storage peer** has its own public HTTPS URL and holds chunks. The coordinator issues short-lived, host-bound Ed25519 tokens containing direct peer URLs; clients upload chunks straight to peers in parallel, so the coordinator is not in the data path. Peers never make authorization decisions: they verify the coordinator's signature and the SHA-256 of the bytes they receive, then sign an ACK that the coordinator verifies against that peer's registered public key. The coordinator commits the upload only when every chunk has the configured number of ACKs.

## Local three-instance gateway (recommended main gate)

The compose file starts three app containers and one Caddy gateway. It uses ports 18091-18094 so it does not collide with a normal Erugo dev stack. A single remote node uses 18090; neither range uses the already-occupied 8090.

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

## Multi-host test deployment (enrolled peers, direct data URLs)

One coordinator and two peers run on separate hosts. Enrolled peers use Ed25519 identities and direct HTTPS data URLs: the coordinator handles auth, manifests, ACKs and accounting; chunk bytes go **client → peer** without crossing the coordinator's uplink. `MESH_SECRET` is no longer copied between hosts — it exists only as a legacy fallback for the single-host dev harness.

### 0. Get the branch on every host

`main` does not contain these files. On each host:

```sh
cd ~/erugoshard/Erugo
git fetch origin feat/chunk-mesh-phase1
git checkout -B feat/chunk-mesh-phase1 origin/feat/chunk-mesh-phase1
git rev-parse --short HEAD   # must be fb5602e or newer
ls -la .env.mesh.example docker-compose.mesh-node.yml scripts/mesh-bootstrap.sh
```

### 1. Addresses and ports

| Thing | Value | Notes |
|---|---|---|
| Public coordinator | reuse `https://share.bastionmedia.co.za` if that is the existing Erugo site | No new coordinator hostname is required. `/api/mesh/public-key` and `/api/mesh/uploads` are served by the same app. |
| Public peer 1 / peer 2 | new hostnames, e.g. `https://peer1.bastionmedia.co.za` | Required for direct data URLs. Do not point both at the coordinator hostname, or the coordinator uplink becomes the bottleneck again. |
| Public port | 443 | TLS terminates at Cloudflare/an existing tunnel. |
| Peer app bind | `127.0.0.1:18090` by default | Container port 80 maps to this host port. A tunnel on the same host forwards to `http://localhost:18090`; loopback is safe and correct. |
| Local three-node harness | 18091-18094 | Separate from single-node deployments. |

Use a private IP or `0.0.0.0` for `MESH_BIND` only if a proxy on a **different** host must connect directly; firewall that port. Never expose 18090 publicly.

### 2. Create `.env.mesh` on each host

Do **not** copy `.env.mesh.example` by hand; it contains placeholders. Run:

```sh
sh scripts/mesh-bootstrap.sh
```

The script asks for:

- node id: `coordinator`, `peer1` or `peer2` (unique per host)
- the **public HTTPS URL of this host**, e.g. `https://peer1.example.com` — used as `APP_URL` and as this node's data URL
- the coordinator API URL reachable **from this host**, e.g. `https://studio.example.com`

It generates a unique `APP_KEY` and `JWT_SECRET` per host and a random legacy-only `MESH_SECRET`. Nothing has to be copied between hosts. Each peer needs public DNS and TLS for its data URL.

### 3. Start the node

```sh
sudo docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml up -d --build
sudo docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml ps
```

Health check locally (use the literal id, not `<node-id>`; angle brackets are shell redirection):

```sh
curl -sS http://127.0.0.1:18090/n/peer1/health
# expected: {"status":"ok","node":"peer1","mesh_enabled":true}
```

From that host, confirm the peer can reach the coordinator (needed for signed ACK callbacks):

```sh
curl -sS https://studio.example.com/api/health
```

### 4. Enroll the peer

On each peer host, generate a registration blob:

```sh
sudo docker compose --env-file .env.mesh -p erugo-mesh-node \
  -f docker-compose.mesh-node.yml exec app \
  php artisan mesh:peer-init peer1 https://peer1.example.com
```

The command fetches the coordinator public key from `/api/mesh/public-key`, creates a local Ed25519 keypair (private key stays in `storage/app/mesh/identity.json`, mode `600`), and prints a short-lived single-use blob. Copy that blob into **Settings → Mesh** on the coordinator and click **Approve peer**. The coordinator stores only the peer's public key and data URL. Revoke a peer from the same page if its key is compromised.

### 5. Data flow after enrollment

The upload token response now contains `upload_urls` with absolute URLs per node, for example `https://peer1.example.com/n/peer1/chunks/<intent>/<index>`. Tokens are `v2`, bound to scheme + host + path, and signed by the coordinator; peers verify them with the stored coordinator public key. ACKs are signed by the peer and verified against its registered public key.

The local Caddy gateway remains the UI/control-plane entry point and the legacy fallback for the three-container harness. Enrolled peers do not need gateway routes for chunk data.

### Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `cp: cannot stat '.env.mesh.example'` | Still on `main` or an old commit | Checkout `fb5602e` or newer as in step 0 |
| `-bash: node-id: No such file or directory` | Literal `<node-id>` in a URL | Use `peer1` / `peer2` |
| Peer health works only on localhost | `MESH_BIND` still loopback and gateway needs direct access | For the enrolled direct-URL design this is fine; clients use the public HTTPS URL, not this port |
| `peer-init` cannot fetch public key | `MESH_COORDINATOR_URL` unreachable or wrong scheme | Use the coordinator's public HTTPS URL reachable from the peer |
| Chunk PUT returns 401 | Node not enrolled, revoked, or blob was forged | Approve a fresh blob in Settings → Mesh |
| Commit stays 409 with missing indexes | Peer ACKs rejected — node revoked or key mismatch | Revoke and re-enroll the node with a fresh blob |
| `APP_KEY` / `JWT_SECRET` still say `generated-...` | Placeholder never replaced | Re-run `scripts/mesh-bootstrap.sh` after moving the old file aside |

### WireGuard?

Not in this slice. WireGuard between servers would still send bytes through the coordinator gateway unless clients also joined a VPN. Direct per-peer HTTPS URLs remove the coordinator from the data path without adding `NET_ADMIN` or `wg` tooling to the containers. Prior art and trade-offs are in `specs/chunk-mesh-storage.md`.

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

The response contains `tokens` and `upload_urls`, keyed by node and chunk index. Enrolled peers get absolute URLs such as `https://peer1.example.com/n/peer1/chunks/<intent>/0`; tokens are `v2`, single-use, short-lived and bound to that host. Call the endpoint again if an upload takes longer than the TTL or needs a retry.

5. PUT each chunk directly to two different peer URLs, using the token for that node and chunk:

```sh
curl -sS -X PUT https://peer1.example.com/n/peer1/chunks/<intent_id>/0 \
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
