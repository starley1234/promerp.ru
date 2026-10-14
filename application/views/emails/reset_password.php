<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Письмо со ссылкой на сброс пароля.
 *
 * Ожидает: $username, $reset_link.
 * См. Controller_Erp100_Auth (запрос восстановления пароля).
 */
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #111827; line-height: 1.5">
    <p>Здравствуйте, <?php echo HTML::chars($username); ?>!</p>

    <p>
        Вы запросили восстановление пароля в системе PromERP.
        Чтобы задать новый пароль, перейдите по ссылке (действует 1 час):
    </p>

    <p>
        <a href="<?php echo HTML::chars($reset_link); ?>" style="color: #2563eb"><?php echo HTML::chars($reset_link); ?></a>
    </p>

    <p class="text-gray-500 text-[13px]">
        Если вы не запрашивали восстановление — просто проигнорируйте письмо,
        пароль останется прежним.
    </p>

    <p class="text-gray-500 text-[13px]">— Служба поддержки PromERP</p>
</div>
