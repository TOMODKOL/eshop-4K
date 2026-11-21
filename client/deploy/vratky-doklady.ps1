# Doklady k vratkám z client.4kluciodkol.cz do Pohody (přes mServer).
# Prodejna v Clientu potvrdí „Zboží je v pořádku – vystavit doklad“ → tento skript:
#   - vrací se peníze (součet záporný)  → opravný daňový doklad, řada 2604 (jako ODD 260400245)
#   - výměna / doplatek (součet 0 a víc) → vydaná faktura, řada 2601
# Údaje odběratele, středisko, činnost a ceny položek bere z původní vydané faktury.
#
# Režim NÁVRH (výchozí): doklad se do Pohody NEZAPÍŠE, jen se uloží XML do Pomocnik\Client\doklady-navrh ke kontrole.
# Ostrý režim zapne Otík po schválení prvního dokladu: soubor stav\vratky_doklady.json  {"ostre": true}
#
# Výměny (od 21. 11. 2026): hned po odeslání vratky založí přijatou objednávku 991… (číslo dává Client, zboží se zarezervuje)
# a při doplatku převodem zálohovou fakturu v řadě 2608 (VS = její číslo). Hlídá zaplacení zálohy (FA.KcLikv = 0).
# Konečná faktura pak vyřídí položky objednávky a odečte zaplacenou zálohu.
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
  xmlns:ord="http://www.stormware.cz/schema/version_2/order.xsd"
  xmlns:int="http://www.stormware.cz/schema/version_2/intDoc.xsd"
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

