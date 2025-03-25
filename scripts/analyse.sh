#!/bin/bash -e

# --------------------------------------------------------------------------
# Use PHPStan to analyse code
# --------------------------------------------------------------------------
./vendor/bin/phpstan analyse --memory-limit=-1 -c .phpstan/config.neon
