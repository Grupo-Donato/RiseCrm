$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..\..")).Path
$php = (Get-Command php -ErrorAction Stop).Source
$cli = "plugins/grupo_donato_gestao/Tests/cli.php"
Set-Location $root
$token = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds().ToString()
$ids = (& $php $cli seriessetup $token | Select-Object -Last 1).Trim()
if (-not $ids) { throw "Series race setup failed." }
$seriesId = ($ids -split ',')[0]
$jobs = @()
try {
    1..2 | ForEach-Object {
        $jobs += Start-Job -ScriptBlock {
            param($Php, $Root, $SeriesId, $Cli)
            Set-Location $Root
            & $Php $Cli seriesgenerate $SeriesId 2>&1
        } -ArgumentList $php,$root,$seriesId,$cli
    }
    $jobs | Wait-Job | Out-Null
    $failed = @($jobs | Where-Object State -ne "Completed")
    if ($failed.Count) { throw "Series generators failed: $(@($failed | Receive-Job) -join ' | ')" }
    $inspection = (& $php $cli seriesinspect $seriesId | Select-Object -Last 1).Trim()
    if ($LASTEXITCODE -ne 0) { throw "Series inspection failed: $inspection" }
    $inspection
    "RESULT: PASS - geração concorrente de série sem duplicidades"
} finally {
    $jobs | Remove-Job -Force -ErrorAction SilentlyContinue
    & $php $cli seriescleanup $ids | Out-Null
}
