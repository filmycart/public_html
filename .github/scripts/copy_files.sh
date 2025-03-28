#!/usr/bin/env bash
# bash boilerplate
set -euo pipefail # strict mode
readonly SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
function l { # Log a message to the terminal.
    echo
    echo -e "[$SCRIPT_NAME] ${1:-}"
}

# File to copy from Notehub
OPEN_FOLDER=./notehub/api/openapi.yaml

# if the file exists in Notehub, copy it to Notehub-JS repo
if [ -f "$OPEN_FOLDER" ]; then
    echo "Copying $OPEN_FOLDER"
    cp -R ./notehub/api/openapi.yaml $DESTINATION_PATH
fi

echo "Files copied to $DESTINATION_PATH"