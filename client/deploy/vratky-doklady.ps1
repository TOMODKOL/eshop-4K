# Doklady k vratkám z client.4kluciodkol.cz do Pohody (přes mServer).
# Prodejna v Clientu potvrdí „Zboží je v pořádku – vystavit doklad“ → tento skript:
#   - vrací se peníze (součet záporný)  → opravný daňový doklad, řada 2604 (jako ODD 260400245)
#   - výměna / doplatek (součet 0 a víc) → vydaná faktura, řada 2601
# Údaje odběratele, středisko, činnost a ceny položek bere z původní vydané faktury.
#
# Režim NÁVRH (výchozí): doklad se do Pohody NEZAPÍŠE, jen se uloží XML do Pomocnik\Client\doklady-navrh ke kontrole.
# Ostrý režim zapne Otík po schválení prvního dokladu: soubor stav\vratky_doklady.json  {"ostre": true}
# Spouští se z reklamace-sync.ps1 (každých 5 minut). Ručně:
#   cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\vratky-doklady.ps1
param([string]$Portal = 'https://client.4kluciodkol.cz', [switch]$Ostre)
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$uloha = 'vratky_doklady'

$hodina = (Get-Date).Hour
if ($hodina -lt $MServerOd -or $hodina -ge $MServerDo) { return }

$nastF = Join-Path $StavDir 'vratky_doklady.json'
if (-not $Ostre -and (Test-Path -LiteralPath $nastF)) {
    $Ostre = [bool](Get-Content -LiteralPath $nastF -Raw -Encoding UTF8 | ConvertFrom-Json).ostre
}
$hotoveF = Join-Path $StavDir 'vratky_doklady_hotove.json'   # pojistka proti dvojímu zápisu: číslo vratky → číslo dokladu
$hotove = @{}
if (Test-Path -LiteralPath $hotoveF) {
    (Get-Content -LiteralPath $hotoveF -Raw -Encoding UTF8 | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $hotove[$_.Name] = $_.Value }
}
$navrhDir = Join-Path $Data 'Client\doklady-navrh'

$token = (Nacti-Udaj 'client_klice').GetNetworkCredential().Password
$apiHlav = @{ Authorization = "Bearer $token" }
$udaj  = Nacti-Udaj 'mserver'
$kod   = [Text.Encoding]::GetEncoding($MServerKodovaniPrihlaseni)
$auth  = [Convert]::ToBase64String($kod.GetBytes(('{0}:{1}' -f $udaj.UserName, $udaj.GetNetworkCredential().Password)))
$hlav  = @{ 'STW-Authorization' = "Basic $auth"; 'STW-Application' = 'Pomocnik 4K' }
$url   = $MServerAdresa.TrimEnd('/') + '/xml'
$cp1250 = [Text.Encoding]::GetEncoding('windows-1250')

function Uzel($n, [string]$cesta) {
    if ($null -eq $n) { return $null }
    $xp = './' + ((($cesta -split '/') | ForEach-Object { "*[local-name()='$_']" }) -join '/')
    return $n.SelectSingleNode($xp)
}
function Uzly($n, [string]$cesta) {
    $xp = './' + ((($cesta -split '/') | ForEach-Object { "*[local-name()='$_']" }) -join '/')
    return $n.SelectNodes($xp)
}
function Text($n, [string]$cesta) { $u = Uzel $n $cesta; if ($u) { $u.InnerText.Trim() } else { '' } }
function X([string]$s) { [Security.SecurityElement]::Escape($s) }
function Cislo([double]$d) { $d.ToString('0.##', [Globalization.CultureInfo]::InvariantCulture) }

function MServer([string]$id, [string]$obsah, [string]$poznamka) {
    $xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="$id" ico="08576726" application="Pomocnik 4K" version="2.0" note="$(X $poznamka)"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"
  xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:lStk="http://www.stormware.cz/schema/version_2/list_stock.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd"
  xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd"
  xmlns:typ="http://www.stormware.cz/schema/version_2/type.xsd">
$obsah
</dat:dataPack>
"@
    $odp = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 300
    $doc = New-Object Xml.XmlDocument
    $doc.LoadXml($cp1250.GetString($odp.RawContentStream.ToArray()))
    return $doc
}

