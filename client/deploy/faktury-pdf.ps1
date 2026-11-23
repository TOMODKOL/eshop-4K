# Faktury v PDF pro zákazníky (client.4kluciodkol.cz → účet na e-shopu, tlačítko „Stáhnout fakturu (PDF)“).
#  1. Zeptá se portálu, ke kterým fakturám chybí PDF (GET /api/sync/faktury-pdf).
#  2. Pohoda je přes mServer vytiskne sestavou „Faktura“ (ID 190) do PDF – mServer vrátí soubor rovnou v odpovědi (base64),
#     na serveru iPodniku nic nezůstane (removeFile).
#  3. PDF pošle do portálu (POST /api/sync/faktury-pdf). Do Pohody nic nezapisuje.
#  Změněné faktury: každý běh se zeptá Pohody na vydané faktury změněné od minula (ftr:lastChanges). Když se jejich obsah
#  opravdu liší (otisk hlavičky, položek a součtů ve stav\faktury_pdf_otisky.json), vytiskne je znovu a PDF v portálu přepíše.
# Spouští se z reklamace-sync.ps1 (každých 5 minut, jen v době běhu mServeru). Ručně:
#   cd D:\Pomocnik ; powershell -ExecutionPolicy Bypass -File .\faktury-pdf.ps1 [-Pocet 25]
param([string]$Portal = 'https://client.4kluciodkol.cz', [int]$Pocet = 25, [int]$Sestava = 190)
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

# faktury, které Pohoda opakovaně nevytiskla (např. smazané) – po 3 pokusech se přeskakují
$chybyF = Join-Path $StavDir 'faktury_pdf_chyby.json'
$chyby = @{}
if (Test-Path -LiteralPath $chybyF) {
    (Get-Content -LiteralPath $chybyF -Raw -Encoding UTF8 | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $chyby[$_.Name] = [int]$_.Value }
}

$odp = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/faktury-pdf?limit=' + ($Pocet + 50)) -Headers $apiHlav -TimeoutSec 60
$cisla = @(@($odp.invoices) | Where-Object { $_ -match '^\d{6,12}$' -and ([int]$chyby[[string]$_]) -lt 3 } | Select-Object -First $Pocet)

# --- změněné faktury: znovu vytisknout a v portálu přepsat ---
$otiskyF = Join-Path $StavDir 'faktury_pdf_otisky.json'
$zmenyF  = Join-Path $StavDir 'faktury_pdf_zmeny.txt'
$otisky = @{}
if (Test-Path -LiteralPath $otiskyF) {
    (Get-Content -LiteralPath $otiskyF -Raw -Encoding UTF8 | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $otisky[$_.Name] = [string]$_.Value }
}
$od = if (Test-Path -LiteralPath $zmenyF) { (Get-Content -LiteralPath $zmenyF -Raw).Trim() } else { (Get-Date).AddDays(-1).ToString('yyyy-MM-ddTHH:mm:ss') }
$zacatek = (Get-Date).AddMinutes(-2).ToString('yyyy-MM-ddTHH:mm:ss')
$znovu = @()
try {
    $dotaz = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="faktury-zmeny" ico="08576726" application="Pomocnik 4K" version="2.0" note="Změněné vydané faktury"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd" xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
 <dat:dataPackItem id="zmeny" version="2.0">
  <lst:listInvoiceRequest version="2.0" invoiceType="issuedInvoice" invoiceVersion="2.0">
   <lst:requestInvoice><ftr:filter><ftr:lastChanges>$od</ftr:lastChanges></ftr:filter></lst:requestInvoice>
  </lst:listInvoiceRequest>
 </dat:dataPackItem>
</dat:dataPack>
"@
    $rz = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($dotaz) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 300
    $dz = New-Object Xml.XmlDocument
    $dz.LoadXml($cp1250.GetString($rz.RawContentStream.ToArray()))
    $sha = [Security.Cryptography.SHA256]::Create()
    foreach ($inv in @($dz.SelectNodes("//*[local-name()='invoice']"))) {
        $h = $inv.SelectSingleNode("./*[local-name()='invoiceHeader']")
        if (-not $h) { continue }
        $c = ($h.SelectSingleNode(".//*[local-name()='number']/*[local-name()='numberRequested']")).InnerText.Trim()
        if ($c -notmatch '^\d{6,12}$') { continue }
        $obsah = (@('invoiceHeader', 'invoiceDetail', 'invoiceSummary') | ForEach-Object {
            $u = $inv.SelectSingleNode("./*[local-name()='$_']"); if ($u) { $u.OuterXml } }) -join ''
        $otisk = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($obsah))) -replace '-', ''
        if ($otisky[$c] -and $otisky[$c] -ne $otisk) { $znovu += $c }
        $otisky[$c] = $otisk
    }
    Set-Content -LiteralPath $zmenyF -Value $zacatek -Encoding ASCII
} catch { Zapis-Log $uloha ('CHYBA změněné faktury: ' + $_.Exception.Message) }
if ($znovu.Count) { $cisla = @($znovu + $cisla | Select-Object -Unique) }
$otisky.GetEnumerator() | ForEach-Object -Begin { $o = [ordered]@{} } -Process { $o[$_.Key] = $_.Value } -End { $o } |
    ConvertTo-Json | Set-Content -LiteralPath $otiskyF -Encoding UTF8
