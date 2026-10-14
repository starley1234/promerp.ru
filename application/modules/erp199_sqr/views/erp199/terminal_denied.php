<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Отказ в доступе к терминалу: человеку нужна понятная страница, а не JSON.
 *
 * Случается, когда сессия истекла или ключ устройства отозван. Дальше два
 * пути: войти в систему или получить рабочий ключ у администратора.
 */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нужен доступ</title>
    <style>
        body { margin: 0; padding: 24px; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; }
        main { max-width: 32rem; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        a.button { display: inline-block; margin-top: 8px; background: #1d4ed8; color: #fff; text-decoration: none; border-radius: 10px; padding: 10px 16px; }
        .muted { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
<main>
    <h1>Терминал закрыт</h1>
    <p>Чтобы сканировать наклейки, войдите в систему или используйте ключ устройства, который выдал администратор.</p>
    <p class="muted">Если вы уже входили и всё равно видите эту страницу — сессия истекла или у вашей роли нет права «erp199.scan».</p>
    <a class="button" href="/auth/login">Войти</a>
</main>
</body>
</html>
