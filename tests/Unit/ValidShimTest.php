<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Проверка PHP 8.1+ совместимости валидации (application/classes/Valid.php).
 *
 * До появления перекрытия Valid::max_length(NULL, ...) вызывал E_DEPRECATED,
 * который Kohana превращал в ErrorException — сохранение моделей падало.
 */
class ValidShimTest extends TestCase
{
    public function test_override_is_active()
    {
        $reflection = new \ReflectionClass('Valid');
        $file = str_replace('\\', '/', $reflection->getFileName());

        $this->assertContains('/application/classes/Valid.php', $file,
            'Должно использоваться перекрытие Valid из application/classes');
    }

    public function test_null_values_are_accepted_by_length_rules()
    {
        $this->assertTrue(\Valid::max_length(NULL, 10));
        $this->assertTrue(\Valid::min_length(NULL, 5));
        $this->assertTrue(\Valid::exact_length(NULL, 5));
    }

    public function test_null_date_does_not_raise_deprecation()
    {
        $this->assertTrue(\Valid::date(NULL));
        $this->assertTrue(\Valid::date(''));
        $this->assertTrue(\Valid::date('2026-01-15'));
        $this->assertFalse(\Valid::date('not-a-date'));
    }

    public function test_length_rules_still_work()
    {
        $this->assertTrue(\Valid::max_length('abc', 10));
        $this->assertFalse(\Valid::max_length('abcdef', 3));
        $this->assertTrue(\Valid::min_length('abcdef', 3));
        $this->assertFalse(\Valid::min_length('ab', 3));
    }

    public function test_email_rule()
    {
        $this->assertTrue(\Valid::email('admin@promerp.local'));
        $this->assertFalse(\Valid::email('broken-email'));
        $this->assertFalse(\Valid::email(NULL));
    }

    public function test_no_deprecation_is_raised_for_null()
    {
        $errors = [];

        set_error_handler(function ($no, $str) use (&$errors) {
            $errors[] = $str;
            return TRUE;
        }, E_ALL);

        \Valid::max_length(NULL, 10);
        \Valid::min_length(NULL, 10);
        \Valid::date(NULL);
        \Valid::digit(NULL);
        \Valid::decimal(NULL);
        \Valid::color(NULL);

        restore_error_handler();

        $deprecations = array_filter($errors, function ($e) {
            return strpos($e, 'Deprecated') !== FALSE OR strpos($e, 'deprecated') !== FALSE;
        });

        $this->assertSame([], array_values($deprecations), 'Правила валидации не должны вызывать E_DEPRECATED на NULL');
    }
}
