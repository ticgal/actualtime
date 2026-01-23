#!/bin/bash

TOOL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PHPSTAN_DIR="$TOOL_DIR/phpstan/phpstan.phar"
CONFIG_FILE="$TOOL_DIR/phpstan/phpstan.neon"
AUTOLOAD_FILE="$TOOL_DIR/phpstan/autoload.php"

# if exist  vendor/bin/phpstan, use it
if [ -f "$TOOL_DIR/../vendor/bin/phpstan" ]; then
    cd "$TOOL_DIR/.."
    vendor/bin/phpstan analyze --ansi --memory-limit=2G --no-interaction
    exit 0
fi

# Verify folder
if [ ! -f "$PHPSTAN_DIR" ]; then
    echo "Error: PHPStan not finded in $PHPSTAN_DIR"
    exit 1
fi

# Verify file
if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: The configuration file doesn't exist in $CONFIG_FILE"
    exit 1
fi

# Clear cache
#./tools/phpstan/phpstan.phar clear-result-cache

# Execute PHPStan
$PHPSTAN_DIR analyse -c "$CONFIG_FILE" -a "$AUTOLOAD_FILE" $TOOL_DIR/..
