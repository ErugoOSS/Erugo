# Chunked mesh storage — Phase 1 spec

Status: spec only. Branch: `spec/chunk-mesh-storage`. No application code has been changed and no live environment has been touched.

## 1. Problem and goal

Erugo currently stores uploads in one instance's local `storage/app/uploads`, moves them into one local `storage/app/shares` tree, and tracks everything in one SQLite database. Uploads use `tus-js-client` with 20 MB chunks, but `tusd` assembles those chunks into a single local file; the "chunks" are only transport chunks, not distributable storage units. A lost instance loses its shares, large transfers use one stream, and the existing backup only covers the SQLite database.

Phase 1 goal: shard each upload across several private instances for parallel transfer, make the file downloadable as soon as its chunks are durable, then replicate in the background until every instance holds a verified full copy. Data-plane failover is in this phase; application-level failover is not.

## 2. Decisions confirmed with the operator

| Decision | Choice |
|---|---|
| Eventual parity | Full copy on every instance after background replication |
| During upload | File exists as shards; download must work before replication finishes |
| Public surface | One public URL; node-specific paths routed to private peers |
| Failover | Staged: data first, full app failover later |
| Existing upload path | Keep tus as fallback; add a separate sharded path |
| Download assembly | Browser fast path plus coordinator-side fallback |
| Mesh size | Configurable; initial dev harness 2-3 nodes |
| Change safety | New dev branch only; nothing touches a live environment |

## 3. Current-state findings (why this shape)

- `resources/js/api.js` (`uploadFileWithTus`) uses 20 MB tus chunks via `/files/`; `docker/alpine/Caddyfile` proxies `/files/*` to local `tusd`, and `docker/alpine/supervisord.conf` runs tusd with `storage/app/uploads`.
- `TusdHooksController` creates `UploadSession` and `File` rows on hooks; `UploadsController::createShareFromUploads` copies the assembled temp file into `storage/app/shares/<user>/<share>`, then `CreateShareZip` builds a local ZIP for multi-file shares and sets `status=ready`.
- `SharesController` checks password, expiry, `download_count`, and access before serving a local file or the local ZIP. CLI clients are detected in `routes/web.php` and served directly.
- The `chunk_uploads` table and `ChunkUpload` model exist but are unused; tusd assembles files itself.
- Each container has its own SQLite DB (`config/database.php`) and database queue; no state is shared between instances.
- tusd locks are per-process/file. Upstream docs require shared storage plus sticky sessions, or a distributed lock (not built into tusd; community Redis locker remains an open PR). Plain tus therefore cannot be load-balanced across independent nodes for this design.
- `php.ini` already allows 4 GB bodies, so chunk size is a protocol/config choice rather than a PHP limit change.
- `StatsController::getStorageStats` derives disk usage from local paths; mesh accounting must be DB-backed.
- Laravel temporary signed URLs are bound to `APP_KEY`; sharing that key with storage peers would widen trust unnecessarily. Node tokens need their own HMAC key.

## 4. Phase 1 architecture

### 4.1 Roles

- **Coordinator** — the existing public Erugo instance. Owns users, shares, manifests, authorization, download accounting, replication scheduling, and tombstones. Its SQLite DB is the single metadata writer in phase 1.
- **Storage peers** — private instances reachable only through the public gateway. They store content-addressed chunks, verify hashes on write and read, answer signed chunk GET/PUT, and apply deletion commands. They do not make authorization decisions.
- **Public gateway** — one hostname. Adds `handle /n/{node}/...` routes to Caddy **before** the `/files/*` and Laravel handlers, proxying each to the corresponding private peer. Peers are never publicly reachable by their own address.

### 4.2 Tokens and trust

- Dedicated mesh secret per deployment, distinct from `APP_KEY` and `STUDIO`-style app secrets.
- HMAC-SHA256 over a canonical string: HTTP method, path, expiry timestamp, object id, chunk index, expected SHA-256, nonce. Query parameters are part of the signed payload; no unsigned parameters are trusted.
- Short TTL (minutes). Nodes verify signature, expiry, and nonce shape; they do not query the coordinator per request. Authorization happens when the coordinator issues the token.
- Tokens are scoped to one operation (upload chunk, fetch chunk, delete object). Issued tokens remain valid until expiry, so TTL must be short and the client must request a fresh manifest when it expires.
- Failed hash verification is a hard error, never a fallback to trusting client metadata.