# Bankovní účet zákazníka → paymentAccount (číslo/kód banky, i CZ IBAN)
function Ucet([string]$u) {
    $u = ($u -replace '\s', '').ToUpper()
    if ($u -match '^((\d{1,6})-)?(\d{2,10})/(\d{4})$') { return @{ cislo = $(if ($matches[2]) { "$($matches[2])-$($matches[3])" } else { $matches[3] }); banka = $matches[4] } }
    if ($u -match '^CZ\d{2}(\d{4})(\d{6})(\d{10})$') {
        $pre = $matches[2].TrimStart('0'); $c = $matches[3].TrimStart('0')
        return @{ cislo = $(if ($pre) { "$pre-$c" } else { $c }); banka = $matches[1] }
    }
    return $null
}

function Zpet([string]$no, [string]$stav, [string]$cislo, [string]$chyba) {
    $json = ConvertTo-Json -InputObject @{ results = @(@{ no = $no; state = $stav; doc_no = $cislo; error = $chyba }) } -Depth 5 -Compress
    Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/doklady') -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
        -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 60 | Out-Null
}

try {
    $q = if ($Ostre) { '?navrhy=1' } else { '' }
    $seznam = @((Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/doklady' + $q) -Headers $apiHlav -TimeoutSec 60).returns)
    if ($seznam.Count -eq 0) { return }

    foreach ($r in $seznam) {
        $no = [string]$r.no
        try {
            if ($hotove.ContainsKey($no)) { Zpet $no 'hotovo' ([string]$hotove[$no]) ''; continue }

            # --- původní faktura ---
            $fvNo = ([string]$r.invoice_no) -replace "[^0-9A-Za-z]", ''
            $d = MServer ("vratka_fv_" + $no) ("<dat:dataPackItem id=`"fv`" version=`"2.0`"><lst:listInvoiceRequest version=`"2.0`" invoiceType=`"issuedInvoice`" invoiceVersion=`"2.0`"><lst:requestInvoice><ftr:queryFilter><ftr:filter>FA.Cislo = '$fvNo'</ftr:filter></ftr:queryFilter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>") 'Vratky - cteni faktury'
            $fv = $d.SelectSingleNode("//*[local-name()='invoice' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")
            if (-not $fv) { throw "Faktura $fvNo v Pohodě nenalezena." }
            $h = Uzel $fv 'invoiceHeader'
            if (Uzel $fv 'invoiceSummary/foreignCurrency') { throw "Faktura $fvNo je v cizí měně – doklad vystavte ručně." }
            $fvPolozky = @{}
            foreach ($p in (Uzly $fv 'invoiceDetail/invoiceItem')) { $c = Text $p 'code'; if ($c -and -not $fvPolozky.ContainsKey($c)) { $fvPolozky[$c] = $p } }

            # --- položky ---
            $radky = New-Object System.Text.StringBuilder
            $soucet = 0.0
            foreach ($i in $r.items) {
                $mn = [double]$i.qty
                if ($i.kind -eq 'zbozi' -or $i.kind -eq 'nove') {
                    $zdrojKod = if ($i.kind -eq 'nove') { [string]$i.for_code } else { [string]$i.code }
                    $p = $fvPolozky[$zdrojKod]
                    if (-not $p) { throw "Položka s kódem $zdrojKod není na faktuře $fvNo." }
                    $cena = [double]::Parse((Text $p 'homeCurrency/unitPrice'), [Globalization.CultureInfo]::InvariantCulture)
                    $sleva = Text $p 'discountPercentage'; if (-not $sleva) { $sleva = '0' }
                    $sazba = Text $p 'rateVAT'; $platiDph = Text $p 'payVAT'; if (-not $platiDph) { $platiDph = 'true' }
                    if ($i.kind -eq 'nove' -and $i.other) {
                        # výměna za jiné zboží: cena z e-shopu (s DPH, bez slevy), sazba DPH podle vraceného zboží
                        $cena = [double]$i.unit_price; $sleva = '0'
                    }
                    $nazev = if ($i.kind -eq 'nove') { [string]$i.name } else { Text $p 'text' }
                    $kodZ = [string]$i.code
                    if ($i.kind -eq 'nove') {
                        $s = MServer ("vratka_karta_" + $no) ("<dat:dataPackItem id=`"k`" version=`"2.0`"><lStk:listStockRequest version=`"2.0`" stockVersion=`"2.0`"><lStk:requestStock><ftr:filter><ftr:code>$(X $kodZ)</ftr:code><ftr:store><typ:ids>SKLAD</typ:ids></ftr:store></ftr:filter></lStk:requestStock></lStk:listStockRequest></dat:dataPackItem>") 'Vratky - karta nove velikosti'
                        $k = $s.SelectSingleNode("//*[local-name()='stockHeader']")
                        if (-not $k) { throw "Karta $kodZ (nová velikost) není ve skladu SKLAD." }
                        $nazev = Text $k 'name'
                    }
                    [void]$radky.Append(@"
<inv:invoiceItem><inv:text>$(X $nazev)</inv:text><inv:quantity>$(Cislo $mn)</inv:quantity><inv:unit>ks</inv:unit><inv:payVAT>$platiDph</inv:payVAT><inv:rateVAT>$sazba</inv:rateVAT><inv:discountPercentage>$sleva</inv:discountPercentage><inv:homeCurrency><typ:unitPrice>$(Cislo $cena)</typ:unitPrice></inv:homeCurrency><inv:code>$(X $kodZ)</inv:code><inv:stockItem><typ:store><typ:ids>SKLAD</typ:ids></typ:store><typ:stockItem><typ:ids>$(X $kodZ)</typ:ids></typ:stockItem></inv:stockItem></inv:invoiceItem>
"@)
                    $soucet += $mn * $cena * (1 - [double]::Parse($sleva, [Globalization.CultureInfo]::InvariantCulture) / 100)
                } else {
                    # poštovné za vrácení (+), doprava navíc (+), dobírka (+), vrácení dopravy (−) – textové položky, 21 %
                    $cena = [double]$i.unit_price
                    [void]$radky.Append(@"
<inv:invoiceItem><inv:text>$(X ([string]$i.name))</inv:text><inv:quantity>$(Cislo $mn)</inv:quantity><inv:unit>ks</inv:unit><inv:payVAT>true</inv:payVAT><inv:rateVAT>high</inv:rateVAT><inv:homeCurrency><typ:unitPrice>$(Cislo $cena)</typ:unitPrice></inv:homeCurrency></inv:invoiceItem>
"@)
                    $soucet += $mn * $cena
                }
            }
            $odd = $soucet -lt 0
            if (($odd -and $r.type -ne 'odd') -or (-not $odd -and $r.type -ne 'fv')) { throw ("Součet z faktury ({0} Kč) nesedí s typem dokladu v Clientu – zkontrolujte ceny." -f (Cislo $soucet)) }

            # --- hlavička ---
            $dnes = (Get-Date).ToString('yyyy-MM-dd')
            $partner = (Uzel $h 'partnerIdentity').OuterXml
            $stredisko = Text $h 'centre/ids'; $cinnost = Text $h 'activity/ids'
            $objCislo = Text $h 'numberOrder'; $objDatum = Text $h 'dateOrder'
            $ucetX = ''
            if ($odd) {
                $u = Ucet ([string]$r.bank_account)
                if (-not $u) { throw "Číslo účtu zákazníka '$($r.bank_account)' neumím zapsat – doklad vystavte ručně." }
                $ucetX = "<inv:paymentAccount><typ:accountNo>$($u.cislo)</typ:accountNo><typ:bankCode>$($u.banka)</typ:bankCode></inv:paymentAccount>"
            }
            $text = if ($odd) { "Opravný daňový doklad k daňovému dokladu č. $fvNo`nDůvod opravy: Vratka eshop" } else { "Výměna zboží k faktuře č. $fvNo" }
            $hlavicka = @"
<inv:invoiceHeader>
<inv:invoiceType>$(if ($odd) { 'issuedCorrectiveTax' } else { 'issuedInvoice' })</inv:invoiceType>
<inv:number><typ:ids>$(if ($odd) { '2604' } else { '2601' })</typ:ids></inv:number>
<inv:date>$dnes</inv:date><inv:dateTax>$dnes</inv:dateTax><inv:dateAccounting>$dnes</inv:dateAccounting><inv:dateKHDPH>$dnes</inv:dateKHDPH>
<inv:dateDue>$(if ($odd) { (Get-Date).AddDays(1).ToString('yyyy-MM-dd') } else { $dnes })</inv:dateDue><inv:dateApplicationVAT>$dnes</inv:dateApplicationVAT>
<inv:accounting><typ:ids>3Fv</typ:ids></inv:accounting><inv:classificationVAT><typ:ids>UD</typ:ids></inv:classificationVAT>
<inv:text>$(X $text)</inv:text>
$partner
$(if ($objCislo) { "<inv:numberOrder>$(X $objCislo)</inv:numberOrder>" })$(if ($objDatum) { "<inv:dateOrder>$objDatum</inv:dateOrder>" })
<inv:paymentType><typ:ids>Příkazem</typ:ids></inv:paymentType><inv:account><typ:ids>OBCZ</typ:ids></inv:account><inv:symConst>0308</inv:symConst>
$ucetX
$(if ($stredisko) { "<inv:centre><typ:ids>$(X $stredisko)</typ:ids></inv:centre>" })$(if ($cinnost) { "<inv:activity><typ:ids>$(X $cinnost)</typ:ids></inv:activity>" })
<inv:note>Vratka $no z client.4kluciodkol.cz</inv:note>
<inv:intNote>Vratka $no, faktura $fvNo, vystavil pomocník</inv:intNote>
</inv:invoiceHeader>
"@
            $obsah = @"
<dat:dataPackItem id="$no" version="2.0"><inv:invoice version="2.0">
$hlavicka
<inv:invoiceDetail>
$($radky.ToString())
</inv:invoiceDetail>
<inv:invoiceSummary><inv:roundingDocument>math2one</inv:roundingDocument><inv:roundingVAT>none</inv:roundingVAT></inv:invoiceSummary>
</inv:invoice></dat:dataPackItem>
"@
            if (-not $Ostre) {
                Zajisti-Slozku $navrhDir
                $f = Join-Path $navrhDir ("{0}_{1}.xml" -f $no, $(if ($odd) { 'ODD' } else { 'FV' }))
                [IO.File]::WriteAllText($f, $obsah, (New-Object Text.UTF8Encoding($true)))
                Zpet $no 'navrh' '' ''
                Zapis-Log $uloha ("NÁVRH {0}: {1} {2} Kč → {3}" -f $no, $(if ($odd) { 'ODD' } else { 'FV' }), (Cislo $soucet), $f)
                continue
            }

            $d = MServer ("vratka_" + $no) $obsah ("Doklad k vratce $no")
            $item = $d.SelectSingleNode("//*[local-name()='responsePackItem']")
            $ir = $d.SelectSingleNode("//*[local-name()='invoiceResponse']")
            $stav = if ($ir) { $ir.GetAttribute('state') } else { $item.GetAttribute('state') }
            $pd = $d.SelectSingleNode("//*[local-name()='producedDetails']")
            $cislo = if ($pd) { Text $pd 'number' } else { '' }
            if ($stav -eq 'error' -or -not $cislo) {
                $pozn = ($d.SelectNodes("//*[local-name()='detail']") | ForEach-Object { (Text $_ 'note') }) -join '; '
                if (-not $pozn) { $pozn = $item.GetAttribute('note') }
                throw "Pohoda doklad nevystavila: $pozn"
            }
            $hotove[$no] = $cislo
            Set-Content -LiteralPath $hotoveF -Value ($hotove | ConvertTo-Json) -Encoding UTF8
            Zpet $no 'hotovo' $cislo ''
            Zapis-Log $uloha ("OK    {0}: {1} {2} ({3} Kč)" -f $no, $(if ($odd) { 'ODD' } else { 'FV' }), $cislo, (Cislo $soucet))
        } catch {
            $m = $_.Exception.Message
            Zapis-Log $uloha ("CHYBA {0}: {1}" -f $no, $m)
            try { Zpet $no 'chyba' '' $m } catch { }
        }
    }
} catch {
    Zapis-Log $uloha ('CHYBA {0}' -f $_.Exception.Message)
}
