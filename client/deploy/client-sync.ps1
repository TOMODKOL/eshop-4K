# Pošle prodejní doklady z Pohody do portálu client.4kluciodkol.cz (vratky a reklamace).
#  - čte přes mServer (jen čtení) vydané faktury a prodejky
#      faktura s objednávkou z e-shopu (8810…)  -> zdroj eshop    (zákazník si vratku zadá sám na webu)
#      ostatní vydané faktury                   -> zdroj faktura  (reklamaci zakládá prodejna v administraci)
#      prodejky 26PH…                           -> zdroj prodejna (reklamaci zakládá prodejna v administraci)
#  - posílá je na https://client.4kluciodkol.cz/api/sync/objednavky (klíč = sync_token z nasad-client.ps1)
#  - první běh nahraje doklady od 1. 1. 2025 po měsících, další běhy jen posledních $Dni dní
# Ručně:  cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\client-sync.ps1 [-Od 2025-01-01]
# Ze souborů (XML export z Pohody, např. uzavřený rok):
#         powershell -ExecutionPolicy Bypass -File .\client-sync.ps1 -Soubory 'složka s XML'   (nebo cesta k jednomu souboru)
param([string]$Od = '', [int]$Dni = 4, [string]$Portal = 'https://client.4kluciodkol.cz', [string[]]$Soubory = @())
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$uloha = 'client_sync'

$hodina = (Get-Date).Hour
if (-not $Soubory -and ($hodina -lt $MServerOd -or $hodina -ge $MServerDo)) { return }
$nejstarsi = (Get-Date).Date.AddMonths(-25).ToString('yyyy-MM-dd')   # starší doklady už reklamovat nejde

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

function Dotaz([string]$polozka) {
    $xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="client_sync" ico="08576726" application="Pomocnik 4K" version="2.0" note="Doklady pro client.4kluciodkol.cz - jen cteni"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"
  xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
  <dat:dataPackItem id="d" version="2.0">$polozka</dat:dataPackItem>
</dat:dataPack>
"@
    $odp = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 900
    $doc = New-Object Xml.XmlDocument
    $doc.LoadXml($cp1250.GetString($odp.RawContentStream.ToArray()))
    $st = $doc.SelectSingleNode("//*[local-name()='responsePackItem']")
    if ($st -and $st.GetAttribute('state') -ne 'ok') { throw ('Pohoda vrátila stav ' + $st.GetAttribute('state') + ': ' + $st.GetAttribute('note')) }
    return $doc
}
function Filtr([datetime]$d1, [datetime]$d2) { "<ftr:filter><ftr:dateFrom>$($d1.ToString('yyyy-MM-dd'))</ftr:dateFrom><ftr:dateTill>$($d2.ToString('yyyy-MM-dd'))</ftr:dateTill></ftr:filter>" }

function Nacti-Faktury([datetime]$d1, [datetime]$d2) {
    $doc = Dotaz ('<lst:listInvoiceRequest version="2.0" invoiceType="issuedInvoice" invoiceVersion="2.0"><lst:requestInvoice>' + (Filtr $d1 $d2) + '</lst:requestInvoice></lst:listInvoiceRequest>')
    return $doc.SelectNodes("//*[local-name()='invoice' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")
}
function Nacti-Prodejky([datetime]$d1, [datetime]$d2) {
    $doc = Dotaz ('<lst:listProdejkaRequest version="2.0" prodejkaVersion="2.0"><lst:requestProdejka>' + (Filtr $d1 $d2) + '</lst:requestProdejka></lst:listProdejkaRequest>')
    return $doc.SelectNodes("//*[local-name()='prodejka' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")
}

