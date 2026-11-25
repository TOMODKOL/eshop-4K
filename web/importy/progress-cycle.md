# Progress Cycle – feed nových karet (od 25. 11. 2026)

- Feed: https://b2b.progresscycle.cz/customerfeed.txt (Heureka formát, veřejný; dostupnost zvlášť zbozidostupnost.txt – nepoužíváme).
- Definice „Progress Cycle - nové karty“ (XML, Správce importů), párování podle EAN proti všem kartám (Dodavatel = Všichni).
- Výběr dělá XPath (vstupní filtr BSADMIN v tomto případě nefungoval): jen Giro, Vittoria, Crankbrothers, s EAN, bez lyžařského zboží a vzorků, bez seznamu kódů, které u nás už jsou pod jiným EAN.
- Kód = "PS" + kód Progressu. Stávající karty s kódem Progressu přejmenovány na PS… přes mServer (25. 11.). Feedy FB/Google/Heureka/Zboží používají ID karty, ne kód.
- Název: typ – ZNAČKA – model (C# skript), popis z feedu, MOC s DPH, Velikost z konce názvu, fotky do Obrazky\ProgressCycle\<kód>.
- Nové karty: SKLAD / !Importy / Progress Cycle / značka / kategorie.
- Robot „Automatický import - Progress Cycle nové karty“: jen Importovat nové + kategorie, sledování změn e-mailem. Úloha denně 4:45 (fronta 2).
- Fizik bereme od Aspire, ne od Progressu.
