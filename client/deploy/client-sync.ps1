# Pošle e-shopové faktury z Pohody do portálu client.4kluciodkol.cz (vratky a reklamace).
#  - čte vydané faktury přes mServer (jen čtení), bere jen ty s objednávkou z e-shopu (číslo 8810…)
#  - posílá je na https://client.4kluciodkol.cz/api/sync/objednavky (klíč = sync_token z nasad-client.ps1)
#  - první běh nahraje faktury od 1. 1. 2025 po měsících, další běhy jen posledních $Dni dní
# Ručně:  cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\client-sync.ps1 [-Od 2025-01-01]
param([string]$Od = '', [int]$Dni = 4, [string]$Portal = 'https://client.4kluciodkol.cz')
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$uloha = 'client_sync'

$hodina = (Get-Date).Hour
if ($hodina -lt $MServerOd -or $hodina -ge $MServerDo) { return }

$stavF = Join-Path $StavDir 'client_sync.json'
$prvni = -not (Test-Path -LiteralPath $stavF)
if (-not $Od) { $Od = $(if ($prvni) { '2025-01-01' } else { (Get-Date).AddDays(-$Dni).ToString('yyyy-MM-dd') }) }

# Položky faktury, které nejsou zboží (doprava, dobírka, platba, poukazy…)
$NeniZbozi = '(?i)doprav|dob[ií]rk|po[sš]tovn|balné|výdejní místo|vydejni misto|z-box|zásilkovn|zasilkovn|\bgls\b|\bppl\b|dpd|česká pošta|ceska posta|osobní odběr|osobni odber|platba|poplatek|zaokrouhl|dárkov|poukaz|sleva'

$udaj  = Nacti-Udaj 'mserver'
$kod   = [Text.Encoding]::GetEncoding($MServerKodovaniPrihlaseni)
$auth  = [Convert]::ToBase64String($kod.GetBytes(('{0}:{1}' -f $udaj.UserName, $udaj.GetNetworkCredential().Password)))
$hlav  = @{ 'STW-Authorization' = "Basic $auth"; 'STW-Application' = 'Pomocnik 4K' }
$url   = $MServerAdresa.TrimEnd('/') + '/xml'
$cp1250 = [Text.Encoding]::GetEncoding('windows-1250')
$token = (Nacti-Udaj 'client_klice').GetNetworkCredential().Password
$inv   = [Globalization.CultureInfo]::InvariantCulture

function Uzel($n, [string]$cesta) {
    if ($null -eq $n) { return $null }
    $xp = './' + ((($cesta -split '/') | ForEach-Object { "*[local-name()='$_']" }) -join '/')
    return $n.SelectSingleNode($xp)
}
function Text($n, [string]$cesta) { $u = Uzel $n $cesta; if ($u) { $u.InnerText.Trim() } else { '' } }
function Cislo([string]$s) { if ($s) { [double]::Parse($s, $inv) } else { 0.0 } }

function Nacti-Faktury([datetime]$d1, [datetime]$d2) {
    $xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="client_sync" ico="08576726" application="Pomocnik 4K" version="2.0" note="Faktury pro client.4kluciodkol.cz - jen cteni"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"
  xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
  <dat:dataPackItem id="fv" version="2.0"><lst:listInvoiceRequest version="2.0" invoiceType="issuedInvoice" invoiceVersion="2.0"><lst:requestInvoice><ftr:filter><ftr:dateFrom>$($d1.ToString('yyyy-MM-dd'))</ftr:dateFrom><ftr:dateTill>$($d2.ToString('yyyy-MM-dd'))</ftr:dateTill></ftr:filter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>
</dat:dataPack>
"@
    $odp = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 900
    $doc = New-Object Xml.XmlDocument
    $doc.LoadXml($cp1250.GetString($odp.RawContentStream.ToArray()))
    $st = $doc.SelectSingleNode("//*[local-name()='responsePackItem']")
    if ($st -and $st.GetAttribute('state') -ne 'ok') { throw ('Pohoda vrátila stav ' + $st.GetAttribute('state') + ': ' + $st.GetAttribute('note')) }
    return $doc.SelectNodes("//*[local-name()='invoice' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")
}

