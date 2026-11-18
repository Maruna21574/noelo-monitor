<?php
/** @var array<string, mixed>|null $latestRun */
/** @var \Noelo\Monitor\Reporting\DailyReport $report */
/** @var array<int, array<string, mixed>> $pendingPayments */
/** @var array<int, array<string, mixed>> $reportHistory */
/** @var array<int, array<string, mixed>> $recentReminders */
/** @var string $reminderMode off|dry-run|live */
/** @var array<int, string> $reminderStatuses order_id => stav z payment_reminders_sent */
/** @var array<int, int> $reviewOrderIds objednavky s platbou na overenie (kluce) */
/** @var bool $reminderConfigured */
/** @var string $csrfToken */
/** @var string|null $username */

use Noelo\Monitor\Support\Html;
use Noelo\Monitor\Support\Money;

$shippableCount = count($report->shippableOrderIds);
$partialCount = count($report->partiallyShippable);
$unavailableCount = count($report->unavailable);
$conflictCount = count($report->reservationConflicts);
$pendingPaymentCount = count($pendingPayments);
$reminderCount = count($report->paymentReminders);
$cancellationCount = count($report->cancellationSuggestions);
$errorCount = count($report->technicalErrors);

$navItems = [
    ['sec-run', 'Stav kontroly', null],
    ['sec-shippable', 'Pripravené', $shippableCount],
    ['sec-partial', 'Čiastočne', $partialCount],
    ['sec-unavailable', 'Nedostupné', $unavailableCount],
    ['sec-conflicts', 'Konflikty', $conflictCount],
    ['sec-payments', 'Platby na overenie', $pendingPaymentCount],
    ['sec-reminders', 'Pripomienky', $reminderCount],
    ['sec-auto-reminders', 'Odoslané pripomienky', count($recentReminders)],
    ['sec-cancel', 'Návrhy na storno', $cancellationCount],
    ['sec-errors', 'Chyby', $errorCount],
    ['sec-history', 'História e-mailov', null],
];
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard – Noelo Monitor</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="topbar">
    <a href="?page=dashboard" class="nav-logo-wrap">
        <img src="https://noelo.sk/img/design/logo-new-v2-sk.png" alt="Noelo.sk" class="nav-logo-img">
        <span class="nav-badge">Denný monitoring</span>
    </a>
    <div class="nav-right">
        <a href="?page=help">Návod</a>
        <a href="?page=order-feed" target="_blank" rel="noopener">Surový feed objednávok ↗</a>
        <?php if ($username !== null): ?>
            <span>Prihlásený: <strong><?= Html::e($username) ?></strong></span>
        <?php endif; ?>
        <a href="?page=logout">Odhlásiť sa</a>
    </div>
</nav>

