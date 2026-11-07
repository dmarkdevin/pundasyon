#!/usr/bin/env bash
# Proves make:module produces working code: generates two linked sample modules
# with every field type, runs style, static analysis and their own tests, then
# removes them again. Runs in CI; run it locally after changing stubs/module.
# Inside Sail: ./vendor/bin/sail bash scripts/check-module-generator.sh
set -euo pipefail
cd "$(dirname "$0")/.."

cleanup() {
    php artisan remove:module GeneratorCheckItem --force >/dev/null 2>&1 || true
    php artisan remove:module GeneratorCheckGroup --force >/dev/null 2>&1 || true
}
trap cleanup EXIT

php artisan make:module GeneratorCheckGroup --fields="name:string:unique,notes:text:nullable" --soft-deletes=false
php artisan make:module GeneratorCheckItem --fields="name:string,sku:string:unique,description:text:nullable,stock:integer,price:decimal,is_active:boolean,released_on:date:nullable,published_at:datetime:nullable,specs:json:nullable,generator_check_group_id:foreignId,user_id:foreignId:nullable"

vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/pest --filter='GeneratorCheck'

echo "Generated modules pass style, static analysis and their tests."