### 4.3 Upload flow

1. Authenticated client requests an upload intent (filename, size, content hash, client-computed chunk hashes).
2. Coordinator creates an upload intent and a plan: chunk size, chunk count, and a placement set of at least two nodes per chunk (`ERUGO_MESH_WRITE_COPIES`, minimum 2 in phase 1).
3. Client sends chunk PUTs in parallel through `/n/<node>/chunks/<intent>/<index>` with signed tokens.
4. Each peer verifies the chunk hash, writes to a temporary content-addressed path, and ACKs with `(node, index, hash)`. Writes are idempotent on `(intent, index, hash)`.
5. Coordinator commits the manifest only when every chunk has the configured number of ACKs. Before that, the intent has a TTL and its chunks are garbage-collected if abandoned.
6. On commit, a `File` row is created/updated the same way the tus hook does today, so share creation continues to work. A failed or partially ACKed upload is retried or reported as failed, never marked available.

### 4.4 Download flow

1. Request hits the existing share endpoints. Password, expiry, access, and download-limit checks stay in the coordinator.
2. On an allowed download, `download_count` is incremented **once atomically**, before chunk tokens are issued. Retries of individual chunks do not increment it again.
3. Coordinator returns a signed manifest: chunk order, sizes, hashes, and node URLs/tokens.
4. Browser fast path fetches chunks in parallel from node paths, verifies each hash, assembles a Blob, and triggers the download. The client refreshes the manifest if tokens expire mid-transfer; a chunk can be re-fetched from another placement.
5. Coordinator fallback keeps existing `/shares/...` and `downloadFile` behavior for `curl`/`wget` and older clients: it fetches chunks from peers server-side and streams the assembled bytes. This preserves current routes and CLI compatibility.
6. Multi-file shares keep the `pending -> ready` ZIP flow; `CreateShareZip` is adapted to source chunks from peers. "Instantly accessible" applies to the committed single-file case; ZIP creation remains a short post-commit step and must be explicit about that limit.

### 4.5 Background replication and repair

- A replication job walks manifests, compares the `placements` table with the configured node list, and copies missing chunks to any node lacking them.
- Each copy is hash-verified at the destination before its placement is marked complete. Copies are idempotent; a node that already has the hash is skipped.
- A manifest has a `generation`. Replication carries the generation and refuses to write when the coordinator's generation has advanced (e.g. after deletion), preventing stale jobs from resurrecting a share.
- Health checks mark nodes up/down; placement avoids down nodes, and repair resumes when they return. Disk capacity is reported before placement; a full node is treated as unavailable.

### 4.6 Deletion, expiry, and retention

- `Share::cleanFiles`, expiry sweeps, and user-triggered deletes enqueue an idempotent tombstone carrying object id + generation.
- Nodes apply tombstones on receipt; if offline, the tombstone is retried/outboxed and applied on reconnect before any delayed replication.
- Orphan chunks from abandoned intents or superseded generations are garbage-collected on a TTL.
- Storage stats come from manifest/placement rows, not a local directory scan.

## 5. Data model additions (phase 1)

- `mesh_nodes`: id, public path segment, private endpoint, enabled/capacity/health fields, secret reference.
- `mesh_upload_intents`: intent id, user, file metadata, total hash, chunk size/count, status, expiry.
- `mesh_objects` / manifests: object id, share/file, size, chunk count, generation, status.
- `mesh_chunks`: object id, index, hash, size.
- `mesh_placements`: chunk id, node id, state, ACK timestamp.
- `mesh_replication_jobs` and `mesh_tombstones`: retry state and generation guards.
- The legacy `chunk_uploads` table stays untouched; it is not reused to avoid mixing two chunk vocabularies.

Metadata access goes through repository interfaces so a shared Postgres/other DB can replace SQLite in a later phase without rewriting the data plane.

## 6. Failure modes and mitigations

