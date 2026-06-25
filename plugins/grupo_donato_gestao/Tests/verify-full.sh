#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
PLUGIN="$ROOT/plugins/grupo_donato_gestao"
LOG_DIR="$ROOT/writable/logs"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cd "$ROOT"

step() {
  local name="$1"; shift
  echo "[FULL] $name"
  "$@"
  echo "  PASS"
}

if [ -d "$LOG_DIR" ]; then
  find "$LOG_DIR" -maxdepth 1 -type f -print0 | while IFS= read -r -d '' file; do
    printf '%s\t%s\n' "$file" "$(wc -l < "$file")" >> "$TMP/log-counts"
  done
fi

step "verify-fast" bash "$PLUGIN/Tests/verify-fast.sh"
step "installation" php "$PLUGIN/Tests/cli.php" install
step "installation idempotency" php "$PLUGIN/Tests/cli.php" install
step "self-test" php "$PLUGIN/Tests/cli.php" selftest
step "sequence and temporal concurrency" bash "$PLUGIN/Tests/concurrency.sh"
step "booking concurrency" bash "$PLUGIN/Tests/booking_concurrency.sh"
step "booking series concurrency" bash "$PLUGIN/Tests/series_concurrency.sh"
step "court rental concurrency" bash "$PLUGIN/Tests/court_rental_concurrency.sh"
step "uninstall preservation" php "$PLUGIN/Tests/cli.php" uninstallcheck

step "operacional module (autoload, rotas, views, 9 tabelas grupo_donato_*)" php "$PLUGIN/Tests/cli.php" operacional-check

integrity=""
if [ -d "$ROOT/writable/backups" ]; then
  while IFS= read -r candidate; do
    if [ -f "$candidate/integrity/rise-app.csv" ] && [ -f "$candidate/integrity/rise-system.csv" ]; then integrity="$candidate/integrity"; break; fi
  done < <(find "$ROOT/writable/backups" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' 2>/dev/null | sort -nr | cut -d' ' -f2-)
fi
if [ -n "$integrity" ]; then
  echo "[FULL] Rise core integrity"
  php -r '
  foreach([["app",$argv[1]],["system",$argv[2]]] as [$name,$csv]){
    $base=$argv[3]."/".$name;$fh=fopen($csv,"r");$header=fgetcsv($fh);$header[0]=preg_replace("/^\\xEF\\xBB\\xBF/","",$header[0]);$map=array_flip($header);$expected=[];
    while(($row=fgetcsv($fh))!==false){$rel=str_replace("\\\\","/",$row[$map["Path"]]);$expected[strtolower($rel)]=$row[$map["SHA256"]];$path=$base."/".$rel;if(!is_file($path)||hash_file("sha256",$path)!==strtolower($row[$map["SHA256"]])){fwrite(STDERR,"Core mismatch: $name/$rel\n");exit(1);}}
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));foreach($it as $file){if($file->isFile()){$rel=strtolower(str_replace("\\\\","/",substr($file->getPathname(),strlen($base)+1)));if(!isset($expected[$rel])){fwrite(STDERR,"Unexpected core file: $name/$rel\n");exit(1);}}}
  }
  ' "$integrity/rise-app.csv" "$integrity/rise-system.csv" "$ROOT"
  echo "  PASS"
else
  echo "[FULL] Rise core integrity"
  echo "  SKIP no per-file baseline manifest found"
fi

mysqlcheck_bin="${GD_MYSQLCHECK_EXE:-}"
if [ -z "$mysqlcheck_bin" ] && command -v mysqlcheck >/dev/null 2>&1; then mysqlcheck_bin="$(command -v mysqlcheck)"; fi
if [ -n "$mysqlcheck_bin" ] && [ -z "${GD_SKIP_DB_CHECK:-}" ]; then
  echo "[FULL] database CHECK TABLE"
  db_name="${GD_DB_NAME:-rise_crm}"; db_user="${GD_DB_USER:-root}"; args=(--check --silent "--user=$db_user")
  if [ -n "${GD_DB_PASSWORD:-}" ]; then args+=("--password=$GD_DB_PASSWORD"); fi
  "$mysqlcheck_bin" "${args[@]}" "$db_name"
  echo "  PASS"
else
  echo "[FULL] database CHECK TABLE"
  echo "  SKIP mysqlcheck unavailable or GD_SKIP_DB_CHECK set"
fi

echo "[FULL] New relevant log errors"
if [ -f "$TMP/log-counts" ]; then
  while IFS=$'\t' read -r file old_count; do
    if [ -f "$file" ] && tail -n "+$((old_count + 1))" "$file" | grep -Ei '(CRITICAL|ERROR).*(GD|grupo_donato|booking)' >> "$TMP/log-errors"; then :; fi
  done < "$TMP/log-counts"
fi
if [ -s "$TMP/log-errors" ]; then cat "$TMP/log-errors" >&2; exit 1; fi
echo "  PASS"
echo "VERIFY-FULL: PASS"
