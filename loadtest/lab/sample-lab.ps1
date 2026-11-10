# Read-only resource sampler for the LOCAL LAB on Windows (isolated Apache + isolated MariaDB). Writes one CSV row per interval.
# It changes nothing. It only watches processes whose command line contains the lab marker, so the developer's own XAMPP
# services are not counted.
#
#   powershell -File loadtest/lab/sample-lab.ps1 -Out results/res-steady-250.csv -Seconds 200 -Interval 2
param(
  [string]$Out = 'resources.csv',
  [int]$Seconds = 120,
  [int]$Interval = 2,
  [string]$HttpdMarker = 'httpd-lab.conf',
  [string]$MysqlMarker = '--port=3307',
  [string]$Mysql = 'D:\xampp\mysql\bin\mysql.exe',
  [string]$DbHost = '127.0.0.1', [int]$DbPort = 3307, [string]$DbUser = 'labuser', [string]$DbPass = 'labpass'
)
$ErrorActionPreference = 'SilentlyContinue'
$cores = (Get-CimInstance Win32_ComputerSystem).NumberOfLogicalProcessors
$totalMb = [math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory / 1MB)
"utc_time,cpu_total_pct,cores,ram_used_mb,ram_total_mb,httpd_procs,httpd_threads,httpd_cpu_pct,httpd_ws_mb,mysqld_ws_mb,mysqld_cpu_pct,db_threads_connected,db_threads_running,db_max_used_connections,db_slow_queries,established_8088" | Out-File -Encoding ascii $Out
$prev = @{}
$end = (Get-Date).AddSeconds($Seconds)
while ((Get-Date) -lt $end) {
  $t0 = Get-Date
  $cpu = (Get-CimInstance Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'").PercentProcessorTime
  $os = Get-CimInstance Win32_OperatingSystem
  $usedMb = [math]::Round(($os.TotalVisibleMemorySize - $os.FreePhysicalMemory) / 1024)
  $all = Get-CimInstance Win32_Process | Where-Object { $_.Name -in 'httpd.exe', 'mysqld.exe' }
  $h = @($all | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -like "*$HttpdMarker*" })
  $m = @($all | Where-Object { $_.Name -eq 'mysqld.exe' -and $_.CommandLine -like "*$MysqlMarker*" })
  $hThreads = ($h | Measure-Object -Property ThreadCount -Sum).Sum
  $hWs = [math]::Round((($h | Measure-Object -Property WorkingSetSize -Sum).Sum) / 1MB)
  $mWs = [math]::Round((($m | Measure-Object -Property WorkingSetSize -Sum).Sum) / 1MB)
  # CPU% of a process group = delta(kernel+user time) / delta(wall) / cores
  $now = Get-Date
  $pct = @{}
  foreach ($grp in @(@('h', $h), @('m', $m))) {
    $cpuTime = (($grp[1] | ForEach-Object { $_.KernelModeTime + $_.UserModeTime }) | Measure-Object -Sum).Sum / 1e7
    if ($prev.ContainsKey($grp[0])) { $dt = ($now - $prev[$grp[0]].t).TotalSeconds; $pct[$grp[0]] = if ($dt -gt 0) { [math]::Round(100 * ($cpuTime - $prev[$grp[0]].c) / $dt / $cores, 1) } else { 0 } } else { $pct[$grp[0]] = 0 }
    $prev[$grp[0]] = @{ t = $now; c = $cpuTime }
  }
  $st = & $Mysql -h $DbHost -P $DbPort -u $DbUser "-p$DbPass" -N -e "SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Max_used_connections','Slow_queries')" 2>$null
  $s = @{}; foreach ($l in $st) { $p = $l -split "\s+"; if ($p.Count -ge 2) { $s[$p[0]] = $p[1] } }
  $est = @(Get-NetTCPConnection -LocalPort 8088 -State Established).Count
  $cols = @((Get-Date).ToUniversalTime().ToString('s'), $cpu, $cores, $usedMb, $totalMb, $h.Count, [int]$hThreads, $pct['h'], $hWs, $mWs, $pct['m'], $s['Threads_connected'], $s['Threads_running'], $s['Max_used_connections'], $s['Slow_queries'], $est)
  ($cols | ForEach-Object { "$_" }) -join ',' | Out-File -Append -Encoding ascii $Out
  $sleep = $Interval - ((Get-Date) - $t0).TotalSeconds
  if ($sleep -gt 0) { Start-Sleep -Milliseconds ([int]($sleep * 1000)) }
}
Write-Output "wrote $Out"
