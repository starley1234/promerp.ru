<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроль целостности ядра фреймворка (Konine 3.4.0).
 *
 * system/ и modules/ — это фреймворк, их менять нельзя.
 * Все доработки должны жить в application/ (каскад Kohana).
 *
 * Паспорт (application/framework-manifest.php) содержит хэши файлов
 * фреймворка для сверки.
 *
 * @package Dev
 */
class Dev_Framework {

    /**
     * @var string
     */
    protected $_root;

    /**
     * @var string
     */
    protected $_manifest_file;

    /**
     * @var array
     */
    protected $_framework_dirs = ['system', 'modules'];

    public function __construct(?string $root = NULL)
    {
        $this->_root = $root !== NULL ? rtrim($root, '/') : realpath(APPPATH.'..');
        $this->_manifest_file = APPPATH.'framework-manifest.php';
    }

    /**
     * Путь к файлу паспорта.
     *
     * @return string
     */
    public function manifest_file(): string
    {
        return $this->_manifest_file;
    }

    /**
     * Загрузить текущий паспорт фреймворка.
     *
     * @return array
     */
    public function load_manifest(): array
    {
        if ( ! is_file($this->_manifest_file))
        {
            return [];
        }

        $data = include $this->_manifest_file;

        return is_array($data) ? $data : [];
    }

    /**
     * Просканировать файлы фреймворка и вернуть относительные пути и sha1.
     *
     * @return array
     */
    public function scan(): array
    {
        $files = [];

        foreach ($this->_framework_dirs as $dir)
        {
            $path = $this->_root.'/'.$dir;

            if ( ! is_dir($path))
            {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item)
            {
                if ( ! $item->isFile())
                {
                    continue;
                }

                $pathname = $item->getPathname();
                $rel = ltrim(str_replace($this->_root, '', $pathname), '/\\');
                $rel = str_replace('\\', '/', $rel);

                $files[$rel] = sha1_file($pathname);
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Сверить текущие файлы фреймворка с паспортом.
     *
     * @return array
     */
    public function compare(): array
    {
        $manifest = $this->load_manifest();
        $current  = $this->scan();

        $modified = [];
        $added    = [];
        $removed  = [];

        foreach ($current as $file => $hash)
        {
            if ( ! isset($manifest[$file]))
            {
                $added[] = $file;
            }
            elseif ($manifest[$file] !== $hash)
            {
                $modified[] = $file;
            }
        }

        foreach ($manifest as $file => $hash)
        {
            if ( ! isset($current[$file]))
            {
                $removed[] = $file;
            }
        }

        return [
            'manifest' => count($manifest),
            'current'  => count($current),
            'modified' => $modified,
            'added'    => $added,
            'removed'  => $removed,
        ];
    }

    /**
     * Обновить паспорт фреймворка.
     *
     * @return int Количество зафиксированных файлов
     */
    public function update_manifest(): int
    {
        $current = $this->scan();

        $content = "<?php defined('SYSPATH') or die('No direct script access.');\n\n"
            . "/**\n"
            . " * Паспорт целостности фреймворка Konine 3.4.0 (system/, modules/).\n"
            . " * Сгенерирован задачей bin/console dev:framework --update\n"
            . " */\n"
            . "return [\n";

        foreach ($current as $file => $hash)
        {
            $content .= "    ".var_export($file, TRUE)." => ".var_export($hash, TRUE).",\n";
        }

        $content .= "];\n";

        file_put_contents($this->_manifest_file, $content);

        return count($current);
    }
}
