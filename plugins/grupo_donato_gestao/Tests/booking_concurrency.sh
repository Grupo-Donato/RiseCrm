#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."
token="$(date +%s%N)"

race() {
  local count="$1" reverse="$2" ids out
  ids="$(php plugins/grupo_donato_gestao/Tests/cli.php bookingsetup "$token" "$count" | tail -n 1)"
  out="$(mktemp -d)"
  trap 'php plugins/grupo_donato_gestao/Tests/cli.php bookingcleanup "$ids" >/dev/null 2>&1 || true; rm -rf "$out"' RETURN
  php plugins/grupo_donato_gestao/Tests/cli.php bookingwrite "$ids" 0 >"$out/a" & a=$!
  php plugins/grupo_donato_gestao/Tests/cli.php bookingwrite "$ids" "$reverse" >"$out/b" & b=$!
  wait "$a"; wait "$b"
  saved="$(grep -hxc saved "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
  conflict="$(grep -hxc conflict "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
  test "$saved" -eq 1 && test "$conflict" -eq 1
  echo "resources=$count reverse=$reverse saved=$saved conflict=$conflict"
  php plugins/grupo_donato_gestao/Tests/cli.php bookingcleanup "$ids" >/dev/null
  rm -rf "$out"
  trap - RETURN
}

race 1 0
token="$((token + 1))"
race 2 1
echo "RESULT: PASS - concorrência de reservas simples e multi-recurso serializada"
