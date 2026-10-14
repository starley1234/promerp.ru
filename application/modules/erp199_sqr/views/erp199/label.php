<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Этикетки наклеек: страница печати без интерфейса системы.
 *
 * Размер 58×40 мм — самая ходовая термоэтикетка. QR крупный (важно для дешёвых
 * сканеров и грязных рук), под ним код текстом: если камера не читает, код
 * набирают руками. Адрес сканирования печатается по настройке
 * `erp199_sqr.terminal.print_url`.
 *
 * Переменные: tokens, print_url, base_url.
 */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Этикетки QR-маркировки</title>
    <link rel="stylesheet" href="<?php echo URL::site('assets/css/lot-label.css'); ?>">
    <script defer src="<?php echo URL::site('assets/vendor/qrcode-generator/qrcode.js'); ?>"></script>
    <script defer src="<?php echo URL::site('assets/js/lot-label.js'); ?>"></script>
    <style>
        /* Своя страница печати: 58×40 мм под термопринтер этикеток. */
        .label { width: 58mm; height: 40mm; min-height: 40mm; padding: 3mm; grid-template-columns: 24mm 1fr; gap: 3mm; }
        .qr { width: 22mm; height: 22mm; }
        .details h1 { font-size: 14px; margin: 0 0 3px; }
        .caption, .meta, .fallback { font-size: 8px; }
        .fallback { padding-top: 1mm; }
        @page { size: 58mm 40mm; margin: 0; }
        @media print {
            .label { width: 58mm; height: 40mm; min-height: 40mm; page-break-after: always; }
            .label:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" data-print-label>Печать этикеток</button>
    <a href="/qr/tokens">Вернуться к наклейкам</a>
    <span><?php echo count($tokens); ?> шт., формат 58×40 мм</span>
</div>

<?php foreach ($tokens as $token): ?>
    <?php
    // В QR — ссылка на страницу наклейки: обычная камера телефона откроет
    // терминал, а приложение пришлёт эту же строку на сервер (Scan::clean_code
    // сам достанет код из URL). Если печатать ссылку не нужно — только код.
    $qr_value = $print_url ? $base_url.$token['code'] : $token['code'];
    ?>
    <main class="label" aria-label="Этикетка наклейки <?php echo HTML::chars($token['code']); ?>">
        <div class="qr" data-qr-value="<?php echo HTML::chars($qr_value); ?>"
             aria-label="QR-код наклейки <?php echo HTML::chars($token['code']); ?>"></div>
        <div class="details">
            <div class="caption"><?php echo $token['target_kind'] ? HTML::chars($token['target_kind']) : 'наклейка'; ?></div>
            <h1><?php echo HTML::chars($token['code']); ?></h1>
            <?php if ($token['target_label']): ?><div><?php echo HTML::chars($token['target_label']); ?></div><?php endif; ?>
            <?php if ($token['label_note']): ?><div class="meta"><?php echo HTML::chars($token['label_note']); ?></div><?php endif; ?>
            <div class="meta">партия <?php echo HTML::chars((string) $token['batch']); ?></div>
        </div>
        <?php if ($print_url): ?>
            <div class="fallback"><?php echo HTML::chars($base_url.$token['code']); ?></div>
        <?php endif; ?>
    </main>
<?php endforeach; ?>
</body>
</html>
