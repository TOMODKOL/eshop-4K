<?php
use App\Modules\Vratky\Service;
/** Nápověda pro prodejnu – co dělá kolega a co systém sám. */
$eta = Service::pohodaEta();
?>
<div class="col wide help">
  <a class="link" href="<?= url('/admin/vratky') ?>" style="align-self:flex-start;padding:0">← Přehled</a>
  <div style="display:flex;flex-direction:column;gap:6px"><span class="kicker">Prodejna</span><h1 class="h2">NÁPOVĚDA</h1></div>

  <div class="card">
    <strong style="font-size:17px">Jak s tím pracovat</strong>
    <p>Otevřete <a href="<?= url('/admin/vratky') ?>">Přehled</a>. Nahoře je <strong>Na vás čeká</strong> – jen věci, kde je krok na prodejně. Klikněte na kartu, v detailu je nahoře jedno tlačítko pro další krok a pod ním <strong>Jak na to</strong>. Všechno ostatní běží samo.</p>
    <p class="hint">Když na vás nic nečeká, nic nedělejte. Seznam „Probíhá“ je jen pro přehled.</p>
  </div>

  <details class="card" open><summary class="hs">1 · Přišla vratka – zákazník chce peníze zpět</summary><ol>
    <li>Zákazník pošle zboží Zásilkovnou. Stav <em>Na cestě</em> a <em>Dorazilo</em> se mění sám podle Zásilkovny.</li>
    <li>Po doručení se v Přehledu objeví <strong>Zkontrolovat zboží</strong>. Rozbalte balík a projděte ho podle „Jak zkontrolovat zboží“ v detailu.</li>
    <li>Je v pořádku → <strong>Zboží je v pořádku ✓</strong>. Opravný doklad v Pohodě se vystaví sám (<?= e($eta) ?>), zboží vraťte do regálu.</li>
    <li>Peníze posílá Tomáš. Jakmile platba projde výpisem z banky, vratka se uzavře sama.</li>
  </ol></details>

  <details class="card"><summary class="hs">2 · Výměna za jinou velikost</summary><ol>
    <li>Po doručení: <strong>Zkontrolovat zboží</strong> → <strong>Zboží je v pořádku ✓</strong>. Kód nové velikosti je většinou vyplněný sám.</li>
    <li>Faktura v Pohodě se vystaví sama, nová velikost se tím zarezervuje.</li>
    <li>V Přehledu se objeví <strong>Poslat nové zboží</strong>. Zabalte, odešlete jako běžnou zásilku a v detailu klikněte <strong>Nové zboží odesláno ✓</strong> (číslo zásilky můžete připsat). Zákazník dostane e-mail.</li>
  </ol></details>

  <details class="card"><summary class="hs">3 · Výměna za jiné zboží s doplatkem</summary><ol>
    <li>Jako výměna velikosti. Když je nové zboží dražší, zákazník doplácí – kartou, převodem nebo na dobírku.</li>
    <li>Kartou a převodem: systém hlídá platbu sám. Dokud nezaplatí, je vratka v „Probíhá – čeká na zákazníka“. Zboží neposílejte.</li>
    <li>Na dobírku: v detailu u kroku Poslat nové zboží je napsaná částka dobírky.</li>
  </ol></details>

  <details class="card"><summary class="hs">4 · Reklamace z webu</summary><ol>
    <li>V Přehledu se objeví <strong>Založit reklamaci v Pohodě</strong>. V detailu jsou všechny údaje k zkopírování a postup.</li>
    <li>Po založení se Client s Pohodou propojí sám a zákazník dostane potvrzení s číslem reklamace.</li>
    <li>Dál vyřizujete reklamaci <strong>jen v Pohodě</strong> (záložka Stav reklamace). Zákazník dostává e-maily o změnách sám.</li>
  </ol></details>

  <details class="card"><summary class="hs">5 · Zákazník přinese zboží na prodejnu</summary><ol>
    <li>Najděte vratku hledáním nahoře (číslo V-…, jméno nebo e-mail).</li>
    <li>Zkontrolujte zboží a klikněte <strong>Zboží převzato a je v pořádku ✓</strong> (u vratky, která měla jít Zásilkovnou, je to schované pod „Zákazník zboží přinesl na prodejnu?“). Dál to běží stejně jako při zaslání.</li>
    <li>Reklamaci přinesenou na prodejnu zakládejte v Pohodě jako dosud – Client si ji načte sám a zákazník dostane potvrzení.</li>
  </ol></details>

  <details class="card"><summary class="hs">Když zboží není v pořádku</summary><ul>
    <li>V detailu otevřete <strong>Zboží není v pořádku</strong> a napište, co přesně je špatně.</li>
    <li>Většinou zvolte <strong>Jen poznamenat</strong> a domluvte se s Tomášem. Vratka zůstane otevřená a zákazník nic nedostane.</li>
    <li><strong>Zamítnout</strong> jen po domluvě s Tomášem – zákazník dostane e-mail s vaším textem.</li>
  </ul></details>

  <div class="card">
    <strong style="font-size:17px">Co dělá systém sám – neřešte</strong>
    <ul>
      <li>Podací kód pro Zásilkovnu a e-mail zákazníkovi po odeslání vratky.</li>
      <li>Stavy <em>Na cestě</em> a <em>Dorazilo</em> podle Zásilkovny.</li>
      <li>Opravné doklady a faktury v Pohodě po kontrole zboží (<?= e($eta) ?>).</li>
      <li>Hlídání doplatků a připomínky zákazníkům.</li>
      <li>Poštovné: odečítá se jen u vrácení přes Zásilkovnu. Neodečítá se, když si zákazník z objednávky nechá zboží aspoň za hranici dopravy zdarma.</li>
      <li>Uzavření vratky, když peníze odejdou z banky.</li>
      <li>E-maily zákazníkovi o důležitých změnách (přijetí, doručení k nám, vyřízení).</li>
    </ul>
  </div>

  <div class="card">
    <strong style="font-size:17px">Kdo je na tahu</strong>
    <ul>
      <li><strong>Na vás</strong> – krok na prodejně (kontrola, odeslání, založení v Pohodě).</li>
      <li><strong>Na Tomášovi</strong> – poslat peníze, schválit doklad, vyřešit chybu.</li>
      <li><strong>Čeká na zákazníka / Na cestě / U dodavatele / Řeší systém</strong> – nic nedělejte.</li>
    </ul>
    <p class="hint">Něco nefunguje nebo nevíte? Napište Tomášovi, technická zpráva je v detailu v Podrobnostech.</p>
  </div>
</div>
