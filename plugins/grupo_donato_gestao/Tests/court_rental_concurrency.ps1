$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$php = (Get-Command php -ErrorAction Stop).Source
$cli = "plugins/grupo_donato_gestao/Tests/cli.php"
Set-Location $root
$token = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds().ToString()

function Invoke-Race([string[]]$ArgsA, [string[]]$ArgsB) {
    $block = { param($Php, $Root, $Cli, $A) Set-Location $Root; & $Php $Cli @A 2>&1 }
    $jobA = Start-Job -ScriptBlock $block -ArgumentList $php, $root, $cli, $ArgsA
    $jobB = Start-Job -ScriptBlock $block -ArgumentList $php, $root, $cli, $ArgsB
    $jobA, $jobB | Wait-Job | Out-Null
    $lines = @(Receive-Job $jobA) + @(Receive-Job $jobB)
    Remove-Job $jobA, $jobB -Force
    return @($lines)
}

function Assert-OneWinner([string[]]$Lines, [string]$Winner, [string]$Label) {
    $won = @($Lines | Where-Object { $_ -match $Winner }).Count
    $lost = @($Lines | Where-Object { $_ -match 'conflict' }).Count
    if ($won -ne 1 -or $lost -ne 1) { throw "$Label expected 1 '$Winner' + 1 'conflict', got: $($Lines -join ' | ')" }
    Write-Host "  PASS $Label ($Winner=1 conflict=1)"
}

$ids = (& $php $cli rentalracesetup $token | Select-Object -Last 1).Trim()
if (-not $ids) { throw "Court rental race setup failed." }
$parts = $ids -split ','
$activateId = $parts[0]; $seriesId = $parts[1]; $draftA = $parts[2]; $draftB = $parts[3]; $overrideId = $parts[4]; $rid = $parts[5]; $aid = $parts[6]

try {
    Assert-OneWinner (Invoke-Race @("rentalactivate", $activateId, "1") @("rentalactivate", $activateId, "1")) "activated" "duas ativações simultâneas"
    Assert-OneWinner (Invoke-Race @("rentallink", $draftA, $seriesId) @("rentallink", $draftB, $seriesId)) "linked" "duas locações na mesma série"
    Assert-OneWinner (Invoke-Race @("rentaloverride", $overrideId, "1", "90.00") @("rentaloverride", $overrideId, "1", "80.00")) "repriced" "dois overrides concorrentes"
    Assert-OneWinner (Invoke-Race @("rentalcreate", $rid, $aid, "2099-06-20T14:00", "2099-06-20T15:00") @("rentalcreate", $rid, $aid, "2099-06-20T14:00", "2099-06-20T15:00")) "created" "criação integrada concorrente no mesmo recurso"

    $inspection = (& $php $cli rentalraceinspect "$activateId,$seriesId,$overrideId,$rid" | Select-Object -Last 1).Trim()
    if ($LASTEXITCODE -ne 0) { throw "Court rental race inspection failed: $inspection" }
    $inspection
    "RESULT: PASS - locação comercial concorrente sem dupla ocupação, vínculo duplicado ou overwrite silencioso"
} finally {
    & $php $cli rentalracecleanup $ids | Out-Null
}