<div class="subnav">
    <?php foreach ($navItems as [$anchor, $label, $count]): ?>
        <a href="#<?= Html::e($anchor) ?>" class="<?= $count ? 'has-items' : '' ?>">
            <?= Html::e($label) ?><?php if ($count !== null): ?><span class="count"><?= Html::e((string) $count) ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<main class="wrap">

    <?php $reminderFlash = (string) ($_GET['reminder'] ?? ''); ?>
    <?php if ($reminderFlash === 'sent'): ?>
        <p class="flash">Pripomienka bola odoslaná zákazníkovi.</p>
    <?php elseif ($reminderFlash === 'skipped'): ?>
        <p class="flash">Pripomienka sa nebude posielať.</p>
    <?php elseif ($reminderFlash === 'failed'): ?>
        <p class="flash flash-error">Odoslanie pripomienky zlyhalo (shop odpovedal chybou). Môžeš to skúsiť znova.</p>
    <?php elseif ($reminderFlash === 'unknown'): ?>
        <p class="flash flash-error">Nie je jasné, či mail odišiel – skontroluj to ručne a pre istotu ho neposielaj znova.</p>
    <?php elseif ($reminderFlash === 'not-due'): ?>
        <p class="flash flash-error">Túto objednávku už nie je možné poslať (už sa riešila, je zaplatená alebo má platbu na overenie).</p>
    <?php elseif ($reminderFlash === 'not-configured'): ?>
        <p class="flash flash-error">Odosielanie pripomienok nie je nastavené (PAYMENT_REMINDER_URL v .env).</p>
    <?php endif; ?>

    <?php if (isset($_GET['ran'])): ?>
        <p class="flash">Kontrola bola manuálne spustená.</p>
    <?php endif; ?>

    <?php if (($_GET['mail'] ?? null) === 'sent'): ?>
        <p class="flash">Denný report bol odoslaný e-mailom.</p>
    <?php elseif (($_GET['mail'] ?? null) === 'failed'): ?>
        <p class="flash flash-error">Odoslanie e-mailu zlyhalo (skontroluj SMTP nastavenia v .env).</p>
    <?php elseif (($_GET['mail'] ?? null) === 'not-configured'): ?>
        <p class="flash flash-error">SMTP nie je nastavené v .env, e-mail sa nedá poslať.</p>
    <?php elseif (($_GET['mail'] ?? null) === 'no-run-yet'): ?>
        <p class="flash flash-error">Najprv spusti kontrolu (tlačidlo vyššie), potom môžeš poslať e-mail.</p>
    <?php endif; ?>

    <p class="section-desc" style="margin-bottom: 4px;">
        Rýchly prehľad k dnešku. Kliknutím na dlaždicu alebo na menu vyššie preskočíš rovno na danú sekciu nižšie.
    </p>
    <div class="kpi-row">
        <a class="kpi k-ok" href="#sec-shippable">
            <div class="lbl">Pripravené na odoslanie</div>
            <div class="val"><?= Html::e((string) $shippableCount) ?></div>
        </a>
        <a class="kpi k-warn" href="#sec-partial">
            <div class="lbl">Čiastočne pripravené</div>
            <div class="val"><?= Html::e((string) $partialCount) ?></div>
        </a>
        <a class="kpi k-bad" href="#sec-unavailable">
            <div class="lbl">Chýba tovar</div>
            <div class="val"><?= Html::e((string) $unavailableCount) ?></div>
        </a>
        <a class="kpi k-warn" href="#sec-conflicts">
            <div class="lbl">Konflikty rezervácií</div>
            <div class="val"><?= Html::e((string) $conflictCount) ?></div>
        </a>
        <a class="kpi k-info" href="#sec-payments">
            <div class="lbl">Platby na overenie</div>
            <div class="val"><?= Html::e((string) $pendingPaymentCount) ?></div>
        </a>
        <a class="kpi k-warn" href="#sec-reminders">
            <div class="lbl">Nezaplatené 5+ dní</div>
            <div class="val"><?= Html::e((string) $reminderCount) ?></div>
        </a>
        <a class="kpi k-bad" href="#sec-cancel">
            <div class="lbl">Návrh na storno</div>
            <div class="val"><?= Html::e((string) $cancellationCount) ?></div>
        </a>
        <a class="kpi k-bad" href="#sec-errors">
            <div class="lbl">Technické chyby</div>
            <div class="val"><?= Html::e((string) $errorCount) ?></div>
        </a>
    </div>

    <div class="card" id="sec-run">
        <h2>Posledné spustenie kontroly</h2>
        <p class="section-desc">Kedy appka naposledy stiahla objednávky a bankové pohyby, a či sa to podarilo.</p>
        <?php if ($latestRun === null): ?>
            <p class="empty">Kontrola ešte nikdy nebežala.</p>
        <?php else: ?>
            <table>
                <tr><th>Spustené</th><td><?= Html::e((string) $latestRun['started_at']) ?></td></tr>
                <tr><th>Dokončené</th><td><?= Html::e((string) ($latestRun['finished_at'] ?? '-')) ?></td></tr>
                <tr><th>Spôsob</th><td><?= Html::e((string) $latestRun['triggered_by']) ?></td></tr>
                <tr><th>Objednávkový feed</th><td><?= Html::statusBadge($latestRun['order_feed_status'] ?? null) ?></td></tr>
                <tr><th>Stav skladu</th><td><span class="pill pill-muted">z objednávkového feedu (readyItems)</span></td></tr>
                <tr><th>Fio API</th><td><?= Html::statusBadge($latestRun['fio_status'] ?? null) ?></td></tr>
            </table>
        <?php endif; ?>

        <form method="post" action="?page=run-check" style="margin-top: 14px;">
            <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
            <button type="submit" class="btn-primary">Spustiť kontrolu manuálne</button>
        </form>
    </div>

    <div class="card" id="sec-shippable">
        <h2>Pripravené na odoslanie</h2>
        <p class="section-desc">Objednávky, ktoré sú zaplatené (alebo idú na dobierku) a máme pre ne pripravený všetok tovar — môžeš ich rovno baliť a odoslať.</p>
        <?php if ($report->shippableOrderIds === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <p><?= implode(', ', array_map(static fn ($id) => Html::e($report->datePrefix($id)) . '#' . Html::e((string) $id), $report->shippableOrderIds)) ?></p>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-partial">
        <h2>Možno čiastočne odoslať</h2>
        <p class="section-desc">Objednávky, kde je pripravená len časť tovaru. Buď pošli, čo je hotové, alebo počkaj na zvyšok.</p>
        <?php if ($report->partiallyShippable === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <?php foreach ($report->partiallyShippable as $orderId => $result): ?>
                <div class="order-block">
                    <p><strong><?= Html::e($report->datePrefix($orderId)) ?>Objednávka #<?= Html::e((string) $orderId) ?></strong></p>
                    <table>
                        <tr><th>Produkt</th><th>Požadované</th><th>Pripravené</th><th>Dodanie</th><th>Stav</th></tr>
                        <?php foreach (array_merge($result->availableItems, $result->unavailableItems) as $item): ?>
                            <tr>
                                <td><?= Html::e($item->productCode ?? '-') ?> - <?= Html::e($item->title) ?></td>
                                <td><?= Html::e((string) $item->requestedCount) ?></td>
                                <td><?= Html::e($item->readyCount === null ? '?' : (string) $item->readyCount) ?></td>
                                <td><?= Html::e($item->deliveryInfo ?? '-') ?></td>
                                <td><?= Html::e($item->reason ?? 'dostupné') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-unavailable">
        <h2>Tovar nie je dostupný</h2>
        <p class="section-desc">Objednávky, kde ešte nemáme pripravený žiadny tovar — čaká sa na sklad.</p>
        <?php if ($report->unavailable === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <?php foreach ($report->unavailable as $orderId => $result): ?>
                <div class="order-block">
                    <p><strong><?= Html::e($report->datePrefix($orderId)) ?>Objednávka #<?= Html::e((string) $orderId) ?></strong></p>
                    <table>
                        <tr><th>Produkt</th><th>Požadované</th><th>Pripravené</th><th>Dodanie</th><th>Dôvod</th></tr>
                        <?php foreach ($result->unavailableItems as $item): ?>
                            <tr>
                                <td><?= Html::e($item->productCode ?? '-') ?> - <?= Html::e($item->title) ?></td>
                                <td><?= Html::e((string) $item->requestedCount) ?></td>
                                <td><?= Html::e($item->readyCount === null ? '?' : (string) $item->readyCount) ?></td>
                                <td><?= Html::e($item->deliveryInfo ?? '-') ?></td>
                                <td><?= Html::e($item->reason ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-conflicts">
        <h2>Pravdepodobné konflikty sklad/rezervácie</h2>
        <p class="section-desc">Podozrenie, že stará nezaplatená objednávka drží tovar, ktorý potrebuje iná (už zaplatená) objednávka. Appka si tým nie je istá na 100 % — over to ručne. Vyriešený konflikt môžeš odkliknúť, už sa nebude zobrazovať ani v e-maile.</p>
        <?php if ($report->reservationConflicts === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <table>
                <tr><th>Produkt</th><th>Blokovaná objednávka</th><th>Možno blokujú (nezaplatené)</th><th>Akcia</th></tr>
                <?php foreach ($report->reservationConflicts as $conflict): ?>
                    <tr>
                        <td><?= Html::e($conflict->productCode) ?></td>
                        <td><?= Html::e($report->datePrefix($conflict->blockedOrderId)) ?>#<?= Html::e((string) $conflict->blockedOrderId) ?></td>
                        <td><?= implode(', ', array_map(static fn ($id) => '#' . Html::e((string) $id), $conflict->possiblyBlockingOrderIds)) ?></td>
                        <td>
                            <form class="inline" method="post" action="?page=dismiss-conflict">
                                <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
                                <input type="hidden" name="order_id" value="<?= Html::e((string) $conflict->blockedOrderId) ?>">
                                <input type="hidden" name="product_code" value="<?= Html::e($conflict->productCode) ?>">
                                <button type="submit" class="btn-reject">Odkliknúť</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-payments">
        <h2>Možné platby bez správneho VS (ručné overenie)</h2>
        <p class="section-desc">E-shop hovorí, že objednávka nie je zaplatená, ale v banke sa našla platba, ktorá by k nej mohla patriť. Nič sa nedeje automaticky — potvrď alebo zamietni.</p>
        <?php if ($pendingPayments === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <table>
                <tr><th>Dátum objednávky</th><th>Objednávka</th><th>Suma</th><th>Dátum</th><th>Typ zhody</th><th>Poznámka (stav objednávky)</th><th>Akcia</th></tr>
                <?php foreach ($pendingPayments as $payment): ?>
                    <tr>
                        <td><?= Html::e(substr((string) $payment['order_created_at'], 0, 10)) ?></td>
                        <td>#<?= Html::e((string) $payment['order_id']) ?></td>
                        <td><?= Html::e(Money::toDecimalString((int) $payment['amount_cents'])) ?> EUR</td>
                        <td><?= Html::e((string) $payment['transaction_date']) ?></td>
                        <td><?= Html::e(\Noelo\Monitor\Matching\PaymentMatchType::labelFor((string) $payment['match_type'])) ?></td>
                        <td><?= Html::e((string) ($payment['order_status'] ?? '')) ?></td>
                        <td>
                            <form class="inline" method="post" action="?page=payment-review">
                                <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
                                <input type="hidden" name="match_id" value="<?= Html::e((string) $payment['id']) ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn-confirm">Potvrdiť</button>
                            </form>
                            <form class="inline" method="post" action="?page=payment-review">
                                <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
                                <input type="hidden" name="match_id" value="<?= Html::e((string) $payment['id']) ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-reject">Zamietnuť</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-reminders">
        <h2>Pripomenutie úhrady (nezaplatené 5+ dní)</h2>
        <p class="section-desc">Objednávky s platbou vopred, ktoré 5 a viac dní čakajú na zaplatenie. Objednávky čakajúce na pripomienku. Mail zákazníkovi odíde až keď klikneš na „Poslať mail“; „Neposielať“ objednávku zo zoznamu odstráni.</p>
        <?php if ($report->paymentReminders === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <table>
                <tr><th>Dátum objednávky</th><th>Objednávka</th><th>Zákazník</th><th>Suma</th><th>Akcia</th></tr>
                <?php foreach ($report->paymentReminders as $reminderOrder): ?>
                    <?php $reminderStatus = $reminderStatuses[$reminderOrder->id] ?? null; ?>
                    <tr>
                        <td><?= Html::e($reminderOrder->createdAt->format('Y-m-d')) ?></td>
                        <td>#<?= Html::e((string) $reminderOrder->id) ?></td>
                        <td><?= Html::e($reminderOrder->deliveryName) ?></td>
                        <td><?= Html::e(Money::toDecimalString($reminderOrder->totalPriceCents)) ?> EUR</td>
                        <td>
                            <?php if ($reminderStatus === 'sent'): ?>
                                <span class="pill pill-green">mail odoslaný</span>
                            <?php elseif ($reminderStatus === 'skipped'): ?>
                                <span class="pill pill-muted">neposielať (preskočené)</span>
                            <?php elseif ($reminderStatus === 'unknown' || $reminderStatus === 'pending'): ?>
                                <span class="pill pill-muted">neznáme, či mail odišiel – skontroluj ručne</span>
                            <?php elseif (isset($reviewOrderIds[$reminderOrder->id])): ?>
                                <span class="pill pill-muted">platba na overenie – mail sa neposiela</span>
                            <?php elseif (!$reminderConfigured): ?>
                                <span class="pill pill-muted">odosielanie nie je nastavené (.env)</span>
                            <?php else: ?>
                                <form class="inline" method="post" action="?page=reminder-action" onsubmit="return confirm('Naozaj poslať zákazníkovi pripomienku nezaplatenia k objednávke #<?= Html::e((string) $reminderOrder->id) ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
                                    <input type="hidden" name="order_id" value="<?= Html::e((string) $reminderOrder->id) ?>">
                                    <input type="hidden" name="action" value="send">
                                    <button type="submit" class="btn-confirm"><?= $reminderStatus === 'failed' ? 'Skúsiť znova' : 'Poslať mail' ?></button>
                                </form>
                                <form class="inline" method="post" action="?page=reminder-action">
                                    <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
                                    <input type="hidden" name="order_id" value="<?= Html::e((string) $reminderOrder->id) ?>">
                                    <input type="hidden" name="action" value="skip">
                                    <button type="submit" class="btn-reject">Neposielať</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-auto-reminders">
        <h2>Automaticky odoslané pripomienky zákazníkom</h2>
        <p class="section-desc">Pripomienky nezaplatenia, ktoré appka sama odoslala (cez shop) pri dennej kontrole. Každej objednávke najviac jedna.</p>
        <table>
            <tr>
                <th>Automatické odosielanie</th>
                <td>
                    <?= match ($reminderMode) {
                        'live' => '<span class="pill pill-green">ZAPNUTÉ – maily odchádzajú zákazníkom</span>',
                        'dry-run' => '<span class="pill pill-muted">SKÚŠOBNÝ REŽIM – nič sa neposiela automaticky (zoznam vypíše cron do konzoly)</span>',
                        default => '<span class="pill pill-muted">AUTOMATICKY VYPNUTÉ – maily posielaš ručne tlačidlom pri objednávke</span>',
                    } ?>
                </td>
            </tr>
        </table>
        <?php if ($recentReminders === []): ?>
            <p class="empty">Zatiaľ sa neodoslala žiadna automatická pripomienka.</p>
        <?php else: ?>
            <table>
                <tr><th>Objednávka</th><th>Stav</th><th>Kedy</th></tr>
                <?php foreach ($recentReminders as $reminder): ?>
                    <tr>
                        <td>#<?= Html::e((string) $reminder['order_id']) ?></td>
                        <td><?= Html::e(match ($reminder['status']) {
                            'sent' => 'odoslané',
                            'failed' => 'zlyhalo (skúsi sa znova)',
                            'unknown' => 'neznáme – skontroluj ručne',
                            'skipped' => 'preskočené (neposielať)',
                            default => 'prebieha',
                        }) ?></td>
                        <td><?= Html::e((string) $reminder['attempted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-cancel">
        <h2>Návrh na storno (nezaplatené viac než 7 dní)</h2>
        <p class="section-desc">Objednávky nezaplatené viac než 7 dní. Appka nič sama neruší ani nestornuje — je to len návrh, rozhoduje vždy človek.</p>
        <?php if ($report->cancellationSuggestions === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <p><?= implode(', ', array_map(static fn ($o) => Html::e($o->createdAt->format('Y-m-d')) . ' #' . Html::e((string) $o->id), $report->cancellationSuggestions)) ?></p>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-errors">
        <h2>Technické chyby feedov/API</h2>
        <p class="section-desc">Ak sa niečo pri sťahovaní dát pokazilo (výpadok, zlé heslo, nezrovnalosť v dátach), uvidíš to tu.</p>
        <?php if ($report->technicalErrors === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <table>
                <tr><th>Feed</th><th>Kedy</th><th>Správa</th></tr>
                <?php foreach ($report->technicalErrors as $error): ?>
                    <tr>
                        <td><?= Html::e((string) $error['feed_type']) ?></td>
                        <td><?= Html::e((string) $error['occurred_at']) ?></td>
                        <td><?= Html::e((string) $error['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" id="sec-history">
        <h2>História odoslaných reportov</h2>
        <p class="section-desc">Prehľad, kedy sa poslal denný súhrnný e-mail a komu.</p>

        <form method="post" action="?page=send-report" style="margin-bottom: 16px;">
            <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
            <button type="submit" class="btn-primary">Poslať e-mail teraz (aj znova)</button>
        </form>

        <?php if ($reportHistory === []): ?>
            <p class="empty">(žiadne)</p>
        <?php else: ?>
            <table>
                <tr><th>Dátum</th><th>Stav</th><th>Odoslané</th><th>Príjemcovia</th></tr>
                <?php foreach ($reportHistory as $historyRow): ?>
                    <tr>
                        <td><?= Html::e((string) $historyRow['run_date']) ?></td>
                        <td><?= Html::e((string) $historyRow['status']) ?></td>
                        <td><?= Html::e((string) ($historyRow['sent_at'] ?? '-')) ?></td>
                        <td><?= Html::e((string) $historyRow['recipients']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
