#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../../.."
cli="plugins/grupo_donato_gestao/Tests/cli.php"
token="$(date +%s%N)"
ids="$(php "$cli" rentalracesetup "$token" | tail -n 1)"
IFS=',' read -r activateId seriesId draftA draftB overrideId rid aid <<< "$ids"
out="$(mktemp -d)"
cleanup() { php "$cli" rentalracecleanup "$ids" >/dev/null 2>&1 || true; rm -rf "$out"; }
trap cleanup EXIT

race() {
  local label="$1" winner="$2"; shift 2
  local cmd_a=("$@")
  php "$cli" "${cmd_a[@]}" >"$out/a" 2>&1 & local a=$!
  php "$cli" "${cmd_a[@]}" >"$out/b" 2>&1 & local b=$!
  wait "$a"; wait "$b"
  local won lost
  won="$(grep -hc "$winner" "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
  lost="$(grep -hc 'conflict' "$out/a" "$out/b" | awk '{s+=$1} END {print s+0}')"
  test "$won" -eq 1 && test "$lost" -eq 1 || { echo "FAIL $label: won=$won lost=$lost"; cat "$out/a" "$out/b"; exit 1; }
  echo "  PASS $label ($winner=1 conflict=1)"
}

# duas locações na mesma série usam alvos distintos (draftA / draftB)
php "$cli" rentallink "$draftA" "$seriesId" >"$out/la" 2>&1 & la=$!
php "$cli" rentallink "$draftB" "$seriesId" >"$out/lb" 2>&1 & lb=$!
wait "$la"; wait "$lb"
linkwon="$(grep -hc 'linked' "$out/la" "$out/lb" | awk '{s+=$1} END {print s+0}')"
linklost="$(grep -hc 'conflict' "$out/la" "$out/lb" | awk '{s+=$1} END {print s+0}')"
test "$linkwon" -eq 1 && test "$linklost" -eq 1 || { echo "FAIL link race: won=$linkwon lost=$linklost"; cat "$out/la" "$out/lb"; exit 1; }
echo "  PASS duas locações na mesma série (linked=1 conflict=1)"

race "duas ativações simultâneas" "activated" rentalactivate "$activateId" "1"
race "dois overrides concorrentes" "repriced" rentaloverride "$overrideId" "1" "90.00"
race "criação integrada concorrente no mesmo recurso" "created" rentalcreate "$rid" "$aid" "2099-06-20T14:00" "2099-06-20T15:00"

inspection="$(php "$cli" rentalraceinspect "$activateId,$seriesId,$overrideId,$rid" | tail -n 1)"
echo "$inspection"
echo "RESULT: PASS - locação comercial concorrente sem dupla ocupação, vínculo duplicado ou overwrite silencioso"
