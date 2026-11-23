# Faktury a potvrzení objednávek v PDF pro zákazníky (client.4kluciodkol.cz → účet na e-shopu, tlačítka „Stáhnout PDF“).
#  1. Zeptá se portálu, ke kterým dokladům chybí PDF (GET /api/sync/faktury-pdf a /api/sync/objednavky-pdf).
#  2. Pohoda je přes mServer vytiskne do PDF – faktury sestavou „Faktura“ (ID 190), objednávky sestavou
#     „Potvrzení přijetí objednávky“ (ID 383). mServer vrátí PDF rovnou v odpovědi (base64), na serveru iPodniku nic nezůstane.
#  3. PDF pošle do portálu (POST …-pdf). Do Pohody nic nezapisuje.
#  Prodejky (sestava „Prodejka (daňový doklad)“, ID 170) – e-shopové objednávky vyzvednuté a zaplacené na prodejně.
#  Jen doklady se střediskem e-shopu 4kluciodko (ostatní – prodejna, servis – portál označí „nenabízet“ a netisknou se).
#  Zálohové faktury (sestava „Zálohová faktura“, ID 240) portál nezná – bere je pomocník z Pohody sám (všechny s e-mailem
#  odběratele, poprvé od 1. 1. 2025) a posílá i e-mail a číslo objednávky. Hotové: stav\zalohy_pdf_hotove.json (číslo → otisk).
#  Změněné doklady: každý běh se zeptá Pohody na doklady změněné od minula (ftr:lastChanges). Když se jejich obsah opravdu
#  liší (otisk hlavičky, položek a součtů ve stav\doklady_pdf_otisky.json), vytiskne je znovu a PDF v portálu přepíše.
# Spouští se z reklamace-sync.ps1 (každých 5 minut, jen v době běhu mServeru). Ručně:
#   cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\faktury-pdf.ps1 [-Pocet 25]
param([string]$Portal = 'https://client.4kluciodkol.cz', [int]$Pocet = 25)
. (Join-Path $PSScriptRoot 'spolecne.ps1')
$uloha = 'faktury_pdf'

$hodina = (Get-Date).Hour
if ($hodina -lt $MServerOd -or $hodina -ge $MServerDo) { return }

$token = (Nacti-Udaj 'client_klice').GetNetworkCredential().Password
$apiHlav = @{ Authorization = "Bearer $token" }
$udaj  = Nacti-Udaj 'mserver'
$kod   = [Text.Encoding]::GetEncoding($MServerKodovaniPrihlaseni)
$auth  = [Convert]::ToBase64String($kod.GetBytes(('{0}:{1}' -f $udaj.UserName, $udaj.GetNetworkCredential().Password)))
$hlav  = @{ 'STW-Authorization' = "Basic $auth"; 'STW-Application' = 'Pomocnik 4K' }
$url   = $MServerAdresa.TrimEnd('/') + '/xml'
$cp1250 = [Text.Encoding]::GetEncoding('windows-1250')
$StrediskoEshop = '4kluciodko'   # jen doklady s tímto střediskem jsou e-shopové a nabízejí se v PDF

# druhy dokladů: API v portálu, agenda a sestava pro tisk, SQL sloupec čísla, seznam pro změny
$Druhy = @(
    @{ kind = 'faktury';    agenda = 'vydane_faktury';     sestava = 190; sloupec = 'FA.Cislo';
       seznam = '<lst:listInvoiceRequest version="2.0" invoiceType="issuedInvoice" invoiceVersion="2.0"><lst:requestInvoice><ftr:filter><ftr:lastChanges>{0}</ftr:lastChanges></ftr:filter></lst:requestInvoice></lst:listInvoiceRequest>';
       zaznam = 'invoice'; casti = @('invoiceHeader', 'invoiceDetail', 'invoiceSummary') },
    @{ kind = 'objednavky'; agenda = 'prijate_objednavky'; sestava = 383; sloupec = 'OBJ.Cislo';
       seznam = '<lst:listOrderRequest version="2.0" orderType="receivedOrder" orderVersion="2.0"><lst:requestOrder><ftr:filter><ftr:lastChanges>{0}</ftr:lastChanges></ftr:filter></lst:requestOrder></lst:listOrderRequest>';
       zaznam = 'order'; casti = @('orderHeader', 'orderDetail', 'orderSummary') },
    @{ kind = 'prodejky';   agenda = 'prodejky';           sestava = 170; sloupec = 'PH.Cislo';
       seznam = '<lst:listProdejkaRequest version="2.0" prodejkaVersion="2.0"><lst:requestProdejka><ftr:filter><ftr:lastChanges>{0}</ftr:lastChanges></ftr:filter></lst:requestProdejka></lst:listProdejkaRequest>';
       zaznam = 'prodejka'; casti = @('prodejkaHeader', 'prodejkaDetail', 'prodejkaSummary') }
)

