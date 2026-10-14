<?php defined('SYSPATH') or die('No direct access');

/**
 * Паспорт инстанса: заголовки, описание, адрес сайта.
 * Для конкретной установки переопределяется переменными окружения.
 */
$env = static function ($key, $default) {
    $value = getenv($key);
    return ($value === FALSE OR $value === '') ? $default : $value;
};

$name = $env('SITE_NAME', 'Test.PromERP');
$url  = $env('SITE_URL', 'https://test.promerp.ru');

return array(
    'title'           => $name.' — Системы управления предприятием и автоматизация бизнеса',
    'description'     => 'Экспертные решения по внедрению ERP-систем, автоматизации бизнес-процессов и промышленному софту.',
    'h1'              => $name.' — автоматизация и управление предприятием',
    'keywords'        => 'ERP, автоматизация бизнеса, управление производством, внедрение ERP, CRM, промышленное ПО, оптимизация процессов',
    'pagedescription' => $name.' предлагает профессиональные инструменты и руководства по внедрению ERP-систем.',
    'site_url'        => $url,
    'admin_mail'      => $env('ADMIN_MAIL', 'info@promerp.ru'),
    'domain'          => $env('SITE_DOMAIN', 'Test.promerp.ru'),
    'site_name'       => $name,
    'site_logo'       => '<i class="fas fa-cog mr-2"></i>',
);
