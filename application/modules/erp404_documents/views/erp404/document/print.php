<?php defined('SYSPATH') or die('No direct script access.'); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title><?php echo HTML::chars($document->number.' — '.$document->title); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            font: 14px/1.5 -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111827;
        }
        .sheet {
            background: #fff;
            max-width: 820px;
            margin: 0 auto 24px;
            padding: 32px 36px;
            box-shadow: 0 1px 3px rgba(0,0,0,.15);
        }
        .sheet table { border-collapse: collapse; width: 100%; }
        .sheet td, .sheet th { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        .sheet h1 { font-size: 20px; margin: 0 0 12px; }
        .sheet h2 { font-size: 16px; margin: 18px 0 8px; }
        .stamp {
            max-width: 820px;
            margin: 0 auto 12px;
            color: #6b7280;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }
        .toolbar {
            max-width: 820px;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .toolbar button {
            padding: 8px 16px;
            border: 0;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
        }
        .toolbar span { color: #6b7280; font-size: 12px; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
            .toolbar, .stamp { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" data-action="print">Печать</button>
        <span>Отметка о печати ставится на карточке документа — журнал ведёт модуль, а не браузер.</span>
    </div>

    <div class="stamp">
        <span><?php echo HTML::chars($document->number); ?></span>
        <span>
            <?php echo HTML::chars((string) $document->template_code); ?>
            <?php if ($document->template_version): ?>, версия <?php echo (int) $document->template_version; ?><?php endif; ?>
        </span>
    </div>

    <div class="sheet">
        <?php echo $body; ?>
    </div>
</body>
</html>
