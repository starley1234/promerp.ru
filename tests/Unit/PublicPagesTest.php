<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Открытые страницы сайта: главная, о сервисе, условия использования,
 * политика конфиденциальности, обратная связь, вход, документация и демо.
 *
 * Проверяются обещания, а не отдельные строки:
 *
 *  - страницы открыты анонимному посетителю (их читают до регистрации, ради
 *    юридического текста вход не требуют);
 *  - каждая открытая страница собрана по эталону: один h1, корень без своих
 *    полей, без `text-3xl`, `rounded-2xl` и градиентов — вид не должен
 *    расходиться между разделами;
 *  - условия и политика закрыты от поисковой выдачи: мета-тег, заголовок
 *    ответа и robots.txt;
 *  - главная отдаёт поисковику канонический адрес и структурированные
 *    данные, а ответы на частые вопросы совпадают с текстом на странице;
 *  - каталог модулей закрыт от гостей, а главная документации не показывает
 *    ссылок, которые ведут на отказ.
 */
class PublicPagesTest extends TestCase
{
    /** @var array Проверяемые страницы: адрес => заголовок страницы */
    protected $pages = array(
        '/about'   => 'О сервисе',
        '/terms'   => 'Условия использования',
        '/privacy' => 'Политика конфиденциальности',
    );

    /**
     * Гостевой режим: снимаем вход, который мог остаться от других тестов,
     * и поднимаем сессию — в CLI она сама не стартует, и «гость» без сессии
     * получает 403 на любой странице.
     *
     * @return void
     */
    protected function become_guest()
    {
        /*
         * Прошлый тест мог оставить вход в сессии, а после `logout(TRUE)`
         * (сессия разрушена) писать в неё уже некуда — и тогда `logged_in()`
         * молча возвращал TRUE, а `/` отвечал рабочим местом вместо главной.
         * Снимаем вход явно и до, и после: тест отвечает за гостевой режим,
         * а не за то, в каком состоянии его застала сессия.
         */
        $_SESSION = array();

        try
        {
            \Session::instance()->delete('auth_user');
        }
        catch (\Throwable $e)
        {
            // Сессии могло не быть вовсе.
        }

        try
        {
            if (\Auth::instance()->logged_in())
            {
                \Auth::instance()->logout(TRUE);
            }
        }
        catch (\Throwable $e)
        {
            // Сессия в CLI могла быть уже уничтожена — гостю это не мешает.
        }

        if (session_status() !== PHP_SESSION_ACTIVE AND ! headers_sent())
        {
            @session_start();
        }

        $_SESSION = array();
    }

    public function test_route_leads_to_the_pages_controller()
    {
        $route = \Route::get('static_pages');

        $this->assertNotNull($route, 'Роут статических страниц не зарегистрирован');

        $defaults = $route->defaults();

        $this->assertSame('Pages', (string) $defaults['controller'],
            'Роут статических страниц ведёт не на Controller_Pages');

        foreach (array_keys($this->pages) as $url)
        {
            $action = ltrim($url, '/');

            $this->assertTrue(method_exists('Controller_Pages', 'action_'.$action),
                'Нет действия Controller_Pages::action_'.$action);
        }
    }

    public function test_pages_are_open_to_a_guest()
    {
        $this->become_guest();

        foreach ($this->pages as $url => $title)
        {
            $response = \Request::factory($url)->execute();

            $this->assertSame(200, $response->status(),
                $url.' должен открываться без входа, а не отвечать отказом');

            $this->assertStringContainsString($title, $response->body(),
                $url.': страница отдала не тот текст');

            $this->assertStringNotContainsString('Доступ ограничен', $response->body(),
                $url.': гость снова видит страницу отказа');
        }
    }