function ZpetObj([string]$no, [string]$stav, [string]$zf, [string]$chyba) {
    $json = ConvertTo-Json -InputObject @{ results = @(@{ no = $no; state = $stav; zf_no = $zf; error = $chyba }) } -Depth 5 -Compress
    Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/objednavky') -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
        -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 60 | Out-Null
}
# Odpověď Pohody na import → číslo vzniklého dokladu, jinak výjimka s důvodem
function Vysledek($d, [string]$co) {
    $item = $d.SelectSingleNode("//*[local-name()='responsePackItem']")
    $pd = $d.SelectSingleNode("//*[local-name()='producedDetails']")
    $cislo = if ($pd) { Text $pd 'number' } else { '' }
    $chyba = @($d.SelectNodes("//*[@state='error']")).Count -gt 0
    if ($chyba -or -not $cislo) {
        $pozn = ($d.SelectNodes("//*[local-name()='detail']") | ForEach-Object { (Text $_ 'note') }) -join '; '
        if (-not $pozn -and $item) { $pozn = $item.GetAttribute('note') }
        throw "Pohoda $co nezaložila: $pozn"
    }
    return $cislo
}
function PuvodniFaktura([string]$no, [string]$fvNo) {
    $d = MServer ("vratka_fv_" + $no) ("<dat:dataPackItem id=`"fv`" version=`"2.0`"><lst:listInvoiceRequest version=`"2.0`" invoiceType=`"issuedInvoice`" invoiceVersion=`"2.0`"><lst:requestInvoice><ftr:queryFilter><ftr:filter>FA.Cislo = '$fvNo'</ftr:filter></ftr:queryFilter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>") 'Vratky - cteni faktury'
    $fv = $d.SelectSingleNode("//*[local-name()='invoice' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']")
    if (-not $fv) { throw "Faktura $fvNo v Pohodě nenalezena." }
    if (Uzel $fv 'invoiceSummary/foreignCurrency') { throw "Faktura $fvNo je v cizí měně – doklady vystavte ručně." }
    return $fv
}

function Zpet([string]$no, [string]$stav, [string]$cislo, [string]$chyba) {
    $json = ConvertTo-Json -InputObject @{ results = @(@{ no = $no; state = $stav; doc_no = $cislo; error = $chyba }) } -Depth 5 -Compress
    Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/doklady') -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
        -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 60 | Out-Null
}

# ===== 1. Výměny: přijatá objednávka 991… + zálohová faktura na doplatek, hlídání platby =====
try {
    $q = if ($Ostre) { '?navrhy=1' } else { '' }
    $obj = @((Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/objednavky' + $q) -Headers $apiHlav -TimeoutSec 60).returns)
    foreach ($r in $obj) {
        $no = [string]$r.no
        try {
            # --- založená objednávka se zálohou: zaplaceno? (KcLikv = zbývá uhradit) ---
            if ([string]$r.state -eq 'hotovo') {
                if (-not $Ostre -or -not $r.zf_no) { continue }
                $zf = ([string]$r.zf_no) -replace '[^0-9]', ''
                $d = MServer ("vratka_zf_" + $no) ("<dat:dataPackItem id=`"zf`" version=`"2.0`"><lst:listInvoiceRequest version=`"2.0`" invoiceType=`"issuedAdvanceInvoice`" invoiceVersion=`"2.0`"><lst:requestInvoice><ftr:queryFilter><ftr:filter>FA.Cislo = '$zf' AND FA.KcLikv = 0</ftr:filter></ftr:queryFilter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>") 'Vratky - zaplaceni zalohy'
                if ($d.SelectSingleNode("//*[local-name()='invoiceHeader']")) {
                    ZpetObj $no 'zaplaceno' $zf ''
                    Zapis-Log $uloha ("OK    {0}: záloha {1} zaplacena" -f $no, $zf)
                }
                continue
            }
            $xo = ([string]$r.xorder_no) -replace '[^0-9]', ''
            $fvNo = ([string]$r.invoice_no) -replace "[^0-9A-Za-z]", ''
            $fv = PuvodniFaktura $no $fvNo
            $h = Uzel $fv 'invoiceHeader'
            $fvPolozky = @{}
            foreach ($p in (Uzly $fv 'invoiceDetail/invoiceItem')) { $c = Text $p 'code'; if ($c -and -not $fvPolozky.ContainsKey($c)) { $fvPolozky[$c] = $p } }
            $partner = (Uzel $h 'partnerIdentity').OuterXml -replace '<inv:partnerIdentity', '<ord:partnerIdentity' -replace '</inv:partnerIdentity>', '</ord:partnerIdentity>'
            $stredisko = Text $h 'centre/ids'; $cinnost = Text $h 'activity/ids'
            $dnes = (Get-Date).ToString('yyyy-MM-dd')

            # --- položky objednávky = nové zboží ---
            $radky = New-Object System.Text.StringBuilder
            $sazbaZal = 'high'
            foreach ($i in $r.items) {
                $p = $fvPolozky[[string]$i.for_code]
                if (-not $p) { throw "Položka s kódem $($i.for_code) není na faktuře $fvNo." }
                $sazba = Text $p 'rateVAT'; if (-not $sazba) { $sazba = 'high' }; $sazbaZal = $sazba
                if ($i.other) { $cena = [double]$i.unit_price; $sleva = '0' }
                else {
                    $cena = [double]::Parse((Text $p 'homeCurrency/unitPrice'), [Globalization.CultureInfo]::InvariantCulture)
                    $sleva = Text $p 'discountPercentage'; if (-not $sleva) { $sleva = '0' }
                }
                $kodZ = [string]$i.code
                $sklad = if ($kodZ) { "<ord:code>$(X $kodZ)</ord:code><ord:stockItem><typ:store><typ:ids>SKLAD</typ:ids></typ:store><typ:stockItem><typ:ids>$(X $kodZ)</typ:ids></typ:stockItem></ord:stockItem>" } else { '' }
                [void]$radky.Append(@"
<ord:orderItem><ord:text>$(X ([string]$i.name))</ord:text><ord:quantity>$(Cislo ([double]$i.qty))</ord:quantity><ord:delivered>0</ord:delivered><ord:unit>ks</ord:unit><ord:payVAT>true</ord:payVAT><ord:rateVAT>$sazba</ord:rateVAT><ord:discountPercentage>$sleva</ord:discountPercentage><ord:homeCurrency><typ:unitPrice>$(Cislo $cena)</typ:unitPrice></ord:homeCurrency>$sklad</ord:orderItem>
"@)
            }
            $platba = if ([string]$r.pay_method -eq 'dobirka') { 'Dobírkou' } else { 'Příkazem' }
            $objXml = @"
<dat:dataPackItem id="obj_$no" version="2.0"><ord:order version="2.0">
<ord:orderHeader>
<ord:orderType>receivedOrder</ord:orderType>
<ord:number><typ:numberRequested checkDuplicity="true">$xo</typ:numberRequested></ord:number>
<ord:numberOrder>$xo</ord:numberOrder>
<ord:date>$dnes</ord:date>
<ord:text>$(X "Výměna k obj. $($r.order_no) (faktura $fvNo) – vratka $no")</ord:text>
$partner
<ord:paymentType><typ:ids>$platba</typ:ids></ord:paymentType>
<ord:isReserved>true</ord:isReserved>
$(if ($stredisko) { "<ord:centre><typ:ids>$(X $stredisko)</typ:ids></ord:centre>" })$(if ($cinnost) { "<ord:activity><typ:ids>$(X $cinnost)</typ:ids></ord:activity>" })
<ord:note>Vratka $no z client.4kluciodkol.cz</ord:note>
<ord:intNote>Výměna – nové zboží se odešle, až dorazí vrácené zboží$(if ([double]$r.advance -gt 0) { ' a doplatek' }).</ord:intNote>
</ord:orderHeader>
<ord:orderDetail>
$($radky.ToString())
</ord:orderDetail>
</ord:order></dat:dataPackItem>
"@
            # --- zálohová faktura na doplatek (převodem) ---
            $zfXml = ''
            $zaloha = [double]$r.advance
            if ($zaloha -gt 0) {
                $partnerZ = (Uzel $h 'partnerIdentity').OuterXml
                $zfXml = @"
<dat:dataPackItem id="zf_$no" version="2.0"><inv:invoice version="2.0">
<inv:invoiceHeader>
<inv:invoiceType>issuedAdvanceInvoice</inv:invoiceType>
<inv:number><typ:ids>2608</typ:ids></inv:number>
<inv:date>$dnes</inv:date><inv:dateAccounting>$dnes</inv:dateAccounting><inv:dateDue>$((Get-Date).AddDays(7).ToString('yyyy-MM-dd'))</inv:dateDue>
<inv:accounting><typ:ids>Nevím</typ:ids></inv:accounting>
<inv:text>$(X "Záloha – doplatek za výměnu zboží, objednávka $xo (vratka $no)")</inv:text>
$partnerZ
<inv:numberOrder>$xo</inv:numberOrder><inv:dateOrder>$dnes</inv:dateOrder>
<inv:paymentType><typ:ids>Příkazem</typ:ids></inv:paymentType><inv:account><typ:ids>OBCZ</typ:ids></inv:account><inv:symConst>0308</inv:symConst>
$(if ($stredisko) { "<inv:centre><typ:ids>$(X $stredisko)</typ:ids></inv:centre>" })$(if ($cinnost) { "<inv:activity><typ:ids>$(X $cinnost)</typ:ids></inv:activity>" })
<inv:note>Vratka $no z client.4kluciodkol.cz</inv:note>
</inv:invoiceHeader>
<inv:invoiceDetail>
<inv:invoiceItem><inv:text>$(X "Doplatek za výměnu zboží – objednávka $xo")</inv:text><inv:quantity>1</inv:quantity><inv:unit>ks</inv:unit><inv:payVAT>true</inv:payVAT><inv:rateVAT>$sazbaZal</inv:rateVAT><inv:homeCurrency><typ:unitPrice>$(Cislo $zaloha)</typ:unitPrice></inv:homeCurrency></inv:invoiceItem>
</inv:invoiceDetail>
<inv:invoiceSummary><inv:roundingDocument>math2one</inv:roundingDocument><inv:roundingVAT>none</inv:roundingVAT></inv:invoiceSummary>
</inv:invoice></dat:dataPackItem>
"@
            }

            if (-not $Ostre) {
                Zajisti-Slozku $navrhDir
                $f = Join-Path $navrhDir ("{0}_OBJ_{1}.xml" -f $no, $xo)
                [IO.File]::WriteAllText($f, $objXml, (New-Object Text.UTF8Encoding($true)))
                if ($zfXml) { [IO.File]::WriteAllText((Join-Path $navrhDir ("{0}_ZF.xml" -f $no)), $zfXml, (New-Object Text.UTF8Encoding($true))) }
                ZpetObj $no 'navrh' '' ''
                Zapis-Log $uloha ("NÁVRH {0}: objednávka {1}{2}" -f $no, $xo, $(if ($zfXml) { (", záloha {0} Kč" -f (Cislo $zaloha)) } else { '' }))
                continue
            }

            if (-not $hotove.ContainsKey("OBJ-$no")) {
                $d = MServer ("vratka_obj_" + $no) $objXml ("Objednavka k vymene $no")
                $hotove["OBJ-$no"] = Vysledek $d 'objednávku'
                Set-Content -LiteralPath $hotoveF -Value ($hotove | ConvertTo-Json) -Encoding UTF8
            }
            $zfNo = ''
            if ($zfXml) {
                if ($hotove.ContainsKey("ZF-$no")) { $zfNo = [string]$hotove["ZF-$no"] }
                else {
                    $d = MServer ("vratka_zf_" + $no) $zfXml ("Zaloha k vymene $no")
                    $zfNo = Vysledek $d 'zálohovou fakturu'
                    $hotove["ZF-$no"] = $zfNo
                    Set-Content -LiteralPath $hotoveF -Value ($hotove | ConvertTo-Json) -Encoding UTF8
                }
            }
            ZpetObj $no 'hotovo' $zfNo ''
            Zapis-Log $uloha ("OK    {0}: objednávka {1}{2}" -f $no, $xo, $(if ($zfNo) { ", záloha $zfNo" } else { '' }))
        } catch {
            $m = $_.Exception.Message
            Zapis-Log $uloha ("CHYBA {0} (objednávka): {1}" -f $no, $m)
            try { ZpetObj $no 'chyba' '' $m } catch { }
        }
    }
} catch {
    Zapis-Log $uloha ('CHYBA objednávky {0}' -f $_.Exception.Message)
}

# ===== 1b. Daňový doklad k přijaté platbě (26IN) ke každé celé zaplacené zálohové faktuře 2608 =====
# Jako ruční 26IN00321: text „Daňový doklad k přijaté platbě (zálohová faktura č. …)“, párový symbol = číslo zálohy,
# DUZP = den platby (datum likvidace), položka „Zdanitelné plnění - přijetí úplaty“ podle sazeb zálohy, předkontace Nevím, členění UD.
# Jen zálohy vystavené od $DdppOd (starší má prodejna vyřešené ručně). Když už k záloze 26IN s tímto párovým symbolem existuje, nic nedělá.
$DdppOd = '2026-11-21'
try {
    $d = MServer 'ddpp_zalohy' ("<dat:dataPackItem id=`"zf`" version=`"2.0`"><lst:listInvoiceRequest version=`"2.0`" invoiceType=`"issuedAdvanceInvoice`" invoiceVersion=`"2.0`"><lst:requestInvoice><ftr:queryFilter><ftr:filter>FA.Datum &gt;= '$DdppOd' AND FA.KcLikv = 0</ftr:filter></ftr:queryFilter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>") 'Zaplacene zalohy - jen cteni'
    $zalohy = @($d.SelectNodes("//*[local-name()='invoice' and namespace-uri()='http://www.stormware.cz/schema/version_2/list.xsd']"))
    if ($zalohy.Count -gt 0) {
        $di = MServer 'ddpp_interni' ("<dat:dataPackItem id=`"int`" version=`"2.0`"><lst:listIntDocRequest version=`"2.0`" intDocVersion=`"2.0`"><lst:requestIntDoc><ftr:filter><ftr:dateFrom>$DdppOd</ftr:dateFrom></ftr:filter></lst:requestIntDoc></lst:listIntDocRequest></dat:dataPackItem>") 'Interni doklady - jen cteni'
        $maDdpp = @{}
        foreach ($ih in $di.SelectNodes("//*[local-name()='intDocHeader']")) {
            $sp = Text $ih 'symPar'; if ($sp) { $maDdpp[$sp] = $true }
            if ((Text $ih 'text') -match 'zálohov\S* faktu\S* č\. ?(\d+)') { $maDdpp[$matches[1]] = $true }
        }
        foreach ($zf in $zalohy) {
            $zh = Uzel $zf 'invoiceHeader'
            $zfNo = Text $zh 'number/numberRequested'
            if (-not $zfNo -or $maDdpp.ContainsKey($zfNo)) { continue }
            if ($hotove.ContainsKey("DDPP-$zfNo") -and (-not $Ostre -or [string]$hotove["DDPP-$zfNo"] -ne 'navrh')) { continue }
            try {
                $den = Text $zh 'liquidation/date'; if (-not $den) { $den = (Get-Date).ToString('yyyy-MM-dd') }
                $dnes = (Get-Date).ToString('yyyy-MM-dd')
                # součet zálohy po sazbách DPH (ceny s DPH)
                $sazby = [ordered]@{}
                foreach ($zp in (Uzly $zf 'invoiceDetail/invoiceItem')) {
                    $sz = Text $zp 'rateVAT'; if (-not $sz) { $sz = 'none' }
                    $castka = [double]::Parse((Text $zp 'homeCurrency/priceSum'), [Globalization.CultureInfo]::InvariantCulture)
                    if (-not $sazby.Contains($sz)) { $sazby[$sz] = 0.0 }
                    $sazby[$sz] += $castka
                }
                $polozky = ($sazby.Keys | ForEach-Object { "<int:intDocItem><int:text>Zdanitelné plnění - přijetí úplaty</int:text><int:quantity>1</int:quantity><int:payVAT>true</int:payVAT><int:rateVAT>$_</int:rateVAT><int:homeCurrency><typ:unitPrice>$(Cislo $sazby[$_])</typ:unitPrice></int:homeCurrency></int:intDocItem>" }) -join "`n"
                $partner = (Uzel $zh 'partnerIdentity').OuterXml -replace '<inv:partnerIdentity', '<int:partnerIdentity' -replace '</inv:partnerIdentity>', '</int:partnerIdentity>'
                $stredisko = Text $zh 'centre/ids'; $cinnost = Text $zh 'activity/ids'
                $ddppXml = @"
<dat:dataPackItem id="ddpp_$zfNo" version="2.0"><int:intDoc version="2.0">
<int:intDocHeader>
<int:number><typ:ids>26IN</typ:ids></int:number>
<int:symPar>$zfNo</int:symPar>
<int:date>$dnes</int:date><int:dateTax>$den</int:dateTax><int:dateAccounting>$den</int:dateAccounting><int:dateKHDPH>$den</int:dateKHDPH>
<int:accounting><typ:ids>Nevím</typ:ids></int:accounting><int:classificationVAT><typ:ids>UD</typ:ids></int:classificationVAT>
<int:text>$(X "Daňový doklad k přijaté platbě (zálohová faktura č. $zfNo)")</int:text>
$partner
$(if ($stredisko) { "<int:centre><typ:ids>$(X $stredisko)</typ:ids></int:centre>" })$(if ($cinnost) { "<int:activity><typ:ids>$(X $cinnost)</typ:ids></int:activity>" })
<int:intNote>Vystavil pomocník po zaplacení zálohy $zfNo</int:intNote>
</int:intDocHeader>
<int:intDocDetail>
$polozky
</int:intDocDetail>
<int:intDocSummary><int:roundingDocument>none</int:roundingDocument><int:roundingVAT>none</int:roundingVAT></int:intDocSummary>
</int:intDoc></dat:dataPackItem>
"@
                if (-not $Ostre) {
                    Zajisti-Slozku $navrhDir
                    [IO.File]::WriteAllText((Join-Path $navrhDir ("DDPP_{0}.xml" -f $zfNo)), $ddppXml, (New-Object Text.UTF8Encoding($true)))
                    $hotove["DDPP-$zfNo"] = 'navrh'
                    Set-Content -LiteralPath $hotoveF -Value ($hotove | ConvertTo-Json) -Encoding UTF8
                    Zapis-Log $uloha ("NÁVRH daňový doklad k záloze {0} (platba {1})" -f $zfNo, $den)
                    continue
                }
                $odp = MServer ("ddpp_" + $zfNo) $ddppXml ("Danovy doklad k zaloze $zfNo")
                $cis = Vysledek $odp 'daňový doklad k přijaté platbě'
                $hotove["DDPP-$zfNo"] = $cis
                Set-Content -LiteralPath $hotoveF -Value ($hotove | ConvertTo-Json) -Encoding UTF8
                Zapis-Log $uloha ("OK    daňový doklad {0} k záloze {1}" -f $cis, $zfNo)
            } catch {
                Zapis-Log $uloha ("CHYBA daňový doklad k záloze {0}: {1}" -f $zfNo, $_.Exception.Message)
            }
        }
    }
} catch {
    Zapis-Log $uloha ('CHYBA daňové doklady k zálohám {0}' -f $_.Exception.Message)
}

# ===== 2. Doklad po kontrole vráceného zboží (ODD / faktura) =====
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

            # --- výměna: položky přijaté objednávky 991… (faktura je vyřídí a uvolní rezervaci) ---
            $objPol = @{}
            $xo = ([string]$r.xorder_no) -replace '[^0-9]', ''
            if ($xo -and $Ostre -and [string]$r.xorder_state -eq 'hotovo') {
                $od = MServer ("vratka_objcteni_" + $no) ("<dat:dataPackItem id=`"o`" version=`"2.0`"><lst:listOrderRequest version=`"2.0`" orderType=`"receivedOrder`" orderVersion=`"2.0`"><lst:requestOrder><ftr:queryFilter><ftr:filter>OBJ.Cislo = '$xo'</ftr:filter></ftr:queryFilter></lst:requestOrder></lst:listOrderRequest></dat:dataPackItem>") 'Vratky - cteni objednavky'
                foreach ($op in $od.SelectNodes("//*[local-name()='orderItem']")) {
                    $k = Text $op 'code'; if (-not $k) { $k = Text $op 'text' }
                    if ($k -and -not $objPol.ContainsKey($k)) { $objPol[$k] = Text $op 'id' }
                }
                if ($objPol.Count -eq 0) { throw "Objednávka $xo v Pohodě nenalezena." }
            }

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
                    $vazba = ''
                    if ($i.kind -eq 'nove' -and $objPol.Count) {
                        $oid = $objPol[$kodZ]; if (-not $oid) { $oid = $objPol[[string]$i.name] }
                        if ($oid) { $vazba = "<inv:link><typ:sourceAgenda>receivedOrder</typ:sourceAgenda><typ:sourceItemId>$oid</typ:sourceItemId></inv:link>" }
                    }
                    [void]$radky.Append(@"
<inv:invoiceItem>$vazba<inv:text>$(X $nazev)</inv:text><inv:quantity>$(Cislo $mn)</inv:quantity><inv:unit>ks</inv:unit><inv:payVAT>$platiDph</inv:payVAT><inv:rateVAT>$sazba</inv:rateVAT><inv:discountPercentage>$sleva</inv:discountPercentage><inv:homeCurrency><typ:unitPrice>$(Cislo $cena)</typ:unitPrice></inv:homeCurrency><inv:code>$(X $kodZ)</inv:code><inv:stockItem><typ:store><typ:ids>SKLAD</typ:ids></typ:store><typ:stockItem><typ:ids>$(X $kodZ)</typ:ids></typ:stockItem></inv:stockItem></inv:invoiceItem>
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
            # --- odpočet zaplacené zálohy (doplatek převodem) ---
            $zfNo = ([string]$r.zf_no) -replace '[^0-9]', ''
            if ($zfNo -and [double]$r.advance -gt 0) {
                $z = [double]$r.advance
                $zakl = [math]::Round($z / 1.21, 2); $dph = [math]::Round($z - $zakl, 2)
                [void]$radky.Append(@"
<inv:invoiceAdvancePaymentItem><inv:sourceDocument><typ:number>$zfNo</typ:number></inv:sourceDocument><inv:quantity>1</inv:quantity><inv:payVAT>true</inv:payVAT><inv:rateVAT>high</inv:rateVAT><inv:homeCurrency><typ:unitPrice>$(Cislo (-$z))</typ:unitPrice><typ:price>$(Cislo (-$zakl))</typ:price><typ:priceVAT>$(Cislo (-$dph))</typ:priceVAT><typ:priceSum>$(Cislo (-$z))</typ:priceSum></inv:homeCurrency></inv:invoiceAdvancePaymentItem>
"@)
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
            $text = if ($odd) { "Opravný daňový doklad k daňovému dokladu č. $fvNo`nDůvod opravy: Vratka eshop" } else { "Výměna zboží k faktuře č. $fvNo" + $(if ($xo) { ", objednávka $xo" } else { '' }) }
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
<inv:paymentType><typ:ids>$(if (-not $odd -and [string]$r.pay_method -eq 'dobirka') { 'Dobírkou' } else { 'Příkazem' })</typ:ids></inv:paymentType><inv:account><typ:ids>OBCZ</typ:ids></inv:account><inv:symConst>0308</inv:symConst>
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
