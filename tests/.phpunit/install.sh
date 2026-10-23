#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "$0")" && pwd)"
while read -r version checksum filename; do
  target="$script_dir/$filename"
  if [[ ! -f "$target" ]]; then
    temporary="$(mktemp "$script_dir/.download.XXXXXX")"
    trap 'rm -f "$temporary"' EXIT
    curl --fail --location --silent --show-error "https://phar.phpunit.de/phpunit-$version.phar" -o "$temporary"
    actual="$(shasum -a 256 "$temporary" | cut -d ' ' -f 1)"
    [[ "$actual" == "$checksum" ]] || { echo "Checksum mismatch for PHPUnit $version" >&2; exit 1; }
    mv "$temporary" "$target"
  fi
  actual="$(shasum -a 256 "$target" | cut -d ' ' -f 1)"
  [[ "$actual" == "$checksum" ]] || { echo "Checksum mismatch for $filename" >&2; exit 1; }
done < "$script_dir/versions.txt"
