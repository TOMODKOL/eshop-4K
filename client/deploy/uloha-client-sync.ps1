# Založí naplánovanou úlohu \Pomocnik\Client-sync: každou hodinu 5:33–23:33 pošle nové e-shopové faktury z Pohody do client.4kluciodkol.cz.
# Spusťte jednou v účtu User:  cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\uloha-client-sync.ps1
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$ws = Join-Path $env:SystemRoot 'System32\wscript.exe'
$akce = New-ScheduledTaskAction -Execute $ws -Argument ('"{0}" "{1}"' -f (Join-Path $Koren 'skryte.vbs'), (Join-Path $Koren 'client-sync.ps1'))
$nast = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$t = New-ScheduledTaskTrigger -Daily -At '05:33'
$t.Repetition = (New-ScheduledTaskTrigger -Once -At '05:33' -RepetitionInterval (New-TimeSpan -Hours 1) `
                 -RepetitionDuration (New-TimeSpan -Hours 18 -Minutes 1)).Repetition
Register-ScheduledTask -TaskName 'Client-sync' -TaskPath '\Pomocnik\' -Action $akce -Trigger $t -Settings $nast -Force | Out-Null
Write-Host 'Úloha \Pomocnik\Client-sync založena (každou hodinu 5:33–23:33).' -ForegroundColor Green
