<?php /** @var array $rows */
$dnes = date('Y-m-d');
$sent = count(array_filter($rows, fn($r) => $r['state'] === 'odesláno' && str_starts_with($r['at'], $dnes)));
$held = count(array_filter($rows, fn($r) => str_starts_with($r['state'], 'NEODESLÁNO') && str_starts_with($r['at'], $dnes)));
$err  = count(array_filter($rows, fn($r) => $r['state'] === 'CHYBA' && str_starts_with($r['at'], $dnes)));
?>
<div class="col wide">
  <div style="display:flex;flex-direction:column;gap:6px"><span class="kicker">Administrace</span><h1 class="h2">ODESLANÉ E-MAILY</h1></div>
  <span class="hint">Client posílá e-maily přímo přes server, do schránky eshop@ se kopie neukládá. Tady je záznam všech pokusů (posledních 500).</span>
  <div class="stat">
    <div><b><?= $sent ?></b><span>Dnes odesláno</span></div>
    <div><b><?= $held ?></b><span>Dnes zadrženo (staré záznamy)</span></div>
    <div><b><?= $err ?></b><span>Dnes chyba</span></div>
  </div>
  <div class="card" style="overflow-x:auto;padding:0">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="text-align:left;border-bottom:2px solid var(--ink)"><th style="padding:8px 10px">Kdy</th><th style="padding:8px 10px">Stav</th><th style="padding:8px 10px">Komu</th><th style="padding:8px 10px">Předmět</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $c = $r['state'] === 'odesláno' ? '#2e9d4f' : ($r['state'] === 'CHYBA' ? '#c0392b' : '#8a8a86'); ?>
        <tr style="border-bottom:1px solid var(--line)">
          <td style="padding:6px 10px;white-space:nowrap"><?= e(date_cz($r['at'], true)) ?></td>
          <td style="padding:6px 10px;color:<?= $c ?>;font-weight:700"><?= e($r['state'] === 'odesláno' ? 'Odesláno' : ($r['state'] === 'CHYBA' ? 'Chyba' : 'Neodesláno – ' . preg_replace('/^NEODESLÁNO \(([^)]*)\).*/u', '$1', $r['state']))) ?></td>
          <td style="padding:6px 10px"><?= e($r['to']) ?></td>
          <td style="padding:6px 10px"><?= e($r['subject']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" style="padding:12px">Zatím žádné e-maily.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
