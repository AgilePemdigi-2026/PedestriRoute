#!/usr/bin/env bash
# PedestriRoute BDD Test Runner (Cucumber / Python Behave)
# Full acceptance suite: F1–F9
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
echo "   PedestriRoute BDD Suite - Full Acceptance (F1–F9)       "
echo "   Login, Register, Lapor, CV, Validasi, Heatmap, Rute,    "
echo "   Status Perbaikan                                        "
echo "==========================================================="

"$VENV_PATH/bin/behave" "$SCRIPT_DIR/features" "$@"
