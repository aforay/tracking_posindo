<?php

$cmd = 'powershell -NoProfile -Command "Get-Process php | ForEach-Object { [PSCustomObject]@{ Id = $_.Id; CPU = $_.CPU; CommandLine = (Get-CimInstance Win32_Process -Filter \"ProcessId = $($_.Id)\").CommandLine } } | Format-List"';
echo shell_exec($cmd);
