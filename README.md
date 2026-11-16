# E-shop 4K

Podklady pro práci na e-shopu 4kluciodkol.cz s Claude: instrukce, skill bsadmin s postupy pro BSADMIN a firemní písma.

## Co je kde

| Soubor | K čemu je |
| --- | --- |
| `INSTRUKCE.md` | Pravidla pro Clauda: co číst před prací, zásady, kde co je |
| `skills/bsadmin/SKILL.md` | Skill bsadmin: BSADMIN, importy, názvy, popisy produktů, CSS, SEO, e-maily |
| `bsadmin-skill.zip` | Tentýž skill zabalený k nahrání do Claude |
| `fonty/` | Písma FK Screamer a Good Sans. Zakoupená licence, jen pro práci pro firmu |

## Jak si to nastavit v Claude (jednou)

Funguje to na Macu i ve Windows stejně, v aplikaci Claude nebo na claude.ai.

1. **GitHub:** přijměte pozvánku do tohoto repozitáře. Přijde e-mailem od GitHubu poté, co vás Tomáš pozve.
2. **Projekt:** v Claude založte nový projekt **E-shop 4K**.
3. **Soubory:** v projektu u souborů klikněte na **Add → GitHub**, propojte svůj GitHub účet a vyberte repozitář `eshop-4K` (všechny soubory).
4. **Instrukce:** do **Instructions** projektu vložte:
   > Jsi pomocník e-shopu 4 KLUCI OD KOL. Komunikuj česky, vykej. Postupuj přesně podle souboru INSTRUKCE.md v souborech projektu a před prací na webu vždy přečti dokument „E-shop 4K – stav a rozhodnutí“.
5. **Skill:** stáhněte si odsud `bsadmin-skill.zip` a v Claude ho nahrajte v Nastavení v části **Skills** (nahrát skill). Claude ho pak použije sám, kdykoli jde o web nebo BSADMIN, i mimo projekt.
6. **Dokumenty:** Tomáš vám přes tlačítko **Share** nasdílí dokument „E-shop 4K – stav a rozhodnutí“ a design system „4 KLUCI OD KOL“ (s možností úprav). Otevřete je jednou z odkazu v e-mailu.
7. **Podklady:** firemní OneDrive, složka `4 KLUCI OD KOL → AI → Podklady pro Claude`.

## Když se něco změní

Pravidla mění Tomáš, nebo Claude na jeho pokyn, přímo tady v repozitáři. Po změně:

- v projektu u souborů z GitHubu klikněte na **synchronizovat / obnovit**;
- pokud se změnil skill, nahrajte nový `bsadmin-skill.zip` znovu (nahradí starý).

Nová rozhodnutí o webu se píšou do dokumentu „E-shop 4K – stav a rozhodnutí“. Ten je sdílený, takže ho všichni vidí hned.

## Co se nepřenáší

Každý máte vlastní účet Claude, proto Claude nesdílí paměť mezi vámi. Všechno důležité proto musí být tady, ve skillu nebo ve sdíleném dokumentu, ne jen v jednom chatu.
