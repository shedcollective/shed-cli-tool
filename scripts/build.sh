#!/bin/bash -e

# --------------------------------------------------------------------------
# Colour Palette
# --------------------------------------------------------------------------
COMMENT='\033[0;33m'
ERROR='\033[31m'
NC='\033[0m'


# --------------------------------------------------------------------------
# Helpers
# --------------------------------------------------------------------------
commandNotInstalled()
{
    echo ""
    echo -e "${ERROR}ERROR: ${COMMENT}$1${NC} ${ERROR}is not installed.${NC}"
    echo ""
    echo -e "Homepage: ${COMMENT}$2${NC}"
    echo -e "Install using brew: ${COMMENT}$3${NC}"
    echo "" >&2
}


# --------------------------------------------------------------------------
# Test everything is available
# --------------------------------------------------------------------------
if ! [ -x "$(command -v composer)" ]; then
    commandNotInstalled composer "https://getcomposer.org" "brew install composer"
    exit 1
elif ! [ -x "$(command -v box)" ]; then
    commandNotInstalled box "https://github.com/humbug/box" "brew tap humbug/box && brew install box"
    exit 1
fi


# --------------------------------------------------------------------------
# Build
# --------------------------------------------------------------------------
composer --no-interaction --optimize-autoloader --no-dev install
box compile
