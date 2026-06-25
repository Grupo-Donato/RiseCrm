$ErrorActionPreference = "Stop"

$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$plugin = Join-Path $root "plugins\grupo_donato_gestao"
$php = (Get-Command php -ErrorAction Stop).Source

function Assert-True([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}

Write-Host "[FAST] PHP lint"
$phpFiles = @(Get-ChildItem -LiteralPath $plugin -Recurse -File -Filter "*.php")
foreach ($file in $phpFiles) {
    & $php -l $file.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Lint failed: $($file.FullName)" }
}
Write-Host "  PASS $($phpFiles.Count)/$($phpFiles.Count)"

Write-Host "[FAST] Version, schema target and marker"
$constants = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $plugin "Config\Constants.php")
$metadata = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $plugin "index.php")
$versionMatch = [regex]::Match($constants, 'PLUGIN_VERSION\s*=\s*"([^"]+)"')
$schemaMatch = [regex]::Match($constants, 'SCHEMA_TARGET\s*=\s*"([0-9]{3})"')
$metadataMatch = [regex]::Match($metadata, '(?m)^Version:\s*([^\r\n]+)')
Assert-True $versionMatch.Success "PLUGIN_VERSION not found."
Assert-True $schemaMatch.Success "SCHEMA_TARGET not found."
Assert-True $metadataMatch.Success "Plugin metadata version not found."
Assert-True ($versionMatch.Groups[1].Value -eq $metadataMatch.Groups[1].Value.Trim()) "Metadata and constant versions differ."
$markerPath = Join-Path $root "writable\gd_schema_version.txt"
Assert-True (Test-Path -LiteralPath $markerPath) "Schema marker is missing."
$marker = (Get-Content -Raw -LiteralPath $markerPath).Trim()
Assert-True ($marker -eq $schemaMatch.Groups[1].Value) "Schema target $($schemaMatch.Groups[1].Value) differs from marker $marker."
$mysql = $env:GD_MYSQL_EXE
if (-not $mysql) {
    $mysqlCommand = Get-Command mysql -ErrorAction SilentlyContinue
    if ($mysqlCommand) { $mysql = $mysqlCommand.Source }
    elseif (Test-Path "C:\xampp\mysql\bin\mysql.exe") { $mysql = "C:\xampp\mysql\bin\mysql.exe" }
}
if ($mysql -and -not $env:GD_SKIP_DB_CHECK) {
    $dbName = if ($env:GD_DB_NAME) { $env:GD_DB_NAME } else { "rise_crm" }
    $dbUser = if ($env:GD_DB_USER) { $env:GD_DB_USER } else { "root" }
    $arguments = @("--batch", "--skip-column-names", "--user=$dbUser")
    if ($env:GD_DB_PASSWORD) { $arguments += "--password=$($env:GD_DB_PASSWORD)" }
    $sql = "SELECT CONCAT((SELECT MAX(version) FROM ``rise_gd_schema_versions`` WHERE status='completed'),'|',(SELECT value FROM ``rise_gd_settings`` WHERE unit_id IS NULL AND ``key``='schema_version' AND deleted=0 LIMIT 1));"
    $dbState = (($sql | & $mysql @arguments $dbName) -join "").Trim()
    if ($LASTEXITCODE -ne 0) { throw "Database schema query failed." }
    Assert-True ($dbState -eq "$marker|$marker") "Database schema/setting differs from marker: $dbState vs $marker."
    Write-Host "  PASS version=$($versionMatch.Groups[1].Value) schema=$marker database=$dbState"
} else {
    Write-Host "  PASS version=$($versionMatch.Groups[1].Value) schema=$marker (database query skipped)"
}

Write-Host "[FAST] Routes"
$routes = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $plugin "Config\Routes.php")
$requiredRoutes = @(
    '$routes->get("bookings"',
    '$routes->post("bookings/save"',
    '$routes->post("bookings/check-availability"',
    '$routes->post("bookings/(:num)/confirm"',
    '$routes->get("calendar/events"'
    '$routes->get("booking-series"'
    '$routes->post("booking-series/preview"'
    '$routes->post("booking-series/update-this-and-future"'
    '$routes->get("school/students"'
    '$routes->post("school/classes/save"'
    '$routes->post("school/attendance/save"'
    '$routes->get("finance"'
    '$routes->post("finance/payments/save"'
    '$routes->post("finance/expenses/save"'
)
foreach ($route in $requiredRoutes) { Assert-True $routes.Contains($route) "Required route missing: $route" }
Assert-True ($routes -match 'group\("grupo_donato"[\s\S]*?"filter"\s*=>\s*"csrf"') "The plugin route group is not protected by CSRF."
Write-Host "  PASS required routes and CSRF group"

Write-Host "[FAST] Language catalog"
$languagePath = Join-Path $plugin "Language\portuguese\default_lang.php"
$language = Get-Content -Raw -Encoding UTF8 -LiteralPath $languagePath
$keys = [regex]::Matches($language, '"(gd_[a-zA-Z0-9_]+)"\s*=>') | ForEach-Object { $_.Groups[1].Value }
$duplicates = @($keys | Group-Object | Where-Object Count -gt 1)
Assert-True ($duplicates.Count -eq 0) ("Duplicate language keys: " + (($duplicates.Name) -join ", "))
foreach ($key in @("gd_app_title", "gd_menu_calendar", "gd_menu_bookings", "gd_booking_conflict", "gd_menu_booking_series", "gd_booking_series_not_found", "gd_school_students", "gd_school_classes", "gd_school_attendance", "gd_finance_overview", "gd_finance_receivables", "gd_finance_cash")) {
    Assert-True ($keys -contains $key) "Required language key missing: $key"
}
Write-Host "  PASS $($keys.Count) unique gd_* keys"

Write-Host "[FAST] Focused tests"
Write-Host "  SKIP harness has no focused groups; full self-test belongs to verify-full"
Write-Host "VERIFY-FAST: PASS"
