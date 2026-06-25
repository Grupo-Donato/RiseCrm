#!/usr/bin/env bash
# Teste de concorrência do SequenceService: dois processos em paralelo grabbing
# números da MESMA sequência não podem produzir duplicatas (SELECT ... FOR UPDATE).
set -e
cd "$(dirname "$0")/../../.."
OUT="writable"
TYPE="conc_$(date +%s)_$$"
RESOURCE_ID=""
rm -f "$OUT/seqA.txt" "$OUT/seqB.txt"

php plugins/grupo_donato_gestao/Tests/cli.php seqgrab 50 "$TYPE" > "$OUT/seqA.txt" &
PID_A=$!
php plugins/grupo_donato_gestao/Tests/cli.php seqgrab 50 "$TYPE" > "$OUT/seqB.txt" &
PID_B=$!
wait $PID_A
wait $PID_B

A=$(grep -cE '^[0-9]+$' "$OUT/seqA.txt" || true)
B=$(grep -cE '^[0-9]+$' "$OUT/seqB.txt" || true)
ALL=$(cat "$OUT/seqA.txt" "$OUT/seqB.txt" | grep -E '^[0-9]+$' | sort -n)
ALL_COUNT=$(echo "$ALL" | grep -cE '^[0-9]+$')
UNIQ_COUNT=$(echo "$ALL" | sort -nu | grep -cE '^[0-9]+$')

echo "A=$A B=$B total=$ALL_COUNT distinct=$UNIQ_COUNT"
if [ "$ALL_COUNT" -gt 0 ] && [ "$ALL_COUNT" = "$UNIQ_COUNT" ]; then
echo "RESULT: PASS - nenhuma duplicata entre processos paralelos"
else
  echo "RESULT: FAIL - duplicatas:"
  echo "$ALL" | uniq -d
  php plugins/grupo_donato_gestao/Tests/cli.php seqcleanup "$TYPE"
  rm -f "$OUT/seqA.txt" "$OUT/seqB.txt"
  exit 1
fi
php plugins/grupo_donato_gestao/Tests/cli.php seqcleanup "$TYPE"
RESOURCE_ID=$(php plugins/grupo_donato_gestao/Tests/cli.php temporalsetup "$TYPE" | tail -n 1)
php plugins/grupo_donato_gestao/Tests/cli.php temporalwrite "$RESOURCE_ID" > "$OUT/temporalA.txt" &
PID_TA=$!
php plugins/grupo_donato_gestao/Tests/cli.php temporalwrite "$RESOURCE_ID" > "$OUT/temporalB.txt" &
PID_TB=$!
wait $PID_TA
wait $PID_TB
SAVED=$(cat "$OUT/temporalA.txt" "$OUT/temporalB.txt" | grep -c '^saved$' || true)
DUPLICATE=$(cat "$OUT/temporalA.txt" "$OUT/temporalB.txt" | grep -c '^duplicate$' || true)
echo "temporal saved=$SAVED duplicate=$DUPLICATE"
php plugins/grupo_donato_gestao/Tests/cli.php temporalcleanup "$RESOURCE_ID"
if [ "$SAVED" -ne 1 ] || [ "$DUPLICATE" -ne 1 ]; then exit 1; fi
echo "RESULT: PASS - bloqueio temporal concorrente serializado"
rm -f "$OUT/seqA.txt" "$OUT/seqB.txt"
rm -f "$OUT/temporalA.txt" "$OUT/temporalB.txt"