    public function test_terms_and_privacy_are_closed_from_index()
    {
        $this->become_guest();

        foreach (array('/terms', '/privacy') as $url)
        {
            $response = \Request::factory($url)->execute();

            $this->assertStringContainsString('noindex', (string) $response->headers('X-Robots-Tag'),
                $url.': в заголовке ответа нет X-Robots-Tag: noindex');

            $this->assertMatchesRegularExpression('#<meta name="robots" content="noindex[^"]*"#',
                $response->body(),
                $url.': в разметке нет мета-тега robots с noindex');
        }

        // Страница о сервисе индексируется: её и должны находить поисковики.
        $response = \Request::factory('/about')->execute();

        $this->assertStringNotContainsString('noindex', (string) $response->headers('X-Robots-Tag'),
            '/about должен быть открыт для индексации');
    }

    public function test_robots_file_keeps_legal_pages_out()
    {
        $robots = @file_get_contents(DOCROOT.'robots.txt');

        $this->assertNotEmpty($robots, 'public_html/robots.txt отсутствует');

        $this->assertMatchesRegularExpression('#^\s*Disallow:\s*/terms\s*$#m', $robots);
        $this->assertMatchesRegularExpression('#^\s*Disallow:\s*/privacy\s*$#m', $robots);
    }

    /**
     * Открытые страницы, вид которых держим одинаковым.
     *
     * @return  array  адрес => что на странице должно быть
     */
    protected function guest_pages()
    {
        return array(
            '/'                      => 'PromERP',
            '/about'                 => 'О сервисе',
            '/terms'                 => 'Условия использования',
            '/privacy'               => 'Политика конфиденциальности',
            '/contact'               => 'Связаться с нами',
            '/auth/login'            => 'Вход в систему',
            '/auth/register'         => 'Создать аккаунт',
            '/auth/forgot_password'  => 'Восстановление пароля',
            '/docs'                  => 'Документация',
            '/docs/processes'        => 'Бизнес-процессы',
            '/docs/architecture'     => 'Архитектура',
            '/docs/mcp'              => 'MCP',
            '/demo'                  => 'демонстрац',
        );
    }

    /**
     * Каждая открытая страница собрана по эталону интерфейса.
     *
     * Раньше `/contact`, `/auth/*` и страницы ошибок верстались сами: свои
     * отступы поверх отступов `<main>`, Bootstrap-классы, `text-3xl` и ни
     * одного h1. Проверка ловит возврат к такому виду.
     */
    public function test_guest_pages_follow_the_reference_layout()
    {
        $this->become_guest();

        foreach (array_keys($this->guest_pages()) as $url)
        {
            $body = (string) \Request::factory($url)->execute()->body();

            $this->assertMatchesRegularExpression('#<main[^>]*>\s*<div class="(?:mx-auto )?(?:max-w-\S+ )?space-y-6"#',
                $body,
                $url.': корень представления — <div> с `space-y-6`, без своих полей');

            $this->assertMatchesRegularExpression('#<main[^>]*>([\s\S]*?)</main>#', $body);
            \preg_match('#<main[^>]*>([\s\S]*?)</main>#', $body, $main);

            $this->assertSame(1, \preg_match_all('/<h1[\s>]/', $main[1]),
                $url.': на странице ровно один h1');

            foreach (array('text-3xl', 'text-4xl', 'rounded-2xl', 'rounded-3xl', 'bg-gradient') as $forbidden)
            {
                $this->assertStringNotContainsString($forbidden, $main[1],
                    $url.': в разметке страницы встретился запрещённый эталоном класс '.$forbidden);
            }
        }
    }

    /**
     * Каталог модулей — внутренний раздел: гостю он закрыт, а главная
     * документации не показывает ссылок, ведущих на отказ.
     */
    public function test_module_catalogue_is_closed_from_a_guest()
    {
        $this->become_guest();

        $response = \Request::factory('/docs/modules')->execute();

        $this->assertSame(403, $response->status(),
            'каталог модулей должен быть закрыт от гостя, а не отдавать схему системы');

        $index = (string) \Request::factory('/docs')->execute()->body();

        $this->assertStringNotContainsString('href="/docs/modules', $index,
            'главная документации не должна ссылаться на закрытый раздел');
    }

