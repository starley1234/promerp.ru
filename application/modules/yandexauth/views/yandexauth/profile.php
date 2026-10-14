<!doctype html>
<html lang="ru">
<head>
    <meta charSet="utf-8" />
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, shrink-to-fit=no, viewport-fit=cover'>
    <meta http-equiv='X-UA-Compatible' content='ie=edge'>
    <title>Профиль пользователя</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .user-info { border: 1px solid #ccc; padding: 20px; margin: 20px auto; max-width: 600px; text-align: left; }
        .user-info p { margin: 5px 0; }
        .user-info strong { display: inline-block; width: 150px; }
    </style>
</head>
<body>
    <h1>Добро пожаловать, <?php echo HTML::chars($user_info['login']); ?>!</h1>

    <div class="user-info">
        <h2>Ваши данные из Яндекс ID:</h2>
        <p><strong>ID:</strong> <?php echo HTML::chars($user_info['id']); ?></p>
        <p><strong>Логин:</strong> <?php echo HTML::chars($user_info['login']); ?></p>
        <?php if (isset($user_info['first_name'])): ?>
            <p><strong>Имя:</strong> <?php echo HTML::chars($user_info['first_name']); ?></p>
        <?php endif; ?>
        <?php if (isset($user_info['last_name'])): ?>
            <p><strong>Фамилия:</strong> <?php echo HTML::chars($user_info['last_name']); ?></p>
        <?php endif; ?>
        <?php if (isset($user_info['default_email'])): ?>
            <p><strong>Email:</strong> <?php echo HTML::chars($user_info['default_email']); ?></p>
        <?php endif; ?>
        <?php if (isset($user_info['sex'])): ?>
            <p><strong>Пол:</strong> <?php echo HTML::chars($user_info['sex']); ?></p>
        <?php endif; ?>
        <?php if (isset($user_info['birthday'])): ?>
            <p><strong>Дата рождения:</strong> <?php echo HTML::chars($user_info['birthday']); ?></p>
        <?php endif; ?>
        <?php if (isset($user_info['default_avatar_id']) && !$user_info['is_avatar_empty']): ?>
            <p><strong>Аватар:</strong> <br>
                <img src="https://avatars.yandex.net/get-yapic/<?php echo HTML::chars($user_info['default_avatar_id']); ?>/islands-200" alt="Аватар пользователя" class="h-[100px] w-[100px] rounded-full">
            </p>
        <?php endif; ?>
        <p><strong>OAuth Токен (для отладки):</strong> <textarea rows="5" cols="50" readonly><?php echo HTML::chars($oauth_token); ?></textarea></p>
    </div>

    <p><a href="<?php echo URL::site('yandexauth/logout'); ?>">Выйти</a></p>
</body>
</html>