$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$php = (Get-Command php).Source
$type = "conc_{0}_{1}" -f [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds(), $PID
$outA = Join-Path $root "writable\gd-seq-a.txt"
$outB = Join-Path $root "writable\gd-seq-b.txt"
$errA = Join-Path $root "writable\gd-seq-a.err.txt"
$errB = Join-Path $root "writable\gd-seq-b.err.txt"
$temporalA = Join-Path $root "writable\gd-temporal-a.txt"
$temporalB = Join-Path $root "writable\gd-temporal-b.txt"
$temporalErrA = Join-Path $root "writable\gd-temporal-a.err.txt"
$temporalErrB = Join-Path $root "writable\gd-temporal-b.err.txt"
$resourceId = 0

try {
    $argsA = @("plugins/grupo_donato_gestao/Tests/cli.php", "seqgrab", "50", $type)
    $argsB = @("plugins/grupo_donato_gestao/Tests/cli.php", "seqgrab", "50", $type)
    $a = Start-Process -FilePath $php -ArgumentList $argsA -WorkingDirectory $root -RedirectStandardOutput $outA -RedirectStandardError $errA -PassThru -WindowStyle Hidden
    $b = Start-Process -FilePath $php -ArgumentList $argsB -WorkingDirectory $root -RedirectStandardOutput $outB -RedirectStandardError $errB -PassThru -WindowStyle Hidden
    $a.WaitForExit()
    $b.WaitForExit()
    $errors = @()
    if (Test-Path -LiteralPath $errA) { $errors += Get-Content -LiteralPath $errA }
    if (Test-Path -LiteralPath $errB) { $errors += Get-Content -LiteralPath $errB }
    if ($errors.Count -gt 0) {
        throw ("Process failure: " + ($errors -join " | "))
    }

    $lines = @(Get-Content -LiteralPath $outA)
    $lines += @(Get-Content -LiteralPath $outB)
    $numbers = @($lines | Where-Object { $_ -match '^\d+$' } | ForEach-Object { [int64] $_ })
    $distinct = @($numbers | Sort-Object -Unique)
    "A=50 B=50 total=$($numbers.Count) distinct=$($distinct.Count)"
    if ($numbers.Count -ne 100 -or $distinct.Count -ne 100) {
        throw "Duplicate or missing sequence numbers."
    }
    "RESULT: PASS - nenhuma duplicata entre processos paralelos"

    $resourceId = [int64]((& $php "plugins/grupo_donato_gestao/Tests/cli.php" temporalsetup $type | Select-Object -Last 1))
    $argsTA = @("plugins/grupo_donato_gestao/Tests/cli.php", "temporalwrite", "$resourceId")
    $argsTB = @("plugins/grupo_donato_gestao/Tests/cli.php", "temporalwrite", "$resourceId")
    $ta = Start-Process -FilePath $php -ArgumentList $argsTA -WorkingDirectory $root -RedirectStandardOutput $temporalA -RedirectStandardError $temporalErrA -PassThru -WindowStyle Hidden
    $tb = Start-Process -FilePath $php -ArgumentList $argsTB -WorkingDirectory $root -RedirectStandardOutput $temporalB -RedirectStandardError $temporalErrB -PassThru -WindowStyle Hidden
    $ta.WaitForExit(); $tb.WaitForExit(); $ta.Refresh(); $tb.Refresh()
    $temporalErrors = @()
    if (Test-Path -LiteralPath $temporalErrA) { $temporalErrors += Get-Content -LiteralPath $temporalErrA }
    if (Test-Path -LiteralPath $temporalErrB) { $temporalErrors += Get-Content -LiteralPath $temporalErrB }
    if ($temporalErrors.Count -gt 0) { throw ("Temporal process failure: " + ($temporalErrors -join " | ")) }
    $temporalResults = @((Get-Content -LiteralPath $temporalA), (Get-Content -LiteralPath $temporalB))
    $savedCount = @($temporalResults | Where-Object { $_ -eq "saved" }).Count
    $duplicateCount = @($temporalResults | Where-Object { $_ -eq "duplicate" }).Count
    "temporal saved=$savedCount duplicate=$duplicateCount"
    if ($savedCount -ne 1 -or $duplicateCount -ne 1) { throw "Temporal race did not serialize to one save and one duplicate." }
    "RESULT: PASS - bloqueio temporal concorrente serializado"
} finally {
    & $php "plugins/grupo_donato_gestao/Tests/cli.php" seqcleanup $type | Out-Null
    if ($resourceId -gt 0) { & $php "plugins/grupo_donato_gestao/Tests/cli.php" temporalcleanup "$resourceId" | Out-Null }
    Remove-Item -LiteralPath $outA, $outB, $errA, $errB, $temporalA, $temporalB, $temporalErrA, $temporalErrB -Force -ErrorAction SilentlyContinue
}