    /**
     * Главная отдаёт поисковику то, чем его убеждают: канонический адрес,
     * заголовок и описание под сниппет, структурированные данные.
     */
    public function test_home_page_is_described_for_search_engines()
    {
        $this->become_guest();

        $body = (string) \Request::factory('/')->execute()->body();

        $url = \rtrim((string) \Kohana::$config->load('site_config')->get('site_url'), '/').'/';

        $this->assertStringContainsString('<link rel="canonical" href="'.$url.'">', $body,
            'у главной нет канонического адреса — выдача дробится по доменам');
        $this->assertStringContainsString('<meta property="og:url"', $body);
        $this->assertStringContainsString('<meta name="twitter:card"', $body);

        \preg_match('#<title>(.*?)</title>#', $body, $title);
        $this->assertLessThanOrEqual(70, \mb_strlen($title[1], 'UTF-8'),
            'заголовок главной длиннее сниппета: '.$title[1]);

        \preg_match('#<meta name="description" content="(.*?)"#', $body, $description);
        $this->assertLessThanOrEqual(200, \mb_strlen($description[1], 'UTF-8'),
            'описание главной обрежется в сниппете');
        $this->assertGreaterThan(80, \mb_strlen($description[1], 'UTF-8'),
            'описание главной слишком короткое, чтобы ответить на вопрос «что это»');

        $this->assertSame(1, \preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $ld_match),
            'у главной нет структурированных данных');
        $data = \json_decode($ld_match[1], TRUE);

        $this->assertIsArray($data, 'структурированные данные главной не читаются как JSON');

        $types = array();
        foreach ($data['@graph'] as $node)
        {
            $types[] = $node['@type'];
        }