if (-not $cisla.Count) { return }

$polozky = foreach ($c in $cisla) {
@"
 <dat:dataPackItem id="pdf-$c" version="2.0">
  <prn:print version="1.0">
   <prn:record agenda="vydane_faktury"><ftr:queryFilter><ftr:filter>FA.Cislo = '$c'</ftr:filter></ftr:queryFilter></prn:record>
   <prn:printerSettings>
    <prn:report><prn:id>$Sestava</prn:id></prn:report>
    <prn:pdf>
     <prn:fileName>C:\Users\Public\pomocnik_fv_$c.pdf</prn:fileName>
     <prn:binaryData><prn:responseXml>true</prn:responseXml><prn:removeFile>true</prn:removeFile></prn:binaryData>
    </prn:pdf>
   </prn:printerSettings>
  </prn:print>
 </dat:dataPackItem>
"@
}
$xml = @"
<?xml version="1.0" encoding="Windows-1250"?>
<dat:dataPack id="faktury-pdf" ico="08576726" application="Pomocnik 4K" version="2.0" note="Faktury v PDF pro zákazníky"
  xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"
  xmlns:prn="http://www.stormware.cz/schema/version_2/print.xsd"
  xmlns:ftr="http://www.stormware.cz/schema/version_2/filter.xsd">
$($polozky -join "`n")
</dat:dataPack>
"@
$r = Invoke-WebRequest -Uri $url -Method Post -Body $cp1250.GetBytes($xml) -ContentType 'text/xml' -Headers $hlav -UseBasicParsing -TimeoutSec 600
$doc = New-Object Xml.XmlDocument
$doc.LoadXml($cp1250.GetString($r.RawContentStream.ToArray()))

$pdfs = @(); $spatne = @()
foreach ($it in @($doc.SelectNodes("//*[local-name()='responsePackItem']"))) {
    $c = ($it.GetAttribute('id') -replace '^pdf-', '')
    $data = $it.SelectSingleNode(".//*[local-name()='data' and @code='base64']")
    if ($data -and $data.InnerText.Trim()) {
        $pdfs += [pscustomobject]@{ invoice_no = $c; pdf = ($data.InnerText -replace '\s', '') }
    } else {
        $zpr = @($it.SelectNodes(".//*[local-name()='note' or local-name()='errno' or local-name()='message']") | ForEach-Object { $_.InnerText.Trim() }) -join ' '
        $spatne += "$c ($zpr)"
        $chyby[$c] = [int]$chyby[$c] + 1
    }
}

# do portálu po 5 kusech (každé PDF ~40 kB)
$ulozeno = 0
for ($i = 0; $i -lt $pdfs.Count; $i += 5) {
    $davka = @($pdfs[$i..([math]::Min($i + 4, $pdfs.Count - 1))])
    $json = @{ pdfs = $davka } | ConvertTo-Json -Depth 4 -Compress
    $res = Invoke-RestMethod -Uri ($Portal.TrimEnd('/') + '/api/sync/faktury-pdf') -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($json)) `
        -ContentType 'application/json; charset=utf-8' -Headers $apiHlav -TimeoutSec 120
    $ulozeno += [int]$res.saved
    foreach ($e in @($res.errors)) { if ($e) { $spatne += "portál: $e" } }
}

$chyby.GetEnumerator() | ForEach-Object -Begin { $o = [ordered]@{} } -Process { $o[$_.Key] = $_.Value } -End { $o } |
    ConvertTo-Json | Set-Content -LiteralPath $chybyF -Encoding UTF8
Zapis-Log $uloha ('Faktury v PDF: vytištěno {0} (z toho změněných {3}), uloženo v portálu {1}{2}' -f $pdfs.Count, $ulozeno, $(if ($spatne.Count) { '; NEVYŠLO: ' + ($spatne -join ', ') } else { '' }), $znovu.Count)
