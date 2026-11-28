# Releasing Erugo

Releases are built by GitHub Actions (`.github/workflows/release.yml`). Pushing a version tag builds the image for amd64 and arm64, pushes it to Docker Hub as `wardy784/erugo`, starts the amd64 image to check it boots and shows the right version, then opens a draft GitHub release.

## One-time setup

Add two repository secrets under Settings > Secrets and variables > Actions:

- `DOCKERHUB_USERNAME`: the Docker Hub account that owns `wardy784/erugo`.
- `DOCKERHUB_TOKEN`: a Docker Hub personal access token with Read & Write scope. Create it under Account settings > Personal access tokens on Docker Hub.

## Cutting a release

1. Make sure `main` has everything you want and the Tests workflow is green.
2. Tag the commit on `main` and push the tag:

   ```sh
   git checkout main
   git pull
   git tag v0.3.0
   git push origin v0.3.0
   ```

3. Watch the Release workflow in the Actions tab. When it finishes, Docker Hub has `0.3.0`, `0.3`, `0`, `latest` and `sha-<commit>`.
4. Open the draft release on GitHub, write the notes and publish it.

The version shown in the app comes from the tag, without the leading `v`.

## Release candidates

Tag with a suffix after a hyphen, for example `v0.3.0-rc.1`. The workflow pushes only `0.3.0-rc.1` and `sha-<commit>`. It does not touch `latest`, `0.3` or `0`, so people on those tags are not moved onto the candidate. The draft GitHub release is marked as a pre-release.

## Re-running a release

If a run fails part way, open it in the Actions tab and use "Re-run failed jobs".

To build a tag again from scratch, go to Actions > Release > Run workflow and enter the tag, for example `v0.3.0`. The tag must already exist. A plain release moves `latest`, `X.Y` and `X` to the new build, so only re-run the newest release this way. If a draft or published GitHub release already exists for the tag, it is left alone.

## Manual fallback

If Actions is unavailable, build from the tag on a machine with Docker Buildx. `git archive` keeps the build to committed files only.

```sh
git fetch --tags
docker buildx create --name mybuilder --driver docker-container --bootstrap 2>/dev/null || true
docker login

git archive v0.3.0 | docker buildx build --builder mybuilder \
  --platform linux/amd64,linux/arm64 \
  -f docker/alpine/Dockerfile \
  --build-arg VERSION=0.3.0 \
  -t wardy784/erugo:0.3.0 \
  -t wardy784/erugo:0.3 \
  -t wardy784/erugo:0 \
  -t wardy784/erugo:latest \
  --push -
```

`VERSION` is the real version, the same as the tag without the `v`. For a release candidate, push only the exact version tag and leave out `0.3`, `0` and `latest`.
