#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
php_bin="${PHP_BINARY:-/Users/tomjaeger/Library/Application Support/Herd/bin/php83}"
runtime="$("$php_bin" -r 'echo PHP_MAJOR_VERSION,PHP_MINOR_VERSION;')"
extension="/Applications/Herd.app/Contents/Resources/xdebug/xdebug-$runtime-arm64.so"
reports="$(mktemp -d "${TMPDIR:-/tmp}/low-list-coverage.XXXXXX")"
cd "$repo_root"
tests/.phpunit/install.sh
set +e
LOW_LIST_XDEBUG_EXTENSION="$extension" LOW_LIST_COVERAGE_DIRECTORY="$reports" LOW_LIST_COVERAGE_FILE="$reports/xdebug.json" XDEBUG_MODE=coverage "$php_bin" \
  -d "zend_extension=$extension" -d error_reporting=-1 -d display_errors=stderr \
  -d "auto_prepend_file=$repo_root/tests/coverage-prepend.php" \
  tests/.phpunit/phpunit-9.phar -c phpunit.xml --log-junit "$reports/junit.xml" > "$reports/phpunit.txt" 2>&1
result=$?
set -e
[[ -s "$reports/xdebug.json" && -s "$reports/junit.xml" ]] || { cat "$reports/phpunit.txt"; exit 1; }
"$php_bin" tests/coverage-report.php "$reports"
"$php_bin" tests/navigation-report.php "$reports"
python3 /Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon_method_coverage_report.py \
  --repo-root "$repo_root" --source-root low_list --include-magic --coverage-clover "$reports/clover.xml" --reuse-clover --json > "$reports/loose-methods.json"
python3 /Users/tomjaeger/.codex/skills/ee-addon-phpunit-coverage-writer/scripts/addon_method_coverage_report.py \
  --repo-root "$repo_root" --source-root tests/.coverage-source --include-magic --coverage-clover "$reports/clover.xml" --reuse-clover --json > "$reports/archive-methods.json"
cat "$reports/summary.txt"
echo "REPORTS=$reports"
exit "$result"
