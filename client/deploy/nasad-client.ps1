# Nahraje aplikaci client.4kluciodkol.cz na Forpsi (FTP jen z ČR – proto z KANCL).
# Spouštět ručně v PowerShellu v účtu User:
#   cd D:\Pomocnik
#   powershell -ExecutionPolicy Bypass -File .\nasad-client.ps1
# Předtím jednou uložit údaje (hesla zadává jen Tomáš, ukládají se šifrovaně):
#   powershell -ExecutionPolicy Bypass -File .\nastav-udaj.ps1 ftp_client    (jméno = FTP login, heslo = FTP heslo)
#   powershell -ExecutionPolicy Bypass -File .\nastav-udaj.ps1 db_client     (jméno = f204062, heslo = heslo k databázi)
#   (volitelně) .\nastav-udaj.ps1 smtp_client  (jméno = schránka, ze které se posílá, např. info@4kluciodkol.cz, a její heslo) – zapne odesílání e-mailů
#   (volitelně) .\nastav-udaj.ps1 packeta_client  (jen API heslo; odesílatel je v config.example.php) – zapne podací kódy
#   (volitelně) .\nastav-udaj.ps1 gpwebpay  (prostředí test/ostry, soubory klíčů a heslo ke klíči) – zapne doplatek kartou
param(
    [string]$Server   = 'ftpx.forpsi.com',
    [string]$Cil      = 'subdoms/client',
    [string]$Balicek  = 'D:\Pomocnik\client\client-app.zip',
    [switch]$BezSsl,
    [switch]$Klic      # znovu vypsat instalační klíč
)
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$ErrorActionPreference = 'Stop'
$uloha = 'nasad_client'

function Pis([string]$t, [string]$barva = 'Gray') { Write-Host $t -ForegroundColor $barva; Zapis-Log $uloha $t }

# ---------- údaje ----------
$ftp = Nacti-Udaj 'ftp_client'
$db  = Nacti-Udaj 'db_client'
$smtp = $null
if (Test-Path -LiteralPath (Join-Path $TajneDir 'smtp_client.xml')) { $smtp = Nacti-Udaj 'smtp_client' }
$packeta = $null
if (Test-Path -LiteralPath (Join-Path $TajneDir 'packeta_client.xml')) { $packeta = Nacti-Udaj 'packeta_client' }
$gp = $null
if (Test-Path -LiteralPath (Join-Path $TajneDir 'gpwebpay.xml')) {
    $gp = Nacti-Udaj 'gpwebpay'   # jméno = test | ostry, heslo = heslo k soukromému klíči
    foreach ($f in 'gp_soukromy.pem', 'gp_verejny.pem') {
        if (-not (Test-Path -LiteralPath (Join-Path $TajneDir $f))) { throw "Chybí $f v $TajneDir – spusťte znovu nastav-udaj.ps1 gpwebpay." }
    }
}

# Tajné klíče aplikace: vytvoří se jednou a uloží šifrovaně (app_key = jméno, sync_token = heslo)
$klicSoubor = Join-Path $TajneDir 'client_klice.xml'
$prvni = -not (Test-Path -LiteralPath $klicSoubor)
if ($prvni) {
    function Nahodny { $b = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); ($b | ForEach-Object { $_.ToString('x2') }) -join '' }
    $appKey = Nahodny; $syncToken = Nahodny
    New-Object System.Management.Automation.PSCredential($appKey, (ConvertTo-SecureString $syncToken -AsPlainText -Force)) |
        Export-Clixml -LiteralPath $klicSoubor
}
$klice = Nacti-Udaj 'client_klice'
$appKey = $klice.UserName
$syncToken = $klice.GetNetworkCredential().Password

function PhpStr([string]$s) { "'" + ($s -replace '\\', '\\' -replace "'", "\'") + "'" }

