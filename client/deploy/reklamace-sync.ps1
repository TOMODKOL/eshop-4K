# Přenáší reklamace z Pohody (agenda Reklamace, jen čtení přes mServer) do client.4kluciodkol.cz.
#  - nové reklamace zadané v Pohodě se objeví v portálu (zákazník s e-mailem dostane potvrzení s číslem 26RZ…)
#  - reklamace se propojí s požadavkem zákazníka z webu podle čísla dokladu nákupu (pole Doklad)
#  - změna stavu (záložka Stav reklamace) se zákazníkovi ukáže a pošle e-mailem
# První běh načte reklamace od 1. 1. letošního roku bez e-mailů, další běhy jen změněné od minula.
# Ručně:  cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\reklamace-sync.ps1
param([string]$Portal = 'https://client.4kluciodkol.cz')
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$uloha = 'reklamace_sync'

$hodina = (Get-Date).Hour
if ($hodina -lt $MServerOd -or $hodina -ge $MServerDo) { return }

$stavF = Join-Path $StavDir 'reklamace_sync.json'
$prvni = -not (Test-Path -LiteralPath $stavF)
$celkem = $false     # „posledni“: „vse“ = jednorázově znovu načíst všechny reklamace od 2024 (bez e-mailů)
if (-not $prvni) { try { $celkem = ((Get-Content -LiteralPath $stavF -Raw -Encoding UTF8 | ConvertFrom-Json).posledni -eq 'vse') } catch {} }
if ($celkem) { $prvni = $true }
$zacatek = (Get-Date).AddMinutes(-2)        # rezerva na rozdíl hodin KANCL × server Pohody

$udaj  = Nacti-Udaj 'mserver'
$kod   = [Text.Encoding]::GetEncoding($MServerKodovaniPrihlaseni)
$auth  = [Convert]::ToBase64String($kod.GetBytes(('{0}:{1}' -f $udaj.UserName, $udaj.GetNetworkCredential().Password)))
$hlav  = @{ 'STW-Authorization' = "Basic $auth"; 'STW-Application' = 'Pomocnik 4K' }
$url   = $MServerAdresa.TrimEnd('/') + '/xml'
$cp1250 = [Text.Encoding]::GetEncoding('windows-1250')
$token = (Nacti-Udaj 'client_klice').GetNetworkCredential().Password

function Uzel($n, [string]$cesta) {
    if ($null -eq $n) { return $null }
    $xp = './' + ((($cesta -split '/') | ForEach-Object { "*[local-name()='$_']" }) -join '/')
    return $n.SelectSingleNode($xp)
}
function Text($n, [string]$cesta) { $u = Uzel $n $cesta; if ($u) { $u.InnerText.Trim() } else { '' } }

