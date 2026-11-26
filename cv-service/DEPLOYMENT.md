# Deploying the CV service with Git LFS

The five `models/*.onnx` files are stored in Git LFS. A source checkout can
contain small text pointers instead of the approximately 63 MB model binaries.
ONNX Runtime cannot load those pointers, so `/health` reports
`model_ready: false` and Compose blocks the dependent application services.

## Coolify

1. Deploy using `infra/docker-compose.yml` from this repository.
2. Keep `CV_MOCK=false` for real model inference.
3. Remove any model bind mount configured separately in Coolify, particularly
   `../cv-service/models:/app/models`. The image contains the models; a bind
   mount would hide them with files from the server checkout.
4. Rebuild and redeploy so Coolify uses the updated CV image and Compose file.

The Docker build resolves remaining LFS pointers through GitHub's LFS API and
verifies each downloaded object's size and SHA-256 against the checked-out
pointer. Already downloaded model files require no network requests. Missing
or inaccessible LFS objects fail the build with an explicit error rather than
producing a container without usable models. Startup has a 120-second health
check grace period to load the five models.

If the build reports an unavailable object, ensure the model objects are
uploaded from your development checkout:

```bash
git lfs push origin main
```

For a fork, override the Docker build argument `CV_LFS_URL` with its LFS endpoint
(`https://github.com/OWNER/REPO.git/info/lfs`). For a private repository, arrange
an authenticated `git lfs pull` in the source checkout before Docker builds it.
The automatic downloader uses unauthenticated access to the public repository.

## Local deployment

```bash
git lfs install
git lfs pull
docker compose -f infra/docker-compose.yml up -d --build
```

## Verify and diagnose

In Coolify, open the **cv-service logs** and its container terminal. Run:

```bash
python -c "import urllib.request; print(urllib.request.urlopen('http://localhost:8000/health').read().decode())"
```

Expect `model_ready: true`, `mock_mode: false`, five entries in
`specialists_loaded`, and an empty `model_load_errors` list. The default
`model_path` names an optional legacy model; it does not need to exist when the
specialist models are loaded.

For a local Compose deployment, inspect logs with:

```bash
docker compose -f infra/docker-compose.yml logs --tail=100 cv-service
```

If the container exits with code 137 or the host reports an OOM kill, it ran out
of memory while loading models. Increase the available memory and redeploy.
The top-level "dependency failed to start" message alone does not identify
the underlying model-loading or resource error; use the CV logs for that.
