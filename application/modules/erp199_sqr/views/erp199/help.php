<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Короткая справка для того, кто навёл камеру на наклейку без приложения.
 *
 * Задача страницы одна: объяснить, что это за предмет и что делать дальше.
 * Никаких данных она не показывает — наклейка может попасть в чужие руки,
 * а состав сведений решает сервер после входа.
 */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Что такое QR-наклейка</title>
    <style>
        body { margin: 0; padding: 24px; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; }
        main { max-width: 36rem; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { margin: 0 0 12px; }
        ol { margin: 0 0 12px; padding-left: 1.25rem; }
        .muted { color: #6b7280; font-size: 14px; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
<main>
    <h1>Это наклейка учёта</h1>
    <p>Код на ней связывает предмет с записью в производственной системе: изделие, партию, заказ или оборудование.</p>

    <h2>Что делать дальше</h2>
    <ol>
        <li>Если у вас есть приложение — откройте его и отсканируйте наклейку: оно покажет карточку предмета и доступные действия.</li>
        <li>Если приложения нет — <a href="/qr/scan">откройте терминал в браузере</a> и введите код с наклейки руками.</li>
        <li>Если предмет найден, а наклейки на нём нет — сообщите ответственному: наклейку выпускают в системе и клеят заново.</li>
    </ol>

    <p class="muted">
        Что именно можно делать с этим предметом, решает сервер: он проверяет права и текущее состояние.
        Сканирование записывается в журнал — кто, когда, где и с каким результатом.
    </p>
</main>
</body>
</html>
