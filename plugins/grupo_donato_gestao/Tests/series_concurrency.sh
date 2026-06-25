#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."
token="$(date +%s%N)"
ids="$(php plugins/grupo_donato_gestao/Tests/cli.php seriessetup "$token" | tail -n 1)"
series_id="${ids%%,*}"
out="$(mktemp -d)"
cleanup() {
  php plugins/grupo_donato_gestao/Tests/cli.php seriescleanup "$ids" >/dev/null 2>&1 || true
  rm -rf "$out"
}
trap cleanup EXIT
php plugins/grupo_donato_gestao/Tests/cli.php seriesgenerate "$series_id" >"$out/a" & a=$!
php plugins/grupo_donato_gestao/Tests/cli.php seriesgenerate "$series_id" >"$out/b" & b=$!
wait "$a"; wait "$b"
effective="$(grep -hxc 'created=3 idempotent=0' "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
idempotent="$(grep -hxc 'created=0 idempotent=3' "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
test "$effective" -eq 1 && test "$idempotent" -eq 1
inspection="$(php plugins/grupo_donato_gestao/Tests/cli.php seriesinspect "$series_id" | tail -n 1)"
echo "effective=$effective idempotent=$idempotent $inspection"
echo "RESULT: PASS - geração concorrente de série sem duplicidades"