| Failure | Mitigation |
|---|---|
| Client disappears mid-upload | Intent TTL + orphan chunk GC |
| Node lost during upload | Two ACKs required before commit; reassign missing chunk placement |
| Forged or replayed chunk request | HMAC over method/path/expiry/ids/hash/nonce, short TTL, peer rejects mismatches |
| Hash mismatch | Reject write or re-fetch from another placement; never mark placement complete |
| Token expires mid-download | Client requests a fresh manifest; server fallback remains available |
| Download limit race | Single atomic increment at coordinator before token issuance |
| Node offline during delete | Tombstone outbox retried until acknowledged; generation guard blocks stale replication |
| Offline node returns with old chunks | Generation comparison + GC of superseded objects |
| Node full | Capacity-aware placement; node treated as unavailable |
| Multi-file ZIP stale after repair | ZIP generation tied to manifest generation; rebuild on change |
| Shared `APP_KEY` temptation | Dedicated mesh HMAC key; Laravel signed URLs not used for peers |

## 7. Success criteria and test plan (dev only)

Local harness with 2-3 containers, no live hosts:

1. Upload shards land concurrently on at least two nodes; each chunk hash verified server-side.
2. The share downloads successfully before background replication completes.
3. After replication, every node holds a byte-identical full copy, verified by hash.
4. Kill one node mid-upload and one after commit; downloads still succeed and placements are repaired when it returns.
5. Forged, expired, wrong-path, and wrong-hash chunk requests are rejected.
6. `download_count` increments once per download, not per chunk; limit and password throttling still hold.
7. Deleting a share while a node is offline removes its chunks when the node returns; delayed replication cannot resurrect it.
8. Existing tus upload, CLI download, multi-file ZIP flow, expiry sweep, and stats endpoints still pass tests.
9. Throughput is measured against a single-node baseline and recorded; no fixed multiplier is a pass criterion yet.

## 8. Not in this phase

- Erasure coding / Reed–Solomon parity (full copies are the agreed redundancy model).
- Shared Postgres, replicated sessions/queue, or full app failover to a standby public instance.
- Dynamic node discovery or gossip; node list is configured.
- WAN/geography-aware placement and bandwidth scheduling.
- Migration of pre-existing local shares into the mesh.
- UI redesign beyond the uploader/downloader hooks needed for sharded transfer.

## 9. Follow-ups to revisit after phase 1

- Metadata store portability and app failover design.
- Measured throughput target and cost model per node count.
- Whether cold full copies should be compacted later (a hybrid erasure tier).
- Peer enrollment / per-node identity (research below; design not yet confirmed).

## 10. Prior Art — peer enrollment and transport

Research date: 2026-11-25. No implementation decision has been confirmed yet.

| Candidate | Source | Verdict | Notes |
|---|---|---|---|
| Tailscale auth keys | https://tailscale.com/docs/features/access-control/auth-keys | extend | One-off and reusable keys with expiry; a one-off, expiring invite is the established pattern for headless enrollment. |
| NetBird setup keys | https://docs.netbird.io/manage/peers/register-machines-using-setup-keys | extend | One-off vs reusable, usage limits, expiry, auto-groups. Confirms one-time token + usage limit as the expected UX. Revoking a key does not remove already-enrolled peers — revoke the peer separately. |
| Lattice ADR-0003 | https://docs.alattice.io/adr/0003-peer-enrollment-approval-and-client-side-keygen | adopt / extend | Client-side key generation: the control plane stores only public keys. Lifecycle `pending → approved → revoked`, with re-registration under a different public key treated as a new device. This directly addresses the shared-secret problem. |
| libsodium Ed25519 detached signatures | https://www.php.net/manual/en/function.sodium-crypto-sign-detached.php | adopt | Verified `ext-sodium` is present in the Erugo image and `sodium_crypto_sign_keypair()` works. No composer dependency needed. |
| WireGuard | https://routerd.net/docs/adr/wireguard-peer-enrollment and https://github.com/florolf/autowg | compose / defer | Solves encrypted peer-to-peer transport and gives a key-based peer identity, but needs `wg` tooling (absent from the image), `NET_ADMIN`, endpoint management and NAT traversal. It does not replace application-layer chunk-token authorization. Best treated as a later transport layer, not the enrollment mechanism. |
| SPIFFE / SPIRE join tokens | https://spiffe.io/docs/latest/deploying/spire_agent/ | build / reject for now | Join tokens are short-lived bootstrap secrets, but full workload attestation (trust bundles, SVID rotation) is disproportionate to a dev-scale three-node test. |

Observation: the repeated pattern across mature systems is **one-time invite token + client-generated keypair + server stores only public keys**. That removes the shared `MESH_SECRET` from every host without adding WireGuard to the container.
