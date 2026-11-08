#!/usr/bin/env bash
# PedestriRoute BDD Test Runner (Cucumber / Python Behave)
# Sprint 1: F1, F2, F3, F5
# Sprint 2: F4 (Computer Vision), F6 (Heatmap), F9 (Status Perbaikan)
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_PATH="$SCRIPT_DIR/.venv"

if [ ! -d "$VENV_PATH" ]; then
    echo "[INFO] Creating virtual environment at $VENV_PATH..."
    if command -v python3 >/dev/null 2>&1; then
        python3 -m venv "$VENV_PATH"
    else
        python -m venv "$VENV_PATH"
    fi
    echo "[INFO] Installing requirements..."
    if [ -x "$VENV_PATH/bin/pip" ]; then
        "$VENV_PATH/bin/pip" install -r "$SCRIPT_DIR/requirements.txt"
    else
        "$VENV_PATH/Scripts/pip" install -r "$SCRIPT_DIR/requirements.txt"
    fi
fi

# Support both Linux/macOS (bin) and Windows (Scripts) virtualenv layouts.
if [ -x "$VENV_PATH/bin/behave" ]; then
    BEHAVE="$VENV_PATH/bin/behave"
elif [ -x "$VENV_PATH/Scripts/behave" ]; then
    BEHAVE="$VENV_PATH/Scripts/behave"
else
    echo "[ERROR] Behave executable not found in $VENV_PATH"
    exit 1
fi

echo "==========================================================="
echo "   PedestriRoute BDD Suite - Sprint 1 & Sprint 2           "
echo "   Features: F1, F2, F3, F4, F5, F6, F9                    "
echo "==========================================================="

"$BEHAVE" "$SCRIPT_DIR/features" "$@"
