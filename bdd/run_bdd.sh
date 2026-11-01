#!/usr/bin/env bash
# PedestriRoute BDD Test Runner (Cucumber / Python Behave)
# Sprint 1 (Weeks 1 & 2): F1, F2, F3, F5
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
echo "   PedestriRoute BDD Suite - Sprint 1 (Weeks 1 & 2)       "
echo "   Features: F1 (Login), F2 (Register), F3 (Lapor), F5 (Admin)"
echo "==========================================================="

"$VENV_PATH/bin/behave" "$SCRIPT_DIR/features" "$@"
