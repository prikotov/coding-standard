#!/usr/bin/env bash
set -euo pipefail

format=text
output=
while (($#)); do
    case "$1" in
        --format=json) format=json ;;
        --output) output="$2"; shift ;;
        *) printf 'test-stats: unknown option: %s\n' "$1" >&2; exit 1 ;;
    esac
    shift
done

files=$(find tests -type f -name '*.php' -print 2>/dev/null | sort)
count=0
lines=0
while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    count=$((count + 1))
    file_lines=$(wc -l < "$file")
    lines=$((lines + file_lines))
done <<< "$files"
average=0
if ((count > 0)); then average=$(awk "BEGIN { printf \"%.2f\", $lines / $count }"); fi

if [[ "$format" == json ]]; then
    result=$(printf '{\n  "suites": [{"name":"Unit","path":"tests","files":%d,"lines":%d,"average_lines":%s}],\n  "total": {"files":%d,"lines":%d,"average_lines":%s}\n}\n' "$count" "$lines" "$average" "$count" "$lines" "$average")
    if [[ -n "$output" ]]; then mkdir -p "$(dirname "$output")"; printf '%s' "$result" > "$output"; else printf '%s' "$result"; fi
else
    printf 'Unit: %d files, %d lines, %s lines/file\n' "$count" "$lines" "$average"
    printf 'Total: %d files, %d lines, %s lines/file\n' "$count" "$lines" "$average"
fi
