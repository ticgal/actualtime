#!/bin/bash

SCRIPT_DIR=$(dirname "$(readlink -f "$0")")
PARENT_FOLDER_PATH=$(dirname "$SCRIPT_DIR")
PLUGINNAME=$(basename "$PARENT_FOLDER_PATH")

PUBLIC_RELEASE=0
while getopts ":p" opt; do
    case $opt in
        p)
            PUBLIC_RELEASE=1
            ;;
        \?)
            echo "Invalid option: -$OPTARG" >&2
            exit 1
            ;;
    esac
done
shift $((OPTIND - 1))

if [ ! "$#" -eq 1 ]; then
    echo "Usage $0 [-p] <release>"
    exit 1
fi

INIT_PWD=$PWD
if [ ! "$PARENT_FOLDER_PATH" = "$INIT_PWD" ]; then
    cd $PARENT_FOLDER_PATH
fi

# Check core file
if [ ! -f setup.php ]; then
    echo "setup.php not found"
    exit 1
fi

# Check if the version is in the setup.php file
RELEASE=$1
SEMVER_REGEX="^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)(\\-[0-9A-Za-z-]+(\\.[0-9A-Za-z-]+)*)?(\\+[0-9A-Za-z-]+(\\.[0-9A-Za-z-]+)*)?$"
if grep --quiet "'$RELEASE'" setup.php; then
    if [[ $RELEASE =~ $SEMVER_REGEX ]]; then
        echo "$RELEASE found in setup.php"
    else
        echo "Version $RELEASE does not match the semantic versioning format"
        exit 1
    fi
else
    echo "$RELEASE has not been found in setup.php"
    exit 1
fi

# Download dependencies if necessary
if [ -f $PARENT_FOLDER_PATH"/composer.json" ]; then
    INSTALL_COMPOSER=0
    MOVE_TO_PUBLIC=0

    if [ ! -d "$PARENT_FOLDER_PATH/vendor" ]; then
        if [ -d "$PARENT_FOLDER_PATH/public" ] && [ -d "$PARENT_FOLDER_PATH/public/vendor" ]; then
            if [ ! "$(ls -A "$PARENT_FOLDER_PATH/public/vendor")" ]; then
                INSTALL_COMPOSER=1
                MOVE_TO_PUBLIC=1
            fi
        else
            INSTALL_COMPOSER=1
        fi
    elif [ ! "$(ls -A "$PARENT_FOLDER_PATH/vendor")" ]; then
        INSTALL_COMPOSER=1
    fi

    if [ "$INSTALL_COMPOSER" = 1 ]; then
        VERIFICA_COMPOSER=$(which "composer")
        if [ -z $VERIFICA_COMPOSER ]; then
            echo "Composer is not installed"
            exit 1
        else
            echo "Downloading dependencies"
            composer install
            if [ "$MOVE_TO_PUBLIC" = 1 ]; then
                mv "$PARENT_FOLDER_PATH/vendor" "$PARENT_FOLDER_PATH/public"
            fi
        fi
    fi
fi

# Update locales if necessary
if [ -d "$PARENT_FOLDER_PATH/locales" ]; then
    if [ -f "$PARENT_FOLDER_PATH/locales/localazy.keys.json" ]; then
        read -p "Are translations up to date? [Y/n] " -n 1 -r
        echo "\n";
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            if [ -f "$PARENT_FOLDER_PATH/tools/extract_template.sh" ]; then
                echo "Extract locales"
                ./tools/extract_template.sh
            elif [ -f "$PARENT_FOLDER_PATH/tools/generate_locales.sh" ]; then
                echo "Generate locales"
                ./tools/generate_locales.sh
            fi
        fi
    fi
fi

# Clean code with PHP CS Fixer
if [ -f "$PARENT_FOLDER_PATH/tools/php-cs-fixer.sh" ]; then
    echo "Initiating PHP CS Fixer"
    chmod +x $PARENT_FOLDER_PATH/tools/php-cs-fixer.sh
    bash $PARENT_FOLDER_PATH/tools/php-cs-fixer.sh
fi

# Perform PHPStan analysis
if [ -f "$PARENT_FOLDER_PATH/tools/phpstan.sh" ]; then
    echo "Initiating PHPStan analysis"
    chmod +x $PARENT_FOLDER_PATH/tools/phpstan.sh
    bash $PARENT_FOLDER_PATH/tools/phpstan.sh
fi

# remove old tmp files
if [ -e /tmp/$PLUGINNAME ]; then
    echo "Delete existing temp directory"
    rm -rf /tmp/$PLUGINNAME
fi

echo "Copy to  /tmp directory"
git checkout-index -a -f --prefix=/tmp/$PLUGINNAME/

if [ -e vendor ]; then
    cp -R vendor/ /tmp/$PLUGINNAME/
fi

echo "Move to this directory"
cd /tmp/$PLUGINNAME

echo "Delete various scripts and directories"
# array of files and folders to delete
FILES_TO_DELETE=(
    # github files
    ".github"
    ".gitignore"
    ".keep"
    "ISSUE_TEMPLATE.md"
    "PULL_REQUEST_TEMPLATE.md"
    # development folders
    "tools"
    "tests"
    "phpunit"
    # CI/CD config files
    ".php-cs-fixer.php"
    ".phpcs.xml"
    ".twig_cs.dist.php"
    "phpstan.neon"
    "composer.lock"
    ".composer.hash"
    "phpunit.xml.dist"
    "locales/localazy*"
    "RoboFile.php"
    ".travis.yml"
    ".coveralls.yml"
    ".tx"
    # Marketplace files
    "$PLUGINNAME.xml"
    "screenshots"
    )

# loop through the array and delete each file
for file in "${FILES_TO_DELETE[@]}"; do
    if [ -e "$file" ]; then
        rm -rf "$file"
    fi
done

# if exist composer.json, use composer install --no-dev
if [ -f composer.json ]; then
    echo "Removing development dependencies"
    composer install --no-dev --quiet
fi

cd ..

if [ "$PUBLIC_RELEASE" = 1 ]; then
    echo "Creating public release"
    PACKAGE_NAME="glpi-$PLUGINNAME-$RELEASE"
else
    echo "Creating private release"
    PACKAGE_NAME="$PLUGINNAME-$RELEASE"
fi
tar cjf "$PACKAGE_NAME.tar.bz2" $PLUGINNAME

cd $INIT_PWD

rm -rf /tmp/$PLUGINNAME

echo "The Tarball is in the /tmp directory"
