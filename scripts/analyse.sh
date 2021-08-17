#!/bin/bash -e

# --------------------------------------------------------------------------
# Use PHPStan to analyse code
# --------------------------------------------------------------------------
./vendor/bin/phpstan analyse -c .phpstan/config.neon