function MServer([string]$id, [string]$obsah) {
    $xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="$id" ico="08576726" application="Pomocnik 4K" version="2.0" note="Doklady v PDF pro zákazníky"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd" xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:prn="http://www.stormware.cz/schema/version_2/print.xsd" xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
$obsah
</dat:dataPack>
"@
    $r = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 600
    $doc = New-Object Xml.XmlDocument
    $doc.LoadXml($cp1250.GetString($r.RawContentStream.ToArray()))
    return $doc
}
function NactiJson([string]$f) {
    $h = @{}
    if (Test-Path -LiteralPath $f) { (Get-Content -LiteralPath $f -Raw -Encoding UTF8 | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $h[$_.Name] = $_.Value } }
    return $h
}
function UlozJson([hashtable]$h, [string]$f) {
    $o = [ordered]@{}; foreach ($k in ($h.Keys | Sort-Object)) { $o[$k] = $h[$k] }
    $o | ConvertTo-Json | Set-Content -LiteralPath $f -Encoding UTF8
}

$chybyF  = Join-Path $StavDir 'doklady_pdf_chyby.json'    # klíč druh:číslo → počet nepovedených tisků (po 3 se přeskakuje)
$otiskyF = Join-Path $StavDir 'doklady_pdf_otisky.json'   # klíč druh:číslo → otisk obsahu dokladu
$zmenyF  = Join-Path $StavDir 'doklady_pdf_zmeny.txt'     # čas posledního dotazu na změny
$chyby = NactiJson $chybyF
$otisky = NactiJson $otiskyF
$od = if (Test-Path -LiteralPath $zmenyF) { (Get-Content -LiteralPath $zmenyF -Raw).Trim() } else { (Get-Date).AddDays(-1).ToString('yyyy-MM-ddTHH:mm:ss') }
$zacatek = (Get-Date).AddMinutes(-2).ToString('yyyy-MM-ddTHH:mm:ss')
$sha = [Security.Cryptography.SHA256]::Create()
$zmenyOk = $true
$souhrn = @()