# Zboží z položek dokladu (bez dopravy, platby, poukazů a vratek se záporným množstvím)
function Polozky($doklad, [string]$detail, [string]$polozka) {
    $polozky = New-Object System.Collections.ArrayList
    foreach ($p in $doklad.SelectNodes("./*[local-name()='$detail']/*[local-name()='$polozka']")) {
        $nazev = Text $p 'text'
        $ks = Cislo (Text $p 'quantity')
        $celkem = Cislo (Text $p 'homeCurrency/priceSum')
        if (-not (Uzel $p 'stockItem')) { continue }
        if ($ks -le 0 -or $celkem -le 0) { continue }
        if ($nazev -match $NeniZbozi) { continue }
        [void]$polozky.Add([ordered]@{ code = (Text $p 'code'); name = $nazev; qty = $ks; unit_price = [math]::Round($celkem / $ks, 2) })
    }
    return ,$polozky
}
function Soucet($s) {
    return [math]::Round((Cislo (Text $s 'priceNone')) + (Cislo (Text $s 'priceLowSum')) + (Cislo (Text $s 'priceHighSum')) + (Cislo (Text $s 'round/priceRound')), 2)
}

function Z-Faktury($f) {
    $h = Uzel $f 'invoiceHeader'
    $typ = Text $h 'invoiceType'
    if ($typ -and $typ -ne 'issuedInvoice') { return $null }      # dobropisy apod.
    if ((Text $h 'date') -lt $nejstarsi) { return $null }
    $obj = Text $h 'numberOrder'
    if (-not $obj) { $obj = Text $h 'symVar' }
    $eshop = $obj -match '^8810\d+$'
    $adr = Uzel $h 'partnerIdentity/address'
    $email = Text $adr 'email'
    if (-not $email) { $email = Text $h 'partnerIdentity/shipToAddress/email' }
    $jmeno = Text $adr 'name'; if (-not $jmeno) { $jmeno = Text $adr 'company' }
    if ($eshop -and -not $email) { $eshop = $false }              # bez e-mailu ji zákazník na webu nenajde – zakládá prodejna
    $polozky = Polozky $f 'invoiceDetail' 'invoiceItem'
    if ($polozky.Count -eq 0) { return $null }
    return [ordered]@{
        invoice_no    = (Text $h 'number/numberRequested')
        order_no      = $(if ($obj -match '^8810\d+$') { $obj } else { '' })
        source        = $(if ($eshop) { 'eshop' } else { 'faktura' })
        invoice_date  = (Text $h 'date')
        email         = $email
        customer_name = $jmeno
        phone         = (Text $adr 'phone')
        total         = (Soucet (Uzel $f 'invoiceSummary/homeCurrency'))
        items         = $polozky
    }
}

