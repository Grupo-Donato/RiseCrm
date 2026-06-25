#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
PLUGIN="$ROOT/plugins/grupo_donato_gestao"
cd "$ROOT"

echo "[FAST] PHP lint"
count=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  count=$((count + 1))
done < <(find "$PLUGIN" -type f -name '*.php' -print0)
echo "  PASS $count/$count"

echo "[FAST] Version, schema target and marker"
version="$(sed -nE 's/.*PLUGIN_VERSION[[:space:]]*=[[:space:]]*"([^"]+)".*/\1/p' "$PLUGIN/Config/Constants.php" | head -n 1)"
schema="$(sed -nE 's/.*SCHEMA_TARGET[[:space:]]*=[[:space:]]*"([0-9]{3})".*/\1/p' "$PLUGIN/Config/Constants.php" | head -n 1)"
metadata="$(sed -nE 's/^Version:[[:space:]]*(.+)$/\1/p' "$PLUGIN/index.php" | head -n 1 | tr -d '\r')"
test -n "$version" && test -n "$schema" && test -n "$metadata"
test "$version" = "$metadata"
test -f "$ROOT/writable/gd_schema_version.txt"
marker="$(tr -d '[:space:]' < "$ROOT/writable/gd_schema_version.txt")"
test "$schema" = "$marker"
mysql_bin="${GD_MYSQL_EXE:-}"
if [ -z "$mysql_bin" ] && command -v mysql >/dev/null 2>&1; then mysql_bin="$(command -v mysql)"; fi
if [ -z "$mysql_bin" ] && [ -x /c/xampp/mysql/bin/mysql.exe ]; then mysql_bin=/c/xampp/mysql/bin/mysql.exe; fi
if [ -n "$mysql_bin" ] && [ -z "${GD_SKIP_DB_CHECK:-}" ]; then
  db_name="${GD_DB_NAME:-rise_crm}"; db_user="${GD_DB_USER:-root}"; mysql_args=(--batch --skip-column-names "--user=$db_user")
  if [ -n "${GD_DB_PASSWORD:-}" ]; then mysql_args+=("--password=$GD_DB_PASSWORD"); fi
  sql="SELECT CONCAT((SELECT MAX(version) FROM \`rise_gd_schema_versions\` WHERE status='completed'),'|',(SELECT value FROM \`rise_gd_settings\` WHERE unit_id IS NULL AND \`key\`='schema_version' AND deleted=0 LIMIT 1));"
  db_state="$(printf '%s\n' "$sql" | "$mysql_bin" "${mysql_args[@]}" "$db_name")"
  test "$db_state" = "$marker|$marker"
  echo "  PASS version=$version schema=$marker database=$db_state"
else
  echo "  PASS version=$version schema=$marker (database query skipped)"
fi

echo "[FAST] Routes"
grep -Fq '$routes->get("bookings"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("bookings/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("bookings/check-availability"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("bookings/(:num)/confirm"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("calendar/events"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("booking-series"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("booking-series/preview"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("booking-series/update-this-and-future"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("school/students"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("school/classes/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("school/attendance/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("finance"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("finance/payments/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("finance/expenses/save"' "$PLUGIN/Config/Routes.php"
grep -Eq 'group\("grupo_donato".*"filter"[[:space:]]*=>[[:space:]]*"csrf"' "$PLUGIN/Config/Routes.php"
echo "  PASS required routes and CSRF group"

echo "[FAST] Language catalog"
language="$PLUGIN/Language/portuguese/default_lang.php"
duplicates="$(grep -oE '"gd_[A-Za-z0-9_]+"[[:space:]]*=>' "$language" | sed -E 's/^"([^"]+)".*/\1/' | sort | uniq -d)"
test -z "$duplicates"
for key in gd_app_title gd_menu_calendar gd_menu_bookings gd_booking_conflict gd_menu_booking_series gd_booking_series_not_found gd_school_students gd_school_classes gd_school_attendance gd_finance_overview gd_finance_receivables gd_finance_cash; do
  grep -Fq "\"$key\" =>" "$language"
done
key_count="$(grep -oE '"gd_[A-Za-z0-9_]+"[[:space:]]*=>' "$language" | wc -l | tr -d ' ')"
echo "  PASS $key_count unique gd_* keys"

echo "[FAST] Focused tests"
echo "  SKIP harness has no focused groups; full self-test belongs to verify-full"
echo "VERIFY-FAST: PASS"
