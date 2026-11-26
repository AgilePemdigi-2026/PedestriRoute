FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Coolify/source archives may contain LFS pointers instead of ONNX binaries.
ARG CV_LFS_URL=https://github.com/AgilePemdigi-2026/PedestriRoute.git/info/lfs
RUN python resolve_lfs_models.py --lfs-url "$CV_LFS_URL"

ENV PYTHONUNBUFFERED=1

EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --start-period=120s --retries=5 \
    CMD python -c "import urllib.request, json, sys; d = json.load(urllib.request.urlopen('http://localhost:8000/health')); sys.exit(0 if d.get('model_ready') else 1)"

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
