#!/usr/bin/env bash
# PedestriRoute BDD Test Runner - Sprint 3
# Features: F7 (Rekomendasi Rute Pejalan Kaki)
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_PATH="$SCRIPT_DIR/.venv"

if [ ! -d "$VENV_PATH" ]; then
    echo "[INFO] Creating virtual environment at $VENV_PATH..."
    python3 -m venv "$VENV_PATH"
    echo "[INFO] Installing requirements..."
    "$VENV_PATH/bin/pip" install -r "$SCRIPT_DIR/requirements.txt"
fi

echo "==========================================================="
echo "   PedestriRoute BDD Suite - Sprint 3                      "
echo "   Features: F7 (Rekomendasi Rute Pejalan Kaki)            "
echo "==========================================================="

"$VENV_PATH/bin/behave" \
    "$SCRIPT_DIR/features/f7_rute_pejalan_kaki.feature" \
    "$@"
