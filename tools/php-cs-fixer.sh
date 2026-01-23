#!/bin/bash

TOOL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
cd "$TOOL_DIR/.."

if [ ! -f ".php-cs-fixer.php" ]; then
    echo "Error: The configuration file doesn't exist in .php-cs-fixer.php"
    exit 1
fi

if [ ! -f "vendor/bin/php-cs-fixer" ]; then
    echo "Error: PHP CS Fixer not found in vendor/bin/php-cs-fixer"
    exit 1
fi

# vendor/bin/php-cs-fixer check --config=.php-cs-fixer.php to check problems
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --no-interaction