foreach ($D in $Druhy) {
    $k = $D.kind
    try {
        # 1) chybějící v portálu
        $odp = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + "/api/sync/$k-pdf?limit=" + ($Pocet + 50)) -Headers $apiHlav -TimeoutSec 60
        $cisla = @(@($odp.invoices) | Where-Object { $_ -match '^[0-9A-Z]{6,14}$' -and ([int]$chyby["${k}:$_"]) -lt 3 } | Select-Object -First $Pocet)

        # 2) změněné v Pohodě
        $znovu = @()
        try {
            $dz = MServer "zmeny-$k" ("<dat:dataPackItem id=""zmeny-$k"" version=""2.0"">" + ($D.seznam -f $od) + '</dat:dataPackItem>')
            foreach ($z in @($dz.SelectNodes("//*[local-name()='$($D.zaznam)']"))) {
                $h = $z.SelectSingleNode("./*[local-name()='$($D.casti[0])']")
                $n = if ($h) { $h.SelectSingleNode(".//*[local-name()='number']/*[local-name()='numberRequested']") } else { $null }
                if (-not $n -or $n.InnerText.Trim() -notmatch '^[0-9A-Z]{6,14}$') { continue }
                $c = $n.InnerText.Trim()
                $st = $h.SelectSingleNode("./*[local-name()='centre']/*[local-name()='ids']")
                if (-not $st -or $st.InnerText.Trim() -ne $StrediskoEshop) { continue }
                $obsah = (@($D.casti) | ForEach-Object { $u = $z.SelectSingleNode("./*[local-name()='$_']"); if ($u) { $u.OuterXml } }) -join ''
                $otisk = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($obsah))) -replace '-', ''
                if ($otisky["${k}:$c"] -and $otisky["${k}:$c"] -ne $otisk) { $znovu += $c }
                $otisky["${k}:$c"] = $otisk
            }
        } catch { $zmenyOk = $false; Zapis-Log $uloha ("CHYBA změny ($k): " + $_.Exception.Message) }
        # 2b) chybějící z portálu: jen doklady se střediskem e-shopu, ostatní portál označí jako „nenabízet“
        $nove = @($cisla | Where-Object { $znovu -notcontains $_ })
        if ($nove.Count) {
            $seznamC = ($nove | ForEach-Object { "'$_'" }) -join ','
            $dotaz = if ($k -eq 'prodejky') {
                "<lst:listProdejkaRequest version=""2.0"" prodejkaVersion=""2.0""><lst:requestProdejka><ftr:queryFilter><ftr:filter>PH.Cislo IN ($seznamC)</ftr:filter></ftr:queryFilter></lst:requestProdejka></lst:listProdejkaRequest>"
            } elseif ($k -eq 'objednavky') {
                "<lst:listOrderRequest version=""2.0"" orderType=""receivedOrder"" orderVersion=""2.0""><lst:requestOrder><ftr:queryFilter><ftr:filter>OBJ.Cislo IN ($seznamC)</ftr:filter></ftr:queryFilter></lst:requestOrder></lst:listOrderRequest>"
            } else {
                "<lst:listInvoiceRequest version=""2.0"" invoiceType=""issuedInvoice"" invoiceVersion=""2.0""><lst:requestInvoice><ftr:queryFilter><ftr:filter>FA.Cislo IN ($seznamC)</ftr:filter></ftr:queryFilter></lst:requestInvoice></lst:listInvoiceRequest>"
            }
            $ds = MServer "stred-$k" ("<dat:dataPackItem id=""stred-$k"" version=""2.0"">$dotaz</dat:dataPackItem>")
            $eshop = @{}
            foreach ($z in @($ds.SelectNodes("//*[local-name()='$($D.casti[0])']"))) {
                $n = $z.SelectSingleNode(".//*[local-name()='number']/*[local-name()='numberRequested']")
                $st = $z.SelectSingleNode("./*[local-name()='centre']/*[local-name()='ids']")
                if ($n) { $eshop[$n.InnerText.Trim()] = ($st -and $st.InnerText.Trim() -eq $StrediskoEshop) }
            }
            # jiné středisko nebo doklad v Pohodě už není (smazaný) → nenabízet, ať se nevrací v seznamu
            $jine = @($nove | Where-Object { -not $eshop[$_] })
            if ($jine.Count) {
                $json = @{ pdfs = @($jine | ForEach-Object { @{ no = $_; skip = $true } }) } | ConvertTo-Json -Depth 4 -Compress
                [void](Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + "/api/sync/$k-pdf") -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
                    -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 120)
            }
            $cisla = @($cisla | Where-Object { $znovu -contains $_ -or $eshop[$_] })
            $souhrn += "$k`: jiné středisko (nenabízí se) $($jine.Count)"
        }
        $cisla = @(@($znovu) + @($cisla) | Select-Object -Unique)
        if (-not $cisla.Count) { continue }

        # 3) tisk do PDF
        $polozky = foreach ($c in $cisla) {
            "<dat:dataPackItem id=""pdf-$c"" version=""2.0""><prn:print version=""1.0"">" +
            "<prn:record agenda=""$($D.agenda)""><ftr:queryFilter><ftr:filter>$($D.sloupec) = '$c'</ftr:filter></ftr:queryFilter></prn:record>" +
            "<prn:printerSettings><prn:report><prn:id>$($D.sestava)</prn:id></prn:report><prn:pdf>" +
            "<prn:fileName>C:\Users\Public\pomocnik_$($k)_$c.pdf</prn:fileName>" +
            '<prn:binaryData><prn:responseXml>true</prn:responseXml><prn:removeFile>true</prn:removeFile></prn:binaryData>' +
            '</prn:pdf></prn:printerSettings></prn:print></dat:dataPackItem>'
        }
        $doc = MServer "pdf-$k" ($polozky -join "`n")
        $pdfs = @(); $spatne = @()
        foreach ($it in @($doc.SelectNodes("//*[local-name()='responsePackItem']"))) {
            $c = ($it.GetAttribute('id') -replace '^pdf-', '')
            $data = $it.SelectSingleNode(".//*[local-name()='data' and @code='base64']")
            if ($data -and $data.InnerText.Trim()) { $pdfs += [pscustomobject]@{ no = $c; pdf = ($data.InnerText -replace '\s', '') } }
            else {
                $zpr = @($it.SelectNodes(".//*[local-name()='note']") | ForEach-Object { $_.InnerText.Trim() }) -join ' '
                $spatne += "$c ($zpr)"; $chyby["${k}:$c"] = [int]$chyby["${k}:$c"] + 1
            }
        }

        # 4) do portálu po 5 kusech
        $ulozeno = 0
        for ($i = 0; $i -lt $pdfs.Count; $i += 5) {
            $davka = @($pdfs[$i..([math]::Min($i + 4, $pdfs.Count - 1))])
            $json = @{ pdfs = $davka } | ConvertTo-Json -Depth 4 -Compress
            $res = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + "/api/sync/$k-pdf") -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
                -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 120
            $ulozeno += [int]$res.saved
            foreach ($e in @($res.errors)) { if ($e) { $spatne += "portál: $e" } }
        }
        $souhrn += ('{0}: vytištěno {1} (změněných {2}), v portálu {3}{4}' -f $k, $pdfs.Count, $znovu.Count, $ulozeno,
            $(if ($spatne.Count) { '; NEVYŠLO: ' + ($spatne -join ', ') } else { '' }))
    } catch { $souhrn += "$k`: CHYBA " + $_.Exception.Message }
}

