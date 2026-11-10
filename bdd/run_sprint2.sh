#!/usr/bin/env bash
# PedestriRoute BDD Test Runner - Sprint 2
# Features: F4 (Computer Vision), F6 (Heatmap), F9 (Status Perbaikan)
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
echo "   PedestriRoute BDD Suite - Sprint 2                      "
echo "   Features: F4 (Computer Vision), F6 (Heatmap), F9 (Perbaikan)"
echo "==========================================================="

"$VENV_PATH/bin/behave" \
    "$SCRIPT_DIR/features/f4_computer_vision.feature" \
    "$SCRIPT_DIR/features/f6_heatmap.feature" \
    "$SCRIPT_DIR/features/f9_status_perbaikan.feature" \
    "$@"
