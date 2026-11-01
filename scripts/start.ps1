param([string]$Php, [switch]$NoDatabase)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $taskRoot
$taskConfigPath = Join-Path $taskRoot 'storage\local\runtime.json'
$taskRuntime = if (Test-Path -LiteralPath $taskConfigPath) { Get-Content -Raw -LiteralPath $taskConfigPath | ConvertFrom-Json } else { $null }
if (-not $Php) { $Php = if ($taskRuntime) { $taskRuntime.php } else { (Get-Command php -ErrorAction Stop).Source } }
if (-not $NoDatabase -and $taskRuntime) {
 $taskListening = Get-NetTCPConnection -State Listen -LocalPort 3311 -ErrorAction SilentlyContinue
 if (-not $taskListening) {
  $taskData = (Join-Path $taskRoot 'storage\local\mysql').Replace('\','/')
  $taskLog = (Join-Path $taskRoot 'storage\local\mysql.log').Replace('\','/')
  $taskMysql = Start-Process -FilePath (Join-Path $taskRuntime.mysql_base 'bin\mysqld.exe') -ArgumentList @('--no-defaults',('--basedir="'+$taskRuntime.mysql_base+'"'),('--datadir="'+$taskData+'"'),'--port=3311','--bind-address=127.0.0.1','--mysqlx=OFF','--innodb-buffer-pool-size=67108864','--innodb-redo-log-capacity=104857600',('--log-error="'+$taskLog+'"')) -WindowStyle Hidden -PassThru
  $taskMysql.Id | Set-Content -LiteralPath (Join-Path $taskRoot 'storage\local\mysql.pid')
  for ($taskTry=0; $taskTry -lt 30; $taskTry++) { if (Get-NetTCPConnection -State Listen -LocalPort 3311 -ErrorAction SilentlyContinue) { break }; Start-Sleep -Milliseconds 200 }
 }
}
if (Get-NetTCPConnection -State Listen -LocalPort 8085 -ErrorAction SilentlyContinue) { throw 'Port 8085 is already in use; do not start a second web server.' }
Write-Output 'Pelanggan: http://127.0.0.1:8085/ | Staf: http://127.0.0.1:8085/staf'
# Quiet suppresses Artisan request-path output: ticket URLs contain private secrets.
& $Php artisan serve --host=127.0.0.1 --port=8085 --no-reload --quiet