        $this->assertContains('SoftwareApplication', $types,
            'поисковик должен понимать, что перед ним программный продукт');
        $this->assertContains('FAQPage', $types,
            'ответы на частые вопросы должны попадать в выдачу');
    }

    /**
     * Ответы в структурированных данных совпадают с текстом на странице:
     * разойтись они не могут, потому что ведёт их одно место.
     */
    public function test_home_faq_is_the_same_on_the_page_and_in_structured_data()
    {
        $this->become_guest();

        $body = (string) \Request::factory('/')->execute()->body();

        \preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $ld_match);
        $data = \json_decode($ld_match[1], TRUE);

        $questions = array();
        foreach ($data['@graph'] as $node)
        {
            if ($node['@type'] === 'FAQPage')
            {
                foreach ($node['mainEntity'] as $question)
                {
                    $questions[] = array($question['name'], $question['acceptedAnswer']['text']);
                }
            }
        }

        $this->assertNotEmpty($questions, 'на главной нет вопросов для выдачи');

        foreach ($questions as $pair)
        {
            $this->assertStringContainsString($pair[0], $body,
                'вопрос из структурированных данных не показан человеку: '.$pair[0]);
            $this->assertStringContainsString($pair[1], $body,
                'ответ на странице расходится с ответом для поисковика: '.$pair[0]);
        }
    }

    /**
     * Публичную навигацию гостю показывает боковая панель. Топбар остаётся
     * местом для глобальных действий и не повторяет те же ссылки.
     */
    public function test_guest_topbar_does_not_repeat_sidebar_navigation()
    {
        $this->become_guest();

        $body = (string) \Request::factory('/')->execute()->body();

        $this->assertSame(1, \preg_match(
            '#<!-- Верхняя панель \(Topbar Header\) -->\s*<header\b([\s\S]*?)</header>#',
            $body,
            $topbar
        ), 'топбар главной должен быть в общем шаблоне');

        $this->assertStringNotContainsString('aria-label="Разделы сайта"', $topbar[1],
            'ссылки гостевых разделов дублируются в топбаре');
        $this->assertStringContainsString('aria-label="Основная навигация"', $body,
            'боковую навигацию нельзя убирать вместе с дублем в топбаре');
        $this->assertStringContainsString('href="/about"', $body,
            'гостю по-прежнему доступен раздел «О системе»');
        $this->assertStringContainsString('href="/docs"', $body,
            'гостю по-прежнему доступна документация');
    }

    /**
     * Главная сначала показывает приглашение и короткие тезисы; подробности
     * открываются по выбору, а декоративный макет терминала не выдаётся за
     * работающий экран.
     */
    public function test_home_page_uses_a_compact_interactive_presentation()
    {
        $this->become_guest();

        $body = (string) \Request::factory('/')->execute()->body();
        $this->assertMatchesRegularExpression('#<main[^>]*>([\s\S]*?)</main>#', $body);
        \preg_match('#<main[^>]*>([\s\S]*?)</main>#', $body, $main);

        $this->assertStringNotContainsString('Как это выглядит в цеху', $main[1],
            'статичная иллюстрация занимала место и была похожа на работающий экран');
        $this->assertStringContainsString('Выберите карточку, чтобы посмотреть подробнее.', $main[1],
            'коротким тезисам не хватает понятного способа раскрыть подробности');
        $this->assertGreaterThanOrEqual(10, \preg_match_all('#<details\b#', $main[1]),
            'результаты и ответы на вопросы должны раскрываться по запросу');

        if (\strpos($main[1], 'aria-labelledby="demo-roles"') !== FALSE)
        {
            \preg_match('#<section[^>]*aria-labelledby="demo-roles"[\s\S]*?</section>#', $main[1], $demo_card);
            $this->assertStringContainsString('border-blue-200', $demo_card[0],
                'интерактивное демо должно быть заметно, но оставаться в синей палитре эталона');
            $this->assertStringContainsString('action="/demo/enter"', $demo_card[0],
                'выбор роли должен вести в рабочий демонстрационный экран');
            $this->assertStringContainsString('Нажмите на роль', $demo_card[0],
                'гостю должно быть понятно, что выбор роли интерактивен');
        }
    }

    /**
     * Признак потери связи не светится при рабочей сети.
     *
     * Скрывает его атрибут `hidden`, то есть `display: none` из базового слоя
     * Tailwind. Стоит повесить на тот же элемент display-утилиту (`inline-flex`)
     * — она окажется в слое утилит, позже и с той же специфичностью, и
     * перебьёт `[hidden]`: индикатор будет виден всегда, а человек в цеху
     * решит, что система не работает. У обёртки, которая прячется атрибутом,
     * не должно быть своей display-утилиты.
     */
    public function test_offline_indicator_is_hidden_while_the_connection_is_up()
    {
        $this->become_guest();

        $body = (string) \Request::factory('/terms')->execute()->body();

        $this->assertSame(1, \preg_match('#<span id="connection-status"[^>]*>#', $body, $tag),
            'в шапке должен быть признак потери связи: в цеху сеть пропадает');

        $this->assertMatchesRegularExpression('#\shidden(?=[\s>])#', $tag[0],
            'признак потери связи показан при рабочей сети: скрывать его должен атрибут hidden');

        $utilities = array('block', 'inline', 'inline-block', 'flex', 'inline-flex',
            'grid', 'inline-grid', 'contents', 'flow-root');

        \preg_match('#\bclass="([^"]*)"#', $tag[0], $classes);
        $clashing = isset($classes[1]) ? \array_intersect($utilities, \preg_split('#\s+#', $classes[1])) : array();

        $this->assertSame(array(), $clashing,
            'display-утилита на скрытом элементе перебивает [hidden]: индикатор станет виден всегда');
    }

    /**
     * Открытые страницы должны быть в дымовом прогоне: иначе их падение
     * замечают только посетители.
     */
    public function test_pages_are_covered_by_smoke()
    {
        $urls = array();

        foreach ((new \Service_Dev_Smoke)->urls() as $entry)
        {
            $urls[] = $entry['url'];
        }

        foreach (array_keys($this->pages) as $url)
        {
            $this->assertContains($url, $urls, $url.' не проверяется в dev:smoke');
        }
    }
}