function Na-Objednavku($f) {
    $h = Uzel $f 'invoiceHeader'
    $obj = Text $h 'numberOrder'
    if (-not $obj) { $obj = Text $h 'symVar' }
    if ($obj -notmatch '^8810\d+$') { return $null }          # jen e-shop
    $adr = Uzel $h 'partnerIdentity/address'
    $email = Text $adr 'email'
    if (-not $email) { $email = Text $h 'partnerIdentity/shipToAddress/email' }
    $jmeno = Text $adr 'name'; if (-not $jmeno) { $jmeno = Text $adr 'company' }
    $polozky = New-Object System.Collections.ArrayList
    foreach ($p in $f.SelectNodes("./*[local-name()='invoiceDetail']/*[local-name()='invoiceItem']")) {
        $nazev = Text $p 'text'
        $ks = Cislo (Text $p 'quantity')
        $celkem = Cislo (Text $p 'homeCurrency/priceSum')
        if (-not (Uzel $p 'stockItem')) { continue }
        if ($ks -le 0 -or $celkem -le 0) { continue }
        if ($nazev -match $NeniZbozi) { continue }
        [void]$polozky.Add([ordered]@{ code = (Text $p 'code'); name = $nazev; qty = $ks; unit_price = [math]::Round($celkem / $ks, 2) })
    }
    if ($polozky.Count -eq 0) { return $null }
    $s = Uzel $f 'invoiceSummary/homeCurrency'
    $total = (Cislo (Text $s 'priceNone')) + (Cislo (Text $s 'priceLowSum')) + (Cislo (Text $s 'priceHighSum')) + (Cislo (Text $s 'round/priceRound'))
    return [ordered]@{
        invoice_no    = (Text $h 'number/numberRequested')
        order_no      = $obj
        invoice_date  = (Text $h 'date')
        email         = $email
        customer_name = $jmeno
        phone         = (Text $adr 'phone')
        total         = [math]::Round($total, 2)
        items         = $polozky
    }
}

function Posli($davka) {
    $json = ConvertTo-Json -InputObject ([ordered]@{ orders = $davka }) -Depth 6 -Compress
    $odp = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/objednavky') -Method Post `
           -Body ([Text.Encoding]::UTF8.GetBytes($json)) -ContentType 'application/json; charset=utf-8' `
           -Headers @{ Authorization = "Bearer $token" } -TimeoutSec 120
    return $odp
}

try {
    $d = [datetime]::ParseExact($Od, 'yyyy-MM-dd', $null)
    $dnes = (Get-Date).Date
    $celkemPoslano = 0; $celkemFaktur = 0; $chyby = @()
    while ($d -le $dnes) {
        $konec = $d.AddMonths(1).AddDays(-1); if ($konec -gt $dnes) { $konec = $dnes }
        $faktury = Nacti-Faktury $d $konec
        $celkemFaktur += $faktury.Count
        $objednavky = New-Object System.Collections.ArrayList
        foreach ($f in $faktury) { $o = Na-Objednavku $f; if ($o) { [void]$objednavky.Add($o) } }
        for ($i = 0; $i -lt $objednavky.Count; $i += 50) {
            $davka = New-Object System.Collections.ArrayList
            $objednavky.GetRange($i, [math]::Min(50, $objednavky.Count - $i)) | ForEach-Object { [void]$davka.Add($_) }
            $r = Posli $davka
            $celkemPoslano += [int]$r.saved
            if ($r.errors) { $chyby += @($r.errors) }
        }
        Zapis-Log $uloha ('{0:yyyy-MM-dd}–{1:yyyy-MM-dd}: faktur {2}, e-shopových {3}' -f $d, $konec, $faktury.Count, $objednavky.Count)
        $d = $konec.AddDays(1)
    }
    Set-Content -LiteralPath $stavF -Value (@{ posledni = (Get-Date).ToString('s'); od = $Od } | ConvertTo-Json) -Encoding UTF8
    $zprava = 'OK    od {0}: faktur {1}, uloženo objednávek v portálu {2}' -f $Od, $celkemFaktur, $celkemPoslano
    if ($chyby) { $zprava += (' | portál odmítl: ' + (($chyby | Select-Object -First 5) -join '; ')) }
    Zapis-Log $uloha $zprava
    Write-Host $zprava -ForegroundColor Green
} catch {
    Zapis-Log $uloha ('CHYBA {0}' -f $_.Exception.Message)
    Write-Host ('CHYBA ' + $_.Exception.Message) -ForegroundColor Red
}
