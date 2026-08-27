#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
PLUGIN="$ROOT/plugins/grupo_donato_gestao"
cd "$ROOT"

db_config_value() {
  local key="$1"
  sed -nE "s/^[[:space:]]*'${key}'[[:space:]]*=>[[:space:]]*'([^']*)'.*/\\1/p" "$ROOT/app/Config/Database.php" | head -n 1
}

db_config_number() {
  local key="$1"
  sed -nE "s/^[[:space:]]*'${key}'[[:space:]]*=>[[:space:]]*([0-9]+).*/\\1/p" "$ROOT/app/Config/Database.php" | head -n 1
}

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
  db_name="${GD_DB_NAME:-$(db_config_value database)}"
  db_user="${GD_DB_USER:-$(db_config_value username)}"
  db_pass="${GD_DB_PASSWORD:-$(db_config_value password)}"
  db_host="${GD_DB_HOST:-$(db_config_value hostname)}"
  db_port="${GD_DB_PORT:-$(db_config_number port)}"
  db_prefix="${GD_DB_PREFIX:-$(db_config_value DBPrefix)}"
  db_port="${db_port:-3306}"
  mysql_args=(--batch --skip-column-names "--host=$db_host" "--port=$db_port" "--user=$db_user")
  sql="SELECT CONCAT((SELECT MAX(version) FROM \`${db_prefix}gd_schema_versions\` WHERE status='completed'),'|',(SELECT value FROM \`${db_prefix}gd_settings\` WHERE unit_id IS NULL AND \`key\`='schema_version' AND deleted=0 LIMIT 1));"
  if [ -n "$db_pass" ]; then
    db_state="$(printf '%s\n' "$sql" | MYSQL_PWD="$db_pass" "$mysql_bin" "${mysql_args[@]}" "$db_name")"
  else
    db_state="$(printf '%s\n' "$sql" | "$mysql_bin" "${mysql_args[@]}" "$db_name")"
  fi
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
grep -Fq '$routes->post("court-rentals/availability-options"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("booking-series"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("booking-series/preview"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("booking-series/update-this-and-future"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("school/students"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("school/classes/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("school/attendance/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("finance"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("finance/payments/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->post("finance/expenses/save"' "$PLUGIN/Config/Routes.php"
grep -Fq '$routes->get("foto_aluno/(:num)"' "$PLUGIN/Operacional/Config/Routes.php"
grep -Fq 'gd-rental-reverse-payment' "$PLUGIN/Controllers/Rental_finance.php"
grep -Fq 'gd-rental-reverse-payment' "$PLUGIN/Views/finance/rental_payments.php"
grep -Fq 'grupo_donato/finance/payments/reverse' "$PLUGIN/Views/finance/rental_payments.php"
grep -Eq 'group\("grupo_donato".*"filter"[[:space:]]*=>[[:space:]]*"csrf"' "$PLUGIN/Config/Routes.php"
echo "  PASS required routes, protected student photo route, rental payment reversal action and CSRF group"

echo "[FAST] Student photo implementation"
test -f "$PLUGIN/Services/StudentPhotoService.php"
test -f "$PLUGIN/Database/Schema/Versions/V050_add_operational_student_photo_path.php"
grep -Fq '$prefix . "grupo_donato_alunos"' "$PLUGIN/Database/Schema/Versions/V050_add_operational_student_photo_path.php"
grep -Fq '"photo_path"' "$PLUGIN/Database/Schema/Versions/V050_add_operational_student_photo_path.php"
grep -Fq 'form_open_multipart' "$PLUGIN/Operacional/Views/modal_aluno.php"
grep -Fq 'name="student_photo"' "$PLUGIN/Operacional/Views/modal_aluno.php"
echo "  PASS service, V050 migration and multipart form"

echo "[FAST] Language catalog"
language="$PLUGIN/Language/portuguese/default_lang.php"
duplicates="$(grep -oE '"gd_[A-Za-z0-9_]+"[[:space:]]*=>' "$language" | sed -E 's/^"([^"]+)".*/\1/' | sort | uniq -d)"
test -z "$duplicates"
for key in gd_app_title gd_menu_calendar gd_menu_bookings gd_booking_conflict gd_menu_booking_series gd_booking_series_not_found gd_school_students gd_school_classes gd_school_attendance gd_finance_overview gd_finance_receivables gd_finance_cash gd_menu_rentals gd_menu_rental_agenda gd_menu_rental_bookings gd_menu_rental_series gd_menu_rental_single gd_menu_rental_monthly gd_menu_rental_finance gd_menu_rental_charges gd_calendar_content_free_slots gd_calendar_court_filter_help gd_all_courts gd_available_times gd_available_courts_for_time; do
  grep -Fq "\"$key\" =>" "$language"
done
grep -Fq 'types.push("free_slot")' "$PLUGIN/Views/calendar/index.php"
grep -Fq 'class="gd-calendar-resource"' "$PLUGIN/Views/calendar/index.php"
if grep -F 'class="gd-calendar-resource"' "$PLUGIN/Views/calendar/index.php" | grep -Fq ' checked'; then
  echo "  FAIL calendar courts must not be selected by default"
  exit 1
fi
grep -Fq 'plugins: [gdCalendarTimeZonePlugin]' "$PLUGIN/Views/calendar/index.php"
grep -Fq "prev: '\\u2039'" "$PLUGIN/Views/calendar/index.php"
grep -Fq "next: '\\u203a'" "$PLUGIN/Views/calendar/index.php"
grep -Fq 'slotMinTime: "04:00:00"' "$PLUGIN/Views/calendar/index.php"
grep -Fq 'slotMaxTime: "24:00:00"' "$PLUGIN/Views/calendar/index.php"
grep -Fq '.gd-rentals-shell .gd-page-header' "$PLUGIN/Views/components/rentals_styles.php"
grep -Fq 'court-rentals/availability-options' "$PLUGIN/Views/court_rentals/rental_modal.php"
if grep -R -Fq 'components\\rentals_nav' "$PLUGIN/Views" --exclude='rentals_nav.php'; then
  echo "  FAIL top rental navigation is still rendered"
  exit 1
fi
key_count="$(grep -oE '"gd_[A-Za-z0-9_]+"[[:space:]]*=>' "$language" | wc -l | tr -d ' ')"
echo "  PASS $key_count unique gd_* keys"

echo "[FAST] Focused tests"
php "$PLUGIN/Tests/cli.php" student-photo-selftest
echo "VERIFY-FAST: PASS"
