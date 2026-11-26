"""Resolve ONNX Git LFS pointers before packaging the deployment image."""

import argparse
import hashlib
import json
from pathlib import Path
import re
import urllib.request


DEFAULT_LFS_URL = "https://github.com/AgilePemdigi-2026/PedestriRoute.git/info/lfs"


def resolve_models(models_dir: Path, lfs_url: str):
    models = sorted(models_dir.glob("*.onnx"))
    if not models:
        raise RuntimeError(f"No ONNX models found in {models_dir}")

    pointers = []
    for model in models:
        with model.open("rb") as source:
            header = source.read(1024)
        if not header.startswith(b"version https://git-lfs.github.com/spec/v1"):
            print(f"Model already materialized: {model.name}", flush=True)
            continue
        oid = re.search(rb"^oid sha256:([0-9a-f]{64})$", header, re.MULTILINE)
        size = re.search(rb"^size ([0-9]+)$", header, re.MULTILINE)
        if not oid or not size:
            raise RuntimeError(f"Invalid Git LFS pointer: {model}")
        pointers.append((model, oid[1].decode("ascii"), int(size[1])))

    if not pointers:
        return

    # Ask for the exact objects from the checkout, independent of branch changes.
    request = urllib.request.Request(
        f"{lfs_url.rstrip('/')}/objects/batch",
        data=json.dumps({
            "operation": "download",
            "transfers": ["basic"],
            "objects": [{"oid": oid, "size": size} for _, oid, size in pointers],
        }).encode("utf-8"),
        headers={
            "Accept": "application/vnd.git-lfs+json",
            "Content-Type": "application/vnd.git-lfs+json",
        },
    )
    with urllib.request.urlopen(request, timeout=60) as response:
        objects = {obj["oid"]: obj for obj in json.load(response)["objects"]}

    for model, oid, expected_size in pointers:
        obj = objects.get(oid, {})
        action = obj.get("actions", {}).get("download")
        if not action:
            raise RuntimeError(
                f"Git LFS download unavailable for {model.name}: "
                f"{obj.get('error', 'object missing from batch response')}. "
                "Ensure the LFS objects have been pushed and are accessible."
            )

        print(f"Downloading Git LFS model: {model.name} ({expected_size} bytes)", flush=True)
        temporary = model.with_suffix(".onnx.download")
        try:
            digest = hashlib.sha256()
            downloaded = 0
            request = urllib.request.Request(action["href"], headers=action.get("header", {}))
            with urllib.request.urlopen(request, timeout=120) as response, temporary.open("wb") as target:
                while chunk := response.read(1024 * 1024):
                    downloaded += len(chunk)
                    if downloaded > expected_size:
                        raise RuntimeError(f"Git LFS size mismatch for {model.name}")
                    digest.update(chunk)
                    target.write(chunk)
            if downloaded != expected_size or digest.hexdigest() != oid:
                raise RuntimeError(f"Git LFS size/SHA-256 mismatch for {model.name}")
            temporary.replace(model)
        finally:
            temporary.unlink(missing_ok=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--models-dir", type=Path, default=Path("models"))
    parser.add_argument("--lfs-url", default=DEFAULT_LFS_URL)
    args = parser.parse_args()
    try:
        resolve_models(args.models_dir, args.lfs_url)
    except Exception as exc:
        raise SystemExit(
            f"Model preparation failed: {exc}\n"
            "For private repositories, run authenticated `git lfs pull` before the Docker build."
        ) from exc