function Z-Prodejky($p) {
    $h = Uzel $p 'prodejkaHeader'
    if ((Text $h 'date') -lt $nejstarsi) { return $null }
    $polozky = Polozky $p 'prodejkaDetail' 'prodejkaItem'
    if ($polozky.Count -eq 0) { return $null }
    $adr = Uzel $h 'partnerIdentity/address'
    $jmeno = Text $adr 'name'; if (-not $jmeno) { $jmeno = Text $adr 'company' }
    return [ordered]@{
        invoice_no    = (Text $h 'number/numberRequested')
        order_no      = ''
        source        = 'prodejna'
        invoice_date  = (Text $h 'date')
        email         = (Text $adr 'email')
        customer_name = $jmeno
        phone         = (Text $adr 'phone')
        total         = (Soucet (Uzel $p 'prodejkaSummary/homeCurrency'))
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

function Posli-Vse($doklady) {
    $ulozeno = 0
    for ($i = 0; $i -lt $doklady.Count; $i += 50) {
        $davka = New-Object System.Collections.ArrayList
        $doklady.GetRange($i, [math]::Min(50, $doklady.Count - $i)) | ForEach-Object { [void]$davka.Add($_) }
        $r = Posli $davka
        $ulozeno += [int]$r.saved
        if ($r.errors) { $script:chyby += @($r.errors) }
    }
    return $ulozeno
}

# -File předá seznam jako jeden text s čárkami; složka = všechny .xml v ní
$Soubory = @($Soubory | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ } | ForEach-Object {
    if (Test-Path -LiteralPath $_ -PathType Container) { Get-ChildItem -LiteralPath $_ -Filter *.xml | ForEach-Object { $_.FullName } } else { $_ }
})
if ($Soubory) {
    try {
        $script:chyby = @(); $pocty = @{ eshop = 0; faktura = 0; prodejna = 0 }; $celkemPoslano = 0
        foreach ($cesta in $Soubory) {
            $doc = New-Object Xml.XmlDocument
            $doc.Load((Resolve-Path -LiteralPath $cesta).Path)
            $L = 'http://www.stormware.cz/schema/version_2/list.xsd'
            $doklady = New-Object System.Collections.ArrayList
            foreach ($f in $doc.SelectNodes("//*[local-name()='invoice' and namespace-uri()='$L']"))  { $o = Z-Faktury $f;  if ($o) { [void]$doklady.Add($o) } }
            foreach ($p in $doc.SelectNodes("//*[local-name()='prodejka' and namespace-uri()='$L']")) { $o = Z-Prodejky $p; if ($o) { [void]$doklady.Add($o) } }
            foreach ($o in $doklady) { $pocty[$o.source]++ }
            $n = Posli-Vse $doklady
            $celkemPoslano += $n
            Zapis-Log $uloha ('soubor {0}: dokladů {1}, uloženo {2}' -f (Split-Path $cesta -Leaf), $doklady.Count, $n)
        }
        $zprava = 'OK    ze souborů: e-shop {0}, faktury {1}, prodejky {2}; uloženo v portálu {3}' -f $pocty.eshop, $pocty.faktura, $pocty.prodejna, $celkemPoslano
        if ($script:chyby) { $zprava += (' | portál odmítl ' + $script:chyby.Count + ': ' + (($script:chyby | Select-Object -First 5) -join '; ')) }
        Zapis-Log $uloha $zprava
        Write-Host $zprava -ForegroundColor Green
    } catch {
        Zapis-Log $uloha ('CHYBA {0}' -f $_.Exception.Message)
        Write-Host ('CHYBA ' + $_.Exception.Message) -ForegroundColor Red
    }
    return
}

try {
    $d = [datetime]::ParseExact($Od, 'yyyy-MM-dd', $null)
    $dnes = (Get-Date).Date
    $celkemPoslano = 0; $pocty = @{ eshop = 0; faktura = 0; prodejna = 0 }; $script:chyby = @()
    while ($d -le $dnes) {
        $konec = $d.AddMonths(1).AddDays(-1); if ($konec -gt $dnes) { $konec = $dnes }
        $doklady = New-Object System.Collections.ArrayList
        foreach ($f in (Nacti-Faktury $d $konec))  { $o = Z-Faktury $f;   if ($o) { [void]$doklady.Add($o) } }
        foreach ($p in (Nacti-Prodejky $d $konec)) { $o = Z-Prodejky $p;  if ($o) { [void]$doklady.Add($o) } }
        foreach ($o in $doklady) { $pocty[$o.source]++ }
        $celkemPoslano += Posli-Vse $doklady
        Zapis-Log $uloha ('{0:yyyy-MM-dd}–{1:yyyy-MM-dd}: e-shop {2}, faktury {3}, prodejky {4}' -f $d, $konec,
            @($doklady | Where-Object { $_.source -eq 'eshop' }).Count, @($doklady | Where-Object { $_.source -eq 'faktura' }).Count, @($doklady | Where-Object { $_.source -eq 'prodejna' }).Count)
        $d = $konec.AddDays(1)
    }
    Set-Content -LiteralPath $stavF -Value (@{ posledni = (Get-Date).ToString('s'); od = $Od } | ConvertTo-Json) -Encoding UTF8
    $zprava = 'OK    od {0}: e-shop {1}, faktury {2}, prodejky {3}; uloženo v portálu {4}' -f $Od, $pocty.eshop, $pocty.faktura, $pocty.prodejna, $celkemPoslano
    if ($script:chyby) { $zprava += (' | portál odmítl ' + $script:chyby.Count + ': ' + (($script:chyby | Select-Object -First 5) -join '; ')) }
    Zapis-Log $uloha $zprava
    Write-Host $zprava -ForegroundColor Green
} catch {
    Zapis-Log $uloha ('CHYBA {0}' -f $_.Exception.Message)
    Write-Host ('CHYBA ' + $_.Exception.Message) -ForegroundColor Red
}
