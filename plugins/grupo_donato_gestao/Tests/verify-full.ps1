$ErrorActionPreference = "Stop"

$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$plugin = Join-Path $root "plugins\grupo_donato_gestao"
$php = (Get-Command php -ErrorAction Stop).Source
$startedAt = Get-Date
$logCounts = @{}
$logDir = Join-Path $root "writable\logs"
if (Test-Path -LiteralPath $logDir) {
    Get-ChildItem -LiteralPath $logDir -File | ForEach-Object { $logCounts[$_.FullName] = @(Get-Content -LiteralPath $_.FullName).Count }
}

function Invoke-Step([string]$Name, [scriptblock]$Action) {
    Write-Host "[FULL] $Name"
    & $Action
    if ($LASTEXITCODE -ne 0) { throw "$Name failed with exit code $LASTEXITCODE." }
    Write-Host "  PASS"
}

function Compare-Manifest([string]$Manifest, [string]$BasePath) {
    $rows = @(Import-Csv -LiteralPath $Manifest)
    $expected = @{}
    foreach ($row in $rows) {
        $relative = $row.Path.Replace('/', '\')
        $expected[$relative.ToLowerInvariant()] = $row.SHA256.ToLowerInvariant()
        $path = Join-Path $BasePath $relative
        if (-not (Test-Path -LiteralPath $path)) { throw "Missing baseline file: $path" }
        $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash.ToLowerInvariant()
        if ($actual -ne $expected[$relative.ToLowerInvariant()]) { throw "Changed baseline file: $path" }
    }
    $actualFiles = @(Get-ChildItem -LiteralPath $BasePath -Recurse -File | ForEach-Object { $_.FullName.Substring((Resolve-Path $BasePath).Path.Length).TrimStart('\').ToLowerInvariant() })
    $extra = @($actualFiles | Where-Object { -not $expected.ContainsKey($_) })
    if ($extra.Count) { throw "Unexpected files under baseline root: $($extra -join ', ')" }
}

Push-Location $root
try {
    Invoke-Step "verify-fast" { & (Join-Path $PSScriptRoot "verify-fast.ps1") }
    Invoke-Step "installation" { & $php "plugins/grupo_donato_gestao/Tests/cli.php" install }
    Invoke-Step "installation idempotency" { & $php "plugins/grupo_donato_gestao/Tests/cli.php" install }
    Invoke-Step "self-test" { & $php "plugins/grupo_donato_gestao/Tests/cli.php" selftest }
    Invoke-Step "sequence and temporal concurrency" { & (Join-Path $PSScriptRoot "concurrency.ps1") }
    Invoke-Step "booking concurrency" { & (Join-Path $PSScriptRoot "booking_concurrency.ps1") }
    Invoke-Step "booking series concurrency" { & (Join-Path $PSScriptRoot "series_concurrency.ps1") }
    Invoke-Step "court rental concurrency" { & (Join-Path $PSScriptRoot "court_rental_concurrency.ps1") }
    Invoke-Step "uninstall preservation" { & $php "plugins/grupo_donato_gestao/Tests/cli.php" uninstallcheck }

    Invoke-Step "operacional module (autoload, rotas, views, 9 tabelas grupo_donato_*)" { & $php "plugins/grupo_donato_gestao/Tests/cli.php" operacional-check }

    $integrityDirs = @(Get-ChildItem -LiteralPath (Join-Path $root "writable\backups") -Directory -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending |
        ForEach-Object { Join-Path $_.FullName "integrity" } |
        Where-Object { (Test-Path (Join-Path $_ "rise-app.csv")) -and (Test-Path (Join-Path $_ "rise-system.csv")) })
    if ($integrityDirs.Count) {
        Invoke-Step "Rise core integrity" {
            Compare-Manifest (Join-Path $integrityDirs[0] "rise-app.csv") (Join-Path $root "app")
            Compare-Manifest (Join-Path $integrityDirs[0] "rise-system.csv") (Join-Path $root "system")
        }
    } else {
        Write-Host "[FULL] Rise core integrity`n  SKIP no per-file baseline manifest found"
    }

    $mysqlcheck = $env:GD_MYSQLCHECK_EXE
    if (-not $mysqlcheck) {
        $command = Get-Command mysqlcheck -ErrorAction SilentlyContinue
        if ($command) { $mysqlcheck = $command.Source }
        elseif (Test-Path "C:\xampp\mysql\bin\mysqlcheck.exe") { $mysqlcheck = "C:\xampp\mysql\bin\mysqlcheck.exe" }
    }
    if ($mysqlcheck -and -not $env:GD_SKIP_DB_CHECK) {
        Invoke-Step "database CHECK TABLE" {
            $dbName = if ($env:GD_DB_NAME) { $env:GD_DB_NAME } else { "rise_crm" }
            $dbUser = if ($env:GD_DB_USER) { $env:GD_DB_USER } else { "root" }
            $arguments = @("--check", "--silent", "--user=$dbUser")
            if ($env:GD_DB_PASSWORD) { $arguments += "--password=$($env:GD_DB_PASSWORD)" }
            $arguments += $dbName
            & $mysqlcheck @arguments
        }
    } else {
        Write-Host "[FULL] database CHECK TABLE`n  SKIP mysqlcheck unavailable or GD_SKIP_DB_CHECK set"
    }

    Write-Host "[FULL] New relevant log errors"
    $newErrors = @()
    if (Test-Path -LiteralPath $logDir) {
        foreach ($file in Get-ChildItem -LiteralPath $logDir -File) {
            $skip = if ($logCounts.ContainsKey($file.FullName)) { $logCounts[$file.FullName] } else { 0 }
            $newLines = @(Get-Content -LiteralPath $file.FullName | Select-Object -Skip $skip)
            $newErrors += @($newLines | Where-Object { $_ -match '(?i)(CRITICAL|ERROR).*(GD|grupo_donato|booking)' })
        }
    }
    if ($newErrors.Count) { throw "New relevant log errors: $($newErrors -join ' | ')" }
    Write-Host "  PASS"
    Write-Host "VERIFY-FULL: PASS ($([math]::Round(((Get-Date)-$startedAt).TotalSeconds, 1))s)"
} finally {
    Pop-Location
}
