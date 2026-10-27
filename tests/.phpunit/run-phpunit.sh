#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "$0")" && pwd)"
repo_root="$(cd "$script_dir/../.." && pwd)"
php_bin="${PHP_BINARY:-/Users/tomjaeger/Library/Application Support/Herd/bin/php83}"
expected="6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7"
actual="$(shasum -a 256 "$script_dir/phpunit-9.phar" | cut -d ' ' -f 1)"
if [[ "$actual" != "$expected" ]]; then
  echo 'PHPUnit 9.6.38 is missing or has an unexpected SHA-256.' >&2
  exit 1
fi
cd "$repo_root"
exec "$php_bin" -d error_reporting=-1 -d display_errors=stderr "$script_dir/phpunit-9.phar" "$@"
