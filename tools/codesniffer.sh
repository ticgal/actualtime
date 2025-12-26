#!/bin/bash

TOOL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

# if exist vendor/bin/phpcbf, use it
if [ -f "$TOOL_DIR/../vendor/bin/phpcbf" ]; then
    cd "$TOOL_DIR/.."
    vendor/bin/phpcbf
    exit 0
fi