# Releasing Erugo

Releases are built by GitHub Actions (`.github/workflows/release.yml`). Pushing a version tag builds the image for amd64 and arm64, pushes it to Docker Hub as `wardy784/erugo`, starts the amd64 image to check it boots and shows the right version, then opens a draft GitHub release.

## One-time setup

The Docker Hub token is kept in a GitHub environment called `release`, not in the repository's own secrets. Anyone with write access can push a workflow to a branch, and repository secrets are given to every workflow that runs in the repo. Environment secrets are only given to a job that names the environment, from a ref the environment allows, after a reviewer approves it.

1. **Docker Hub token.** On Docker Hub, go to Account settings > Personal access tokens > Generate new token. Give it a description such as `erugo-github-release`, an expiry, and the Read & Write scope (not Delete). Copy it; Docker Hub only shows it once. A personal access token works for every repository under the account, not just `wardy784/erugo`.
2. **Environment.** In the GitHub repo, go to Settings > Environments > New environment and call it `release`. Then:
   - Tick **Required reviewers** and add yourself. Leave **Prevent self-review** off if you're the only reviewer, or you won't be able to approve your own releases.
   - Under **Deployment branches and tags**, choose **Selected branches and tags**, then **Add deployment branch or tag rule**, set the type to **Tag** and the pattern to `v*`.
   - Under **Environment secrets**, add `DOCKERHUB_USERNAME` (`wardy784`) and `DOCKERHUB_TOKEN` (the token from step 1).
3. **Tag ruleset.** Go to Settings > Rules > Rulesets > New ruleset > New tag ruleset. Name it `release tags`, set enforcement to **Active**, add the **Repository admin** role under **Bypass list**, add the target `v*` under **Target tags**, and tick **Restrict creations**, **Restrict updates** and **Restrict deletions**. Only repo admins and org owners can then create or move a version tag.
4. **Repository secrets.** Make sure there's no `DOCKERHUB_USERNAME` or `DOCKERHUB_TOKEN` under Settings > Secrets and variables > Actions > Repository secrets. If there is, delete it.
5. **Roles.** Anyone with the Admin role on the repo, and any org owner, can change environments and rulesets, so give other maintainers Write or Maintain, not Admin.

## Cutting a release

1. Make sure `main` has everything you want and the Tests workflow is green.
2. Tag the commit on `main` and push the tag:

   ```sh
   git checkout main
   git pull
   git tag v0.3.0
   git push origin v0.3.0
   ```

3. Open the Release run in the Actions tab. It waits at **Build and push** with "Review deployments". Check the run is for the tag you pushed, then approve it. When it finishes, Docker Hub has `0.3.0`, `0.3`, `0`, `latest` and `sha-<commit>`.
4. Open the draft release on GitHub, write the notes and publish it.

The version shown in the app comes from the tag, without the leading `v`.

## Release candidates

Tag with a suffix after a hyphen, for example `v0.3.0-rc.1`. The workflow pushes only `0.3.0-rc.1` and `sha-<commit>`. It does not touch `latest`, `0.3` or `0`, so people on those tags are not moved onto the candidate. The draft GitHub release is marked as a pre-release.

## Re-running a release

If a run fails part way, open it in the Actions tab and use "Re-run failed jobs".

To build a tag again from scratch, go to Actions > Release > Run workflow, choose the tag under **Use workflow from** (switch the dropdown to Tags), and enter the same tag, for example `v0.3.0`. The tag must already exist. Runs started from a branch are refused by the release environment. You'll need to approve the run as usual. A plain release moves `latest`, `X.Y` and `X` to the new build, so only re-run the newest release this way. If a draft or published GitHub release already exists for the tag, it is left alone.

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