# --- zálohové faktury ---
$zalOdF = Join-Path $StavDir 'zalohy_pdf_od.txt'
$zalHotF = Join-Path $StavDir 'zalohy_pdf_hotove.json'
$zalHot = NactiJson $zalHotF
$zalOd = if (Test-Path -LiteralPath $zalOdF) { (Get-Content -LiteralPath $zalOdF -Raw).Trim() } else { '2025-01-01T00:00:00' }
try {
    $dz = MServer 'zalohy' ('<dat:dataPackItem id="zalohy" version="2.0"><lst:listInvoiceRequest version="2.0" invoiceType="issuedAdvanceInvoice" invoiceVersion="2.0">' +
        "<lst:requestInvoice><ftr:filter><ftr:lastChanges>$zalOd</ftr:lastChanges></ftr:filter></lst:requestInvoice></lst:listInvoiceRequest></dat:dataPackItem>")
    $cekaji = @()
    foreach ($z in @($dz.SelectNodes("//*[local-name()='invoice']"))) {
        $h = $z.SelectSingleNode("./*[local-name()='invoiceHeader']")
        if (-not $h) { continue }
        $n = $h.SelectSingleNode(".//*[local-name()='number']/*[local-name()='numberRequested']")
        $c = if ($n) { $n.InnerText.Trim() } else { '' }
        $em = $h.SelectSingleNode("./*[local-name()='partnerIdentity']//*[local-name()='email']")
        $email = if ($em) { $em.InnerText.Trim() } else { '' }
        $st = $h.SelectSingleNode("./*[local-name()='centre']/*[local-name()='ids']")
        if (-not $st -or $st.InnerText.Trim() -ne $StrediskoEshop) { continue }
        if ($c -notmatch '^\d{6,12}$' -or $email -notmatch '@' -or ([int]$chyby["zalohy:$c"]) -ge 3) { continue }
        $obj = $h.SelectSingleNode("./*[local-name()='numberOrder']"); $vs = $h.SelectSingleNode("./*[local-name()='symVar']")
        $objNo = if ($obj -and $obj.InnerText.Trim()) { $obj.InnerText.Trim() } elseif ($vs) { $vs.InnerText.Trim() } else { '' }
        $obsah = (@('invoiceHeader', 'invoiceDetail', 'invoiceSummary') | ForEach-Object { $u = $z.SelectSingleNode("./*[local-name()='$_']"); if ($u) { $u.OuterXml } }) -join ''
        $otisk = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($obsah))) -replace '-', ''
        if ($zalHot[$c] -ne $otisk) { $cekaji += [pscustomobject]@{ no = $c; email = $email; order_no = $objNo; otisk = $otisk } }
    }
    $davkaZ = @($cekaji | Select-Object -First $Pocet)
    $vytisk = 0; $ulozZ = 0; $spatneZ = @()
    if ($davkaZ.Count) {
        $polozky = foreach ($x in $davkaZ) {
            "<dat:dataPackItem id=""pdf-$($x.no)"" version=""2.0""><prn:print version=""1.0"">" +
            "<prn:record agenda=""vydane_zalohove_faktury""><ftr:queryFilter><ftr:filter>FA.Cislo = '$($x.no)'</ftr:filter></ftr:queryFilter></prn:record>" +
            "<prn:printerSettings><prn:report><prn:id>240</prn:id></prn:report><prn:pdf><prn:fileName>C:\Users\Public\pomocnik_zalohy_$($x.no).pdf</prn:fileName>" +
            '<prn:binaryData><prn:responseXml>true</prn:responseXml><prn:removeFile>true</prn:removeFile></prn:binaryData></prn:pdf></prn:printerSettings></prn:print></dat:dataPackItem>'
        }
        $doc = MServer 'pdf-zalohy' ($polozky -join "`n")
        $pdfs = @()
        foreach ($it in @($doc.SelectNodes("//*[local-name()='responsePackItem']"))) {
            $c = ($it.GetAttribute('id') -replace '^pdf-', '')
            $x = $davkaZ | Where-Object { $_.no -eq $c } | Select-Object -First 1
            $data = $it.SelectSingleNode(".//*[local-name()='data' and @code='base64']")
            if ($x -and $data -and $data.InnerText.Trim()) { $pdfs += [pscustomobject]@{ no = $c; pdf = ($data.InnerText -replace '\s', ''); email = $x.email; order_no = $x.order_no; otisk = $x.otisk } }
            else { $spatneZ += $c; $chyby["zalohy:$c"] = [int]$chyby["zalohy:$c"] + 1 }
        }
        $vytisk = $pdfs.Count
        for ($i = 0; $i -lt $pdfs.Count; $i += 5) {
            $kus = @($pdfs[$i..([math]::Min($i + 4, $pdfs.Count - 1))])
            $json = @{ pdfs = @($kus | Select-Object no, pdf, email, order_no) } | ConvertTo-Json -Depth 4 -Compress
            $res = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/zalohy-pdf') -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
                -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 120
            $ulozZ += [int]$res.saved
            $chybne = @($res.errors | ForEach-Object { ($_ -split ':')[0] })
            foreach ($p in $kus) { if ($chybne -notcontains $p.no) { $zalHot[$p.no] = $p.otisk } }
        }
    }
    # „od“ posunout, až když nic nečeká (jinak by se starší zálohy z dávky ztratily)
    if ($cekaji.Count -le $davkaZ.Count -and -not $spatneZ.Count) { Set-Content -LiteralPath $zalOdF -Value $zacatek -Encoding ASCII }
    UlozJson $zalHot $zalHotF
    if ($davkaZ.Count) { $souhrn += ('zalohy: vytištěno {0}, v portálu {1}, čeká {2}{3}' -f $vytisk, $ulozZ, ($cekaji.Count - $davkaZ.Count),
        $(if ($spatneZ.Count) { '; NEVYŠLO: ' + ($spatneZ -join ', ') } else { '' })) }
} catch { $souhrn += 'zalohy: CHYBA ' + $_.Exception.Message }

if ($zmenyOk) { Set-Content -LiteralPath $zmenyF -Value $zacatek -Encoding ASCII }
UlozJson $otisky $otiskyF
UlozJson $chyby $chybyF
if ($souhrn.Count) { Zapis-Log $uloha ('Doklady v PDF – ' + ($souhrn -join ' | ')) }
