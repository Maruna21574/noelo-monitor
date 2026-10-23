<?php
/** @var string|null $username */

use Noelo\Monitor\Support\Html;
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Návod – Noelo Monitor</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="topbar">
    <a href="?page=dashboard" class="nav-logo-wrap">
        <img src="https://noelo.sk/img/design/logo-new-v2-sk.png" alt="Noelo.sk" class="nav-logo-img">
        <span class="nav-badge">Denný monitoring</span>
    </a>
    <div class="nav-right">
        <a href="?page=dashboard">← Späť na dashboard</a>
        <?php if ($username !== null): ?>
            <span>Prihlásený: <strong><?= Html::e($username) ?></strong></span>
        <?php endif; ?>
        <a href="?page=logout">Odhlásiť sa</a>
    </div>
</nav>

<div class="subnav">
    <a href="#co-to-je">Čo to je</a>
    <a href="#ako-funguje">Ako to funguje</a>
    <a href="#sekcie">Čo znamenajú sekcie</a>
    <a href="#tlacidla">Tlačidlá</a>
    <a href="#bezpecnost">Čo appka nikdy nerobí</a>
    <a href="#faq">Časté otázky</a>
</div>

<main class="wrap">

    <div class="card" id="co-to-je">
        <h2>Čo táto appka robí</h2>
        <p class="section-desc" style="font-size:13px;">
            Noelo Monitor je interný nástroj, ktorý každý deň sám skontroluje objednávky z e-shopu, sklad
            (podľa toho, čo je reálne pripravené), platby z Fio banky a spáruje ich dokopy. Výsledok ti
            pošle jedným súhrnným e-mailom a zároveň ho vidíš tu na dashboarde. Appka sama nič nemení
            v e-shope ani v banke — len ti hovorí, čo treba skontrolovať a čo môžeš rovno vybaviť.
        </p>
    </div>

    <div class="card" id="ako-funguje">
        <h2>Ako to funguje</h2>
        <p class="section-desc" style="font-size:13px; margin-bottom:10px;">
            Appka funguje v pravidelných "kontrolách". Jedna kontrola = appka si stiahne aktuálny stav
            objednávok z e-shopu a aktuálne bankové pohyby z Fio, porovná to s tým čo má uložené, a
            aktualizuje dashboard aj pošle e-mail (ak ešte dnes neodišiel).
        </p>
        <ul style="padding-left:20px; font-size:13px; line-height:1.8;">
            <li><strong>Automaticky</strong> — raz denne o 03:00 (keď bude nastavený cron na serveri).</li>
            <li><strong>Ručne</strong> — kedykoľvek klikneš na tlačidlo "Spustiť kontrolu manuálne".</li>
            <li><strong>Samotné otvorenie dashboardu nič nesťahuje</strong> — vidíš len dáta z poslednej kontroly.
                Ak chceš čerstvé dáta hneď teraz, musíš kliknúť na "Spustiť kontrolu manuálne".</li>
        </ul>
    </div>

    <div class="card" id="sekcie">
        <h2>Čo znamenajú jednotlivé sekcie</h2>

        <p><strong>Pripravené na odoslanie</strong></p>
        <p class="section-desc">Objednávka je zaplatená (alebo ide na dobierku) a všetok tovar je reálne pripravený skladom. Môžeš rovno baliť a odoslať.</p>

        <p><strong>Možno čiastočne odoslať</strong></p>
        <p class="section-desc">Časť tovaru z objednávky je pripravená, časť ešte nie. Vidíš presne, ktoré položky sú hotové a ktoré chýbajú — buď pošli čo je hotové, alebo počkaj na zvyšok.</p>

        <p><strong>Tovar nie je dostupný</strong></p>
        <p class="section-desc">Žiadna položka z objednávky ešte nie je pripravená. Pri každej vidíš aj "Dodanie" — buď orientačnú lehotu (napr. "do 7 dní"), alebo konkrétny dátum, kedy to príde na sklad (podľa toho, čo posiela e-shop).</p>

        <p><strong>Pravdepodobné konflikty sklad/rezervácie</strong></p>
        <p class="section-desc">Appka si všimla, že stará nezaplatená objednávka (3+ dni) drží rezervovaný tovar, ktorý potrebuje iná, už zaplatená objednávka. Je to len upozornenie na možnú príčinu — appka si tým nie je istá na 100%, treba to overiť ručne (napr. zavolať zákazníkovi, alebo starú objednávku stornovať, ak už naozaj nemá záujem).</p>

        <p><strong>Možné platby bez správneho VS</strong></p>
        <p class="section-desc">E-shop hovorí, že objednávka nie je zaplatená, ale appka našla v bankových pohyboch platbu, ktorá by k nej mohla patriť (podľa sumy, dátumu, alebo mena platiteľa). Nikdy sa to nepotvrdí samo — ty rozhodneš tlačidlom Potvrdiť/Zamietnuť.</p>

        <p><strong>Pripomenutie úhrady (5+ dní)</strong></p>
        <p class="section-desc">Objednávka s platbou vopred, ktorá 5 a viac dní čaká na zaplatenie. Čisto informačné pre teba — zákazníkovi appka automaticky nič neposiela.</p>

        <p><strong>Návrh na storno (7+ dní)</strong></p>
        <p class="section-desc">Objednávka nezaplatená viac než 7 dní. Appka ju nikdy sama nestornuje ani nezruší — je to len návrh, rozhodnutie je vždy na tebe.</p>

        <p><strong>Technické chyby</strong></p>
        <p class="section-desc">Sem padne všetko, čo sa pri sťahovaní dát pokazilo — výpadok feedu/Fio, zlé heslo, alebo aj nezrovnalosti v dátach (napr. keď suma "zaplatené" nesedí so sumou objednávky).</p>

        <p><strong>História odoslaných reportov</strong></p>
        <p class="section-desc">Prehľad, kedy sa denný e-mail naposledy poslal, či sa to podarilo, a komu išiel.</p>
    </div>

    <div class="card" id="tlacidla">
        <h2>Tlačidlá a čo presne robia</h2>

        <p><strong>Spustiť kontrolu manuálne</strong></p>
        <p class="section-desc">Stiahne aktuálny objednávkový feed aj Fio pohyby, spáruje platby, obnoví dashboard a pošle denný e-mail (ak ešte dnes neodišiel). Medzi dvoma kliknutiami počkaj aspoň 30 sekúnd — Fio banka to inak odmietne (appka to bezpečne zaloguje ako chybu, nič sa nepokazí).</p>

        <p><strong>Poslať e-mail teraz (aj znova)</strong></p>
        <p class="section-desc">Pošle dnešný report e-mailom, aj keby už dnes raz odišiel. Užitočné, keď chceš mať istotu, že report naozaj prišiel, alebo keď sa predtým nepodarilo odoslať.</p>

        <p><strong>Potvrdiť / Zamietnuť (pri platbách)</strong></p>
        <p class="section-desc">Ručne rozhodneš, či nájdená platba naozaj patrí k danej objednávke. Appka si to nikdy nedomyslí sama.</p>

        <p><strong>Surový feed objednávok ↗</strong></p>
        <p class="section-desc">Otvorí v novom okne presne to, čo appka dostáva z e-shopu (aj s menami a e-mailmi zákazníkov) — pre prípad, že si chceš niečo overiť priamo v zdrojových dátach.</p>
    </div>

    <div class="card" id="bezpecnost">
        <h2>Čo appka nikdy nerobí sama</h2>
        <ul style="padding-left:20px; font-size:13px; line-height:1.8;">
            <li>Nikdy sama nestornuje ani nezruší objednávku.</li>
            <li>Nikdy sama nič neposiela zákazníkovi (žiadne pripomienky, žiadne e-maily) — komunikuje len s tebou.</li>
            <li>Nikdy sama nepotvrdí párovanie platby — vždy čaká na tvoje kliknutie.</li>
            <li>Nikdy neposiela denný e-mail viac než raz za deň.</li>
            <li>Nikdy nevypisuje celé osobné údaje zákazníka (meno, e-mail) do reportu/e-mailu — len číslo objednávky, produkty a sumy.</li>
        </ul>
    </div>

    <div class="card" id="faq">
        <h2>Časté otázky</h2>

        <p><strong>Prečo vidím tie isté dáta ako včera?</strong></p>
        <p class="section-desc">Lebo od poslednej kontroly nikto nespustil novú (ani ručne, ani cez cron). Klikni na "Spustiť kontrolu manuálne".</p>

        <p><strong>Prečo je Fio API niekedy červené (ERROR)?</strong></p>
        <p class="section-desc">Najčastejšie preto, že si klikol kontrolu dvakrát rýchlo za sebou (do 30 sekúnd) — Fio banka to odmietne. Počkaj chvíľu a skús znova. Objednávkový feed tým nie je ovplyvnený.</p>

        <p><strong>Čo znamená "pripravené X z Y ks"?</strong></p>
        <p class="section-desc">X je počet kusov, ktoré má e-shop pre danú objednávku reálne pripravené/rezervované skladom, Y je počet, čo si zákazník objednal. Toto číslo posiela priamo e-shop (pole <code>readyItems</code>), appka si ho nevymýšľa.</p>

        <p><strong>Prečo appka nepoužíva samostatný "skladový feed"?</strong></p>
        <p class="section-desc">Pôvodne sa s ním počítalo, ale e-shop namiesto toho pridal pole "readyItems" priamo ku každej položke objednávky — to isté a presnejšie, takže samostatný skladový feed už netreba.</p>
    </div>

</main>
</body>
</html>