try {
    if ($prvni) {
        $od = if ($celkem) { '2024-01-01' } else { (Get-Date -Month 1 -Day 1).ToString('yyyy-MM-dd') }
        $filtr = '<ftr:filter><ftr:dateFrom>' + $od + '</ftr:dateFrom></ftr:filter>'
    } else {
        $posledni = (Get-Content -LiteralPath $stavF -Raw -Encoding UTF8 | ConvertFrom-Json).posledni
        $filtr = '<ftr:filter><ftr:lastChanges>' + $posledni + '</ftr:lastChanges></ftr:filter>'
    }
    $xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="reklamace_sync" ico="08576726" application="Pomocnik 4K" version="2.0" note="Reklamace pro client.4kluciodkol.cz - jen cteni"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"
  xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
  <dat:dataPackItem id="rekl" version="2.0"><lst:listClaimRequest version="2.0" claimVersion="2.0"><lst:requestClaim>$filtr</lst:requestClaim></lst:listClaimRequest></dat:dataPackItem>
</dat:dataPack>
"@
    $odp = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 600
    $doc = New-Object Xml.XmlDocument
    $doc.LoadXml($cp1250.GetString($odp.RawContentStream.ToArray()))
    $st = $doc.SelectSingleNode("//*[local-name()='responsePackItem']")
    if ($st -and $st.GetAttribute('state') -ne 'ok') { throw ('Pohoda vrátila stav ' + $st.GetAttribute('state') + ': ' + $st.GetAttribute('note')) }

    $rekl = New-Object System.Collections.ArrayList
    foreach ($c in $doc.SelectNodes("//*[local-name()='claim' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")) {
        $h = Uzel $c 'claimHeader'
        if ((Text $h 'claimType') -ne 'customer') { continue }          # jen zákaznické reklamace
        $s = Uzel $c 'claimSubject/subject'
        $adr = Uzel $h 'partnerIdentity/address'
        $jmeno = Text $adr 'name'; if (-not $jmeno) { $jmeno = Text $adr 'company' }
        $tel = Text $adr 'mobilPhone'; if (-not $tel) { $tel = Text $adr 'phone' }
        [void]$rekl.Add([ordered]@{
            number        = (Text $h 'number/numberRequested')
            status        = (Text $h 'status/ids')
            received      = (Text $h 'received')
            settle_until  = (Text $h 'settleUntil')
            executed      = (Text $h 'executed')          # datum vyřízení v Pohodě = reklamace je uzavřená
            document_no   = (Text $h 'numberDocument')
            document_date = (Text $h 'dateDocument')
            name          = $jmeno
            phone         = $tel
            email         = (Text $adr 'email')
            text          = $(if ($s) { Text $s 'text' } else { Text $h 'text' })
            code          = $(if ($s) { Text $s 'code' } else { '' })
            qty           = $(if ($s) { Text $s 'quantity' } else { '1' })
            defect        = $(if ($s) { Text $s 'defectDescription' } else { '' })
            note          = (Text $h 'note')
            int_note      = (Text $h 'intNote')
        })
    }

    $poslano = 0; $emaily = 0
    for ($i = 0; $i -lt $rekl.Count; $i += 50) {
        $davka = New-Object System.Collections.ArrayList
        $rekl.GetRange($i, [math]::Min(50, $rekl.Count - $i)) | ForEach-Object { [void]$davka.Add($_) }
        $json = ConvertTo-Json -InputObject ([ordered]@{ initial = $prvni; claims = $davka }) -Depth 6 -Compress
        $r = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/reklamace') -Method Post `
             -Body ([Text.Encoding]::UTF8.GetBytes($json)) -ContentType 'application/json; charset=utf-8' `
             -Headers @{ Authorization = "Bearer $token" } -TimeoutSec 120
        $poslano += [int]$r.claims; $emaily += [int]$r.notified
    }
    Set-Content -LiteralPath $stavF -Value (@{ posledni = $zacatek.ToString('yyyy-MM-ddTHH:mm:ss') } | ConvertTo-Json) -Encoding UTF8
    if ($rekl.Count -gt 0 -or $prvni) {
        $zprava = 'OK    reklamací {0}{1}, e-mailů zákazníkům {2}' -f $rekl.Count, $(if ($prvni) { ' (první načtení, bez e-mailů)' } else { '' }), $emaily
        Zapis-Log $uloha $zprava
        Write-Host $zprava -ForegroundColor Green
    }
} catch {
    Zapis-Log $uloha ('CHYBA {0}' -f $_.Exception.Message)
    Write-Host ('CHYBA ' + $_.Exception.Message) -ForegroundColor Red
}

# doklady k vratkám (opravný daňový doklad / faktura) – stejná úloha, každých 5 minut
try { & (Join-Path $PSScriptRoot 'vratky-doklady.ps1') } catch { Zapis-Log 'vratky_doklady' ('CHYBA ' + $_.Exception.Message) }

# faktury v PDF pro zákazníky (tisk z Pohody sestavou Faktura → portál) – stejná úloha, každých 5 minut
try { & (Join-Path $PSScriptRoot 'faktury-pdf.ps1') } catch { Zapis-Log 'faktury_pdf' ('CHYBA ' + $_.Exception.Message) }
