<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтверждение регистрации</title>
</head>
<body>
    <h2>Здравствуйте, <?php echo HTML::chars($username); ?>!</h2>
    <p>Благодарим вас за регистрацию на сайте <?php echo HTML::chars($site_name); ?>.</p>
    <p>Для завершения процесса регистрации и подтверждения вашего адреса электронной почты, пожалуйста, перейдите по следующей ссылке:</p>
    <p><a href="<?php echo $confirmation_link; ?>"><?php echo $confirmation_link; ?></a></p>
    <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
    <br>
</body>
</html>