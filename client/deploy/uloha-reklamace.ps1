# Založí naplánovanou úlohu \Pomocnik\Reklamace-sync: každých 5 minut 5:04–23:59 přenese reklamace z Pohody do client.4kluciodkol.cz a vystaví doklady k vratkám (stejně jako mServer, config.ps1 $MServerOd/$MServerDo).
# Spusťte jednou v účtu User:  cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\uloha-reklamace.ps1
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$ws = Join-Path $env:SystemRoot 'System32\wscript.exe'
$akce = New-ScheduledTaskAction -Execute $ws -Argument ('"{0}" "{1}"' -f (Join-Path $Koren 'skryte.vbs'), (Join-Path $Koren 'reklamace-sync.ps1'))
$nast = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$t = New-ScheduledTaskTrigger -Daily -At '05:04'
$t.Repetition = (New-ScheduledTaskTrigger -Once -At '05:04' -RepetitionInterval (New-TimeSpan -Minutes 5) `
                 -RepetitionDuration (New-TimeSpan -Hours 18 -Minutes 55)).Repetition
Register-ScheduledTask -TaskName 'Reklamace-sync' -TaskPath '\Pomocnik\' -Action $akce -Trigger $t -Settings $nast -Force | Out-Null
Write-Host 'Úloha \Pomocnik\Reklamace-sync založena (každých 5 minut 5:04–23:59).' -ForegroundColor Green
