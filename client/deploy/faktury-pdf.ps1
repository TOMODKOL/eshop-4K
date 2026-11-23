# Faktury a potvrzení objednávek v PDF pro zákazníky (client.4kluciodkol.cz → účet na e-shopu, tlačítka „Stáhnout PDF“).
#  1. Zeptá se portálu, ke kterým dokladům chybí PDF (GET /api/sync/faktury-pdf a /api/sync/objednavky-pdf).
#  2. Pohoda je přes mServer vytiskne do PDF – faktury sestavou „Faktura“ (ID 190), objednávky sestavou
#     „Potvrzení přijetí objednávky“ (ID 383). mServer vrátí PDF rovnou v odpovědi (base64), na serveru iPodniku nic nezůstane.
#  3. PDF pošle do portálu (POST …-pdf). Do Pohody nic nezapisuje.
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

# druhy dokladů: API v portálu, agenda a sestava pro tisk, SQL sloupec čísla, seznam pro změny
$Druhy = @(
    @{ kind = 'faktury';    agenda = 'vydane_faktury';     sestava = 190; sloupec = 'FA.Cislo';
       seznam = '<lst:listInvoiceRequest version="2.0" invoiceType="issuedInvoice" invoiceVersion="2.0"><lst:requestInvoice><ftr:filter><ftr:lastChanges>{0}</ftr:lastChanges></ftr:filter></lst:requestInvoice></lst:listInvoiceRequest>';
       zaznam = 'invoice'; casti = @('invoiceHeader', 'invoiceDetail', 'invoiceSummary') },
    @{ kind = 'objednavky'; agenda = 'prijate_objednavky'; sestava = 383; sloupec = 'OBJ.Cislo';
       seznam = '<lst:listOrderRequest version="2.0" orderType="receivedOrder" orderVersion="2.0"><lst:requestOrder><ftr:filter><ftr:lastChanges>{0}</ftr:lastChanges></ftr:filter></lst:requestOrder></lst:listOrderRequest>';
       zaznam = 'order'; casti = @('orderHeader', 'orderDetail', 'orderSummary') }
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
        $cisla = @(@($odp.invoices) | Where-Object { $_ -match '^\d{6,12}$' -and ([int]$chyby["${k}:$_"]) -lt 3 } | Select-Object -First $Pocet)

        # 2) změněné v Pohodě
        $znovu = @()
        try {
            $dz = MServer "zmeny-$k" ("<dat:dataPackItem id=""zmeny-$k"" version=""2.0"">" + ($D.seznam -f $od) + '</dat:dataPackItem>')
            foreach ($z in @($dz.SelectNodes("//*[local-name()='$($D.zaznam)']"))) {
                $h = $z.SelectSingleNode("./*[local-name()='$($D.casti[0])']")
                $n = if ($h) { $h.SelectSingleNode(".//*[local-name()='number']/*[local-name()='numberRequested']") } else { $null }
                if (-not $n -or $n.InnerText.Trim() -notmatch '^\d{6,12}$') { continue }
                $c = $n.InnerText.Trim()
                $obsah = (@($D.casti) | ForEach-Object { $u = $z.SelectSingleNode("./*[local-name()='$_']"); if ($u) { $u.OuterXml } }) -join ''
                $otisk = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($obsah))) -replace '-', ''
                if ($otisky["${k}:$c"] -and $otisky["${k}:$c"] -ne $otisk) { $znovu += $c }
                $otisky["${k}:$c"] = $otisk
            }
        } catch { $zmenyOk = $false; Zapis-Log $uloha ("CHYBA změny ($k): " + $_.Exception.Message) }
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

if ($zmenyOk) { Set-Content -LiteralPath $zmenyF -Value $zacatek -Encoding ASCII }
UlozJson $otisky $otiskyF
UlozJson $chyby $chybyF
if ($souhrn.Count) { Zapis-Log $uloha ('Doklady v PDF – ' + ($souhrn -join ' | ')) }
