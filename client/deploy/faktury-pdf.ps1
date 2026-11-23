# Faktury v PDF pro zákazníky (client.4kluciodkol.cz → účet na e-shopu, tlačítko „Stáhnout fakturu (PDF)“).
#  1. Zeptá se portálu, ke kterým fakturám chybí PDF (GET /api/sync/faktury-pdf).
#  2. Pohoda je přes mServer vytiskne sestavou „Faktura“ (ID 190) do PDF – mServer vrátí soubor rovnou v odpovědi (base64),
#     na serveru iPodniku nic nezůstane (removeFile).
#  3. PDF pošle do portálu (POST /api/sync/faktury-pdf). Do Pohody nic nezapisuje.
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
Zapis-Log $uloha ('Faktury v PDF: vytištěno {0}, uloženo v portálu {1}{2}' -f $pdfs.Count, $ulozeno, $(if ($spatne.Count) { '; NEVYŠLO: ' + ($spatne -join ', ') } else { '' }))
