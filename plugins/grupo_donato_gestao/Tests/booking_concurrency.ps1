$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$php = (Get-Command php).Source
$token = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds().ToString()

function Invoke-BookingRace([int]$resourceCount, [bool]$reverseSecond) {
    $ids = (& $php "plugins/grupo_donato_gestao/Tests/cli.php" bookingsetup $token $resourceCount | Select-Object -Last 1).Trim()
    if (-not $ids) { throw "Booking race setup failed." }
    $aOut = Join-Path $root "writable\gd-booking-a.txt"
    $bOut = Join-Path $root "writable\gd-booking-b.txt"
    $aErr = Join-Path $root "writable\gd-booking-a.err.txt"
    $bErr = Join-Path $root "writable\gd-booking-b.err.txt"
    try {
        $a = Start-Process -FilePath $php -ArgumentList @("plugins/grupo_donato_gestao/Tests/cli.php", "bookingwrite", $ids, "0") -WorkingDirectory $root -RedirectStandardOutput $aOut -RedirectStandardError $aErr -PassThru -WindowStyle Hidden
        $b = Start-Process -FilePath $php -ArgumentList @("plugins/grupo_donato_gestao/Tests/cli.php", "bookingwrite", $ids, $(if ($reverseSecond) { "1" } else { "0" })) -WorkingDirectory $root -RedirectStandardOutput $bOut -RedirectStandardError $bErr -PassThru -WindowStyle Hidden
        $a.WaitForExit(); $b.WaitForExit()
        $errors = @(); if (Test-Path $aErr) { $errors += Get-Content $aErr }; if (Test-Path $bErr) { $errors += Get-Content $bErr }
        if ($errors.Count) { throw ($errors -join " | ") }
        $results = @((Get-Content $aOut), (Get-Content $bOut))
        $saved = @($results | Where-Object { $_ -eq "saved" }).Count
        $conflict = @($results | Where-Object { $_ -eq "conflict" }).Count
        if ($saved -ne 1 -or $conflict -ne 1) { throw "Expected saved=1 conflict=1; got saved=$saved conflict=$conflict" }
        "resources=$resourceCount reverse=$reverseSecond saved=$saved conflict=$conflict"
    } finally {
        & $php "plugins/grupo_donato_gestao/Tests/cli.php" bookingcleanup $ids | Out-Null
        Remove-Item -LiteralPath $aOut,$bOut,$aErr,$bErr -Force -ErrorAction SilentlyContinue
    }
}

Invoke-BookingRace 1 $false
$token = ([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds() + 1).ToString()
Invoke-BookingRace 2 $true
"RESULT: PASS - concorrência de reservas simples e multi-recurso serializada"
