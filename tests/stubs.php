<?php
/**
 * Минимальная замена PHPUnit для запуска тестов без composer
 * (актуально для окружений без доступа к packagist).
 *
 * Если PHPUnit установлен — этот файл не загружается.
 */

namespace PHPUnit\Framework;

class AssertionFailedError extends \Exception {}

class TestCase
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    public static function assertTrue($condition, $message = '')
    {
        if ($condition !== TRUE)
        {
            throw new AssertionFailedError($message ?: 'Ожидалось TRUE');
        }
    }

    public static function assertFalse($condition, $message = '')
    {
        if ($condition !== FALSE)
        {
            throw new AssertionFailedError($message ?: 'Ожидалось FALSE');
        }
    }

    public static function assertEquals($expected, $actual, $message = '')
    {
        if ($expected != $actual)
        {
            throw new AssertionFailedError($message
                ?: sprintf('Ожидалось %s, получено %s', json_encode($expected), json_encode($actual)));
        }
    }

    public static function assertSame($expected, $actual, $message = '')
    {
        if ($expected !== $actual)
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось !== %s', json_encode($actual)));
        }
    }

    public static function assertNotSame($expected, $actual, $message = '')
    {
        if ($expected === $actual)
        {
            throw new AssertionFailedError($message ?: 'Значения не должны совпадать');
        }
    }

    public static function assertNull($actual, $message = '')
    {
        if ($actual !== NULL)
        {
            throw new AssertionFailedError($message ?: 'Ожидалось NULL');
        }
    }

    public static function assertNotNull($actual, $message = '')
    {
        if ($actual === NULL)
        {
            throw new AssertionFailedError($message ?: 'Ожидалось не NULL');
        }
    }

    public static function assertNotEmpty($actual, $message = '')
    {
        if (empty($actual))
        {
            throw new AssertionFailedError($message ?: 'Ожидалось непустое значение');
        }
    }

    public static function assertLessThan($expected, $actual, $message = '')
    {
        if ( ! ($actual < $expected))
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось < %s, получено %s', $expected, $actual));
        }
    }

    public static function assertLessThanOrEqual($expected, $actual, $message = '')
    {
        if ( ! ($actual <= $expected))
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось <= %s, получено %s', $expected, $actual));
        }
    }

    public static function assertGreaterThanOrEqual($expected, $actual, $message = '')
    {
        if ( ! ($actual >= $expected))
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось >= %s, получено %s', $expected, $actual));
        }
    }

    public static function assertStringContainsString($needle, $haystack, $message = '')
    {
        if (strpos((string) $haystack, (string) $needle) === FALSE)
        {
            throw new AssertionFailedError($message ?: sprintf('Строка не содержит "%s"', $needle));
        }
    }

    public static function assertInstanceOf($class, $actual, $message = '')
    {
        if ( ! ($actual instanceof $class))
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидался объект %s', $class));
        }
    }

    public static function assertGreaterThan($expected, $actual, $message = '')
    {
        if ( ! ($actual > $expected))
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось > %s, получено %s', $expected, $actual));
        }
    }

    public static function assertContains($needle, $haystack, $message = '')
    {
        $found = is_array($haystack)
            ? in_array($needle, $haystack, TRUE)
            : strpos((string) $haystack, (string) $needle) !== FALSE;

        if ( ! $found)
        {
            throw new AssertionFailedError($message ?: 'Значение не найдено');
        }
    }

    public static function assertStringNotContainsString($needle, $haystack, $message = '')
    {
        if (strpos((string) $haystack, (string) $needle) !== FALSE)
        {
            throw new AssertionFailedError($message ?: sprintf('Строка содержит "%s"', $needle));
        }
    }

    public static function assertMatchesRegularExpression($pattern, $string, $message = '')
    {
        if ( ! preg_match($pattern, (string) $string))
        {
            throw new AssertionFailedError($message ?: sprintf('Строка "%s" не соответствует шаблону %s', $string, $pattern));
        }
    }

    public static function assertNotContains($needle, $haystack, $message = '')
    {
        $found = is_array($haystack)
            ? in_array($needle, $haystack, TRUE)
            : strpos((string) $haystack, (string) $needle) !== FALSE;

        if ($found)
        {
            throw new AssertionFailedError($message ?: 'Значение найдено, хотя не должно');
        }
    }

    public static function assertCount($expected, $haystack, $message = '')
    {
        if (count($haystack) !== $expected)
        {
            throw new AssertionFailedError($message ?: sprintf('Ожидалось элементов: %d, получено: %d', $expected, count($haystack)));
        }
    }

    public static function assertFileExists($path, $message = '')
    {
        if ( ! is_file($path))
        {
            throw new AssertionFailedError($message ?: sprintf('Файл "%s" не найден', $path));
        }
    }

    public static function assertIsString($actual, $message = '')
    {
        if ( ! is_string($actual))
        {
            throw new AssertionFailedError($message ?: 'Ожидалась строка');
        }
    }

    public static function assertEmpty($actual, $message = '')
    {
        if ( ! empty($actual))
        {
            throw new AssertionFailedError($message ?: 'Ожидалось пустое значение');
        }
    }

    public static function assertArrayHasKey($key, $array, $message = '')
    {
        if ( ! is_array($array) OR ! array_key_exists($key, $array))
        {
            throw new AssertionFailedError($message ?: sprintf('Ключ "%s" отсутствует в массиве', $key));
        }
    }

    public static function assertArrayNotHasKey($key, $array, $message = '')
    {
        if (array_key_exists($key, $array))
        {
            throw new AssertionFailedError($message ?: sprintf('Ключ %s не должен присутствовать', json_encode($key)));
        }
    }

    public static function assertStringEndsWith($suffix, $string, $message = '')
    {
        if ( ! is_string($string) OR substr($string, -strlen($suffix)) !== $suffix)
        {
            throw new AssertionFailedError($message ?: sprintf('Строка %s не заканчивается на %s', json_encode($string), json_encode($suffix)));
        }
    }

    public static function assertStringStartsWith($prefix, $string, $message = '')
    {
        if ( ! is_string($string) OR strpos($string, $prefix) !== 0)
        {
            throw new AssertionFailedError($message ?: sprintf('Строка %s не начинается с %s', json_encode($string), json_encode($prefix)));
        }
    }

    public static function assertFileDoesNotExist($path, $message = '')
    {
        if (file_exists($path))
        {
            throw new AssertionFailedError($message ?: sprintf('Файл %s не должен существовать', $path));
        }
    }

    public static function assertIsArray($actual, $message = '')
    {
        if ( ! is_array($actual))
        {
            throw new AssertionFailedError($message ?: 'Ожидался массив');
        }
    }

    public static function markTestSkipped($message = '')
    {
        throw new \PHPUnit\Framework\SkippedTestError($message);
    }

    // -- Ожидаемые исключения (минимальная замена PHPUnit) ----------------------

    /** @var string|null Класс ожидаемого исключения */
    protected $expected_exception;

    /** @var string|null Подстрока, которая должна быть в сообщении */
    protected $expected_exception_message;

    /** @var string|null Регулярное выражение для сообщения */
    protected $expected_exception_pattern;

    /**
     * Ожидать исключение указанного класса.
     *
     * @param   string  $class
     * @return  void
     */
    public function expectException($class)
    {
        $this->expected_exception = ltrim($class, '\\');
    }

    /**
     * Ожидать подстроку в сообщении исключения.
     *
     * @param   string  $message
     * @return  void
     */
    public function expectExceptionMessage($message)
    {
        $this->expected_exception_message = $message;
    }

    /**
     * Ожидать совпадение сообщения исключения с регулярным выражением.
     *
     * @param   string  $pattern
     * @return  void
     */
    public function expectExceptionMessageMatches($pattern)
    {
        $this->expected_exception_pattern = $pattern;
    }

    /**
     * Ожидание исключения для раннера.
     *
     * @return array  ['class' => ?string, 'message' => ?string, 'pattern' => ?string]
     */
    public function expectedException()
    {
        return array(
            'class'   => $this->expected_exception,
            'message' => $this->expected_exception_message,
            'pattern' => $this->expected_exception_pattern,
        );
    }
}

namespace PHPUnit\Framework;

class SkippedTestError extends \Exception {}