# ---------- rozbalení balíčku ----------
if (-not (Test-Path -LiteralPath $Balicek)) { throw "Chybí balíček $Balicek" }
$tmp = Join-Path $env:TEMP ('client-nasazeni-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
Expand-Archive -LiteralPath $Balicek -DestinationPath $tmp -Force

# ---------- config.php ----------
$vzor = Get-Content -LiteralPath (Join-Path $tmp 'config\config.example.php') -Raw -Encoding UTF8
$cfg = $vzor
$cfg = $cfg -replace "'dsn'\s*=>\s*'mysql:host=DOPLNIT;dbname=DOPLNIT;charset=utf8mb4'", ("'dsn'  => " + (PhpStr 'mysql:host=a067um.forpsi.com;dbname=f204062;charset=utf8mb4'))
$cfg = $cfg -replace "'user'\s*=>\s*'DOPLNIT'", ("'user' => " + (PhpStr $db.UserName))
$cfg = [regex]::Replace($cfg, "'pass'\s*=>\s*'DOPLNIT'", { param($m) "'pass' => " + (PhpStr $db.GetNetworkCredential().Password) })
$cfg = $cfg -replace "'app_key'\s*=>\s*''", ("'app_key' => " + (PhpStr $appKey))
$cfg = $cfg -replace "'sync_token'\s*=>\s*''", ("'sync_token' => " + (PhpStr $syncToken))
if ($smtp) {
    $cfg = $cfg -replace "('mail'\s*=>\s*\[\s*\r?\n\s*'enabled'\s*=>\s*)false", '$1true'
    $cfg = $cfg -replace "'smtp_user'\s*=>\s*'[^']*'", ("'smtp_user'   => " + (PhpStr $smtp.UserName))
    # jen 'from' s e-mailem (v mail), NE 'from' s datem u pravidel dopravy (shipping_rules)
    $cfg = $cfg -replace "'from'\s*=>\s*'[^'@]*@[^']*'", ("'from'        => " + (PhpStr $smtp.UserName))
    $cfg = [regex]::Replace($cfg, "'smtp_pass'\s*=>\s*''", { param($m) "'smtp_pass'   => " + (PhpStr $smtp.GetNetworkCredential().Password) })
}
if ($packeta) {
    $cfg = $cfg -replace "('packeta'\s*=>\s*\[\s*\r?\n\s*'enabled'\s*=>\s*)false", '$1true'
    $cfg = [regex]::Replace($cfg, "'api_password'\s*=>\s*''", { param($m) "'api_password' => " + (PhpStr $packeta.GetNetworkCredential().Password) })
}
if ($gp) {
    $cfg = $cfg -replace "('gpwebpay'\s*=>\s*\[\s*\r?\n\s*'enabled'\s*=>\s*)false", '$1true'
    if ($gp.UserName -eq 'ostry') { $cfg = $cfg -replace "('test'\s*=>\s*)true(,\s*// true = testovací brána)", '$1false$2' }
    $cfg = [regex]::Replace($cfg, "'key_password'\s*=>\s*''", { param($m) "'key_password'  => " + (PhpStr $gp.GetNetworkCredential().Password) })
    Copy-Item -LiteralPath (Join-Path $TajneDir 'gp_soukromy.pem') -Destination (Join-Path $tmp 'config\gp_private.pem') -Force
    Copy-Item -LiteralPath (Join-Path $TajneDir 'gp_verejny.pem') -Destination (Join-Path $tmp 'config\gp_public.pem') -Force
}
if ($cfg -match 'DOPLNIT') { throw 'V config.php zůstalo DOPLNIT – nasazení zastaveno.' }
[IO.File]::WriteAllText((Join-Path $tmp 'config\config.php'), $cfg, (New-Object Text.UTF8Encoding($false)))

# ---------- FTP ----------
$cred = New-Object Net.NetworkCredential($ftp.UserName, $ftp.GetNetworkCredential().Password)
function Ftp([string]$cesta, [string]$metoda) {
    $r = [Net.FtpWebRequest]::Create("ftp://$Server/$cesta")
    $r.Method = $metoda; $r.Credentials = $cred; $r.EnableSsl = -not $BezSsl
    $r.UsePassive = $true; $r.UseBinary = $true; $r.KeepAlive = $false; $r.Timeout = 60000
    return $r
}
function Seznam([string]$cesta) {
    $r = Ftp $cesta ([Net.WebRequestMethods+Ftp]::ListDirectory)
    $s = New-Object IO.StreamReader($r.GetResponse().GetResponseStream())
    $t = $s.ReadToEnd(); $s.Close()
    return @($t -split "`r?`n" | Where-Object { $_ } | ForEach-Object { ($_ -split '/')[-1] })
}
function Slozka([string]$cesta) {
    try { $r = Ftp $cesta ([Net.WebRequestMethods+Ftp]::MakeDirectory); $r.GetResponse().Close() } catch { }  # už existuje
}
function Nahraj([string]$soubor, [string]$cesta) {
    $r = Ftp $cesta ([Net.WebRequestMethods+Ftp]::UploadFile)
    $b = [IO.File]::ReadAllBytes($soubor); $r.ContentLength = $b.Length
    $st = $r.GetRequestStream(); $st.Write($b, 0, $b.Length); $st.Close(); $r.GetResponse().Close()
}

Pis "Připojuji se k $Server ..." 'Cyan'
$koren = Seznam ''
Pis ("Na FTP je: " + ($koren -join ', '))
$prvniCast = ($Cil -split '/')[0]
if ($koren -notcontains $prvniCast) { throw "Na FTP není složka '$prvniCast'. Pošlete Otíkovi řádek výše (co na FTP je)." }
$sub = Seznam $prvniCast
Pis ("Ve složce $prvniCast je: " + ($sub -join ', '))
if ($sub -notcontains ($Cil -split '/')[1]) { throw "Ve složce '$prvniCast' není '$(($Cil -split '/')[1])' – subdoména ještě není připravená." }

$pocet = 0
Get-ChildItem -LiteralPath $tmp -Recurse | Sort-Object FullName | ForEach-Object {
    $rel = $_.FullName.Substring($tmp.Length + 1) -replace '\\', '/'
    if ($_.PSIsContainer) { Slozka "$Cil/$rel" }
    else { Nahraj $_.FullName "$Cil/$rel"; $pocet++ }
}
Remove-Item -LiteralPath $tmp -Recurse -Force
Pis "Hotovo: nahráno $pocet souborů do $Cil." 'Green'

# mezipaměť PHP na hostingu – jinak server ještě dlouho používá staré soubory
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    $hm = New-Object System.Security.Cryptography.HMACSHA256 -ArgumentList (,[byte[]][Text.Encoding]::UTF8.GetBytes($appKey))
    $k = ($hm.ComputeHash([Text.Encoding]::UTF8.GetBytes('obnovit-cache')) | ForEach-Object { $_.ToString('x2') }) -join ''
    $o = Invoke-RestMethod -Uri ("https://client.4kluciodkol.cz/assets/obnovit.php?k=" + $k) -TimeoutSec 30
    Pis ("Mezipaměť PHP: " + $(if ($o.opcache_reset) { 'vyprázdněna' } else { 'není / nešlo vyprázdnit' }) + " (validate_timestamps=" + $o.validate_timestamps + ", revalidate_freq=" + $o.revalidate_freq + ")")
} catch { Pis ("Mezipaměť PHP se nepodařilo vyprázdnit: " + $_.Exception.Message) 'Yellow' }
Pis ("E-maily: " + $(if ($smtp) { ('zapnuté, odesílatel ' + $smtp.UserName) } else { 'vypnuté – jen do logu' }))
Pis ("Platba kartou (GP webpay): " + $(if ($gp) { $(if ($gp.UserName -eq 'ostry') { 'zapnutá – OSTRÁ brána' } else { 'zapnutá – TESTOVACÍ brána' }) } else { 'vypnutá' }))
Pis ("Zásilkovna: " + $(if ($packeta) { ('zapnutá, odesílatel ' + '4 KLUCI OD KOL') } else { 'vypnutá – podací kódy se nevytvářejí' }))

if ($prvni -or $Klic) {
    Write-Host ''
    Write-Host 'PRVNÍ NASAZENÍ – instalace:' -ForegroundColor Yellow
    Write-Host '1. Otevřete https://client.4kluciodkol.cz/admin/instalace'
    Write-Host '2. Instalační klíč (zkopírujte, nikomu neposílejte):' -NoNewline
    Write-Host " $syncToken" -ForegroundColor Yellow
    Write-Host '3. Vyplňte své jméno, e-mail a heslo správce a potvrďte.'
}
