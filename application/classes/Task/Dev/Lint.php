<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Проверка синтаксиса PHP без внешних зависимостей (token_get_all + TOKEN_PARSE).
 *
 *   bin/console dev:lint                    — весь проект
 *   bin/console dev:lint --changed          — только изменённые по git файлы
 *   bin/console dev:lint --path=application — конкретный каталог
 *
 * @package Minion
 */
class Task_Dev_Lint extends Minion_Task {

    protected $_options = [
        'changed' => FALSE,
        'path'    => NULL,
        'quiet'   => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $quiet   = $this->_flag($options, 'quiet');
        $changed = $this->_flag($options, 'changed');

        $paths = [];

        $path_option = $this->_value($options, 'path');

        if ($path_option)
        {
            foreach (explode(',', $path_option) as $path)
            {
                $paths[] = rtrim($this->_root().'/'.trim($path), '/');
            }
        }
        else
        {
            foreach (['application', 'modules', 'system', 'tests', 'bin', 'public_html'] as $path)
            {
                $paths[] = $this->_root().'/'.$path;
            }
        }

        $files = $this->_collect($paths, $changed);

        echo 'Проверка синтаксиса: '.count($files).' файлов'.PHP_EOL;

        $errors = [];

        foreach ($files as $file)
        {
            $error = $this->_check($file);

            if ($error !== NULL)
            {
                $errors[$file] = $error;
                echo '  [FAIL] '.$this->_relative($file).PHP_EOL.'         '.$error.PHP_EOL;
            }
        }

        if ($errors)
        {
            echo PHP_EOL.'Файлов с ошибками: '.count($errors).PHP_EOL;
            exit(1);
        }

        if ( ! $quiet)
        {
            echo '  [ok]   синтаксис в порядке'.PHP_EOL;
        }
    }

    /**
     * @param   array    $paths
     * @param   boolean  $changed
     * @return  array
     */
    protected function _collect(array $paths, $changed = FALSE)
    {
        $only_changed = [];

        if ($changed)
        {
            $output = [];
            exec('git -C '.escapeshellarg($this->_root()).' status --porcelain 2>/dev/null', $output);

            foreach ($output as $line)
            {
                $file = trim(substr($line, 2));

                if (substr($file, -4) === '.php')
                {
                    $only_changed[realpath($this->_root().'/'.$file)] = TRUE;
                }
            }
        }

        $files = [];

        foreach ($paths as $path)
        {
            if (is_file($path))
            {
                $files[] = $path;
                continue;
            }

            if ( ! is_dir($path))
            {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file)
            {
                if ( ! $file->isFile() OR substr($file->getFilename(), -4) !== '.php')
                {
                    continue;
                }

                $pathname = $file->getPathname();

                foreach (['/vendor/', '/application/logs/', '/application/cache/'] as $skip)
                {
                    if (strpos($pathname, DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($skip, '/')).DIRECTORY_SEPARATOR) !== FALSE)
                    {
                        continue 2;
                    }
                }

                if ($changed AND ! isset($only_changed[realpath($pathname)]))
                {
                    continue;
                }

                $files[] = $pathname;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param   string  $file
     * @return  string|null  текст ошибки или NULL
     */
    protected function _check($file)
    {
        $code = file_get_contents($file);

        try
        {
            token_get_all($code, TOKEN_PARSE);
        }
        catch (ParseError $e)
        {
            return $e->getMessage().' (строка '.$e->getLine().')';
        }

        return NULL;
    }

    /**
     * @param   string  $path
     * @return  string
     */
    protected function _relative($path)
    {
        return str_replace($this->_root().'/', '', $path);
    }
}
