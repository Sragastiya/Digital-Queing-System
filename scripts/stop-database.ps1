$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskPidFile = Join-Path $taskRoot 'storage\local\mysql.pid'
if (-not (Test-Path -LiteralPath $taskPidFile)) { Write-Output 'No recorded PBL MySQL process.'; return }
$taskPid = [int](Get-Content -LiteralPath $taskPidFile)
$taskProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $taskPid"
if ($taskProcess) {
 $taskExpected = (Join-Path $taskRoot 'storage\local\mysql').Replace('\','/')
 if ($taskProcess.Name -ne 'mysqld.exe' -or -not $taskProcess.CommandLine.Contains($taskExpected)) { throw 'Recorded process does not match the PBL database; refusing to stop it.' }
 $taskRuntime = Get-Content -Raw -LiteralPath (Join-Path $taskRoot 'storage\local\runtime.json') | ConvertFrom-Json
 & $taskRuntime.php (Join-Path $PSScriptRoot 'stop-database.php')
 if ($LASTEXITCODE -ne 0) { throw 'Graceful database shutdown failed; process was not killed.' }
 Wait-Process -Id $taskPid -Timeout 15 -ErrorAction SilentlyContinue
}
Remove-Item -LiteralPath $taskPidFile
Write-Output 'PBL MySQL stopped; no other database service was changed.'
