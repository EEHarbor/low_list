#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
reports="$(mktemp -d "${TMPDIR:-/tmp}/low-list-matrix.XXXXXX")"
cd "$repo_root"
tests/.phpunit/install.sh
for runtime in 74 80 81 82 83 84 85; do
  php_bin="/Users/tomjaeger/Library/Application Support/Herd/bin/php$runtime"
  "$php_bin" -v > "$reports/php$runtime-version.txt"
  set +e
  PHP_BINARY="$php_bin" tests/.phpunit/run-phpunit.sh -c phpunit.xml --log-junit "$reports/php$runtime.xml" > "$reports/php$runtime.txt" 2>&1
  result=$?
  set -e
  echo "$result" > "$reports/php$runtime.exit"
done
echo "REPORTS=$reports"
python3 tests/summarize-matrix.py "$reports"
