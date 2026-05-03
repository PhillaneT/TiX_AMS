#!/bin/bash
set -e

cd ams

composer install --no-interaction --no-progress --prefer-dist

php artisan migrate --force --no-interaction
