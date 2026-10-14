<!doctype html>
<html lang="ru">
<head>
    <meta charSet="utf-8" />
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, shrink-to-fit=no, viewport-fit=cover'>
    <meta http-equiv='X-UA-Compatible' content='ie=edge'>
    <title>Обработка токена Яндекса</title>
    <style>
        html, body { background: #eee; text-align: center; padding-top: 50px; }
    </style>
    <script src="https://yastatic.net/s3/passport-sdk/autofill/v1/sdk-suggest-token-with-polyfills-latest.js"></script>
</head>
<body>
    <p>Обработка авторизации...</p>
    <script>
        window.onload = function() {
            // Этот скрипт отправляет токен на родительскую страницу (action_login)
            // через postMessage.
            window.YaSendSuggestToken("<?php echo $token_page_origin; ?>", {
                // Здесь можно передать дополнительные данные, если нужно
                // например, флаг успешной авторизации
                auth_success: true
            });
        };
    </script>
</body>
</html>