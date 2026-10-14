<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фотографии с мобильного терминала (erp199_sqr).
 *
 * Телефон — «глупый клиент»: он присылает base64 или файл, а раскладку по
 * каталогам, проверку размера/типа, подсчёт контрольной суммы и запись в
 * реестр делает сервер. Файлы лежат в `public_html/uploads/erp199/<год>/<месяц>/`.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Media
{
    /** @var Service_Erp199_Media|null */
    protected static $_instance;

    /**
     * @return  Service_Erp199_Media
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Сохранить фото из $_FILES-совместимого массива.
     *
     * @param   array   $file   tmp_name, name, type, error, size
     * @param   array   $meta   token_id, object_id, purpose, uploaded_by, geo_lat, geo_lon
     * @return  array           запись erp_199_media
     */
    public function save_upload(array $file, array $meta = array())
    {
        if (empty($file['tmp_name']) OR ! is_file($file['tmp_name']))
        {
            throw new Kohana_Exception('Файл не получен');
        }

        if ( ! empty($file['error']))
        {
            throw new Kohana_Exception('Ошибка загрузки файла (код :code)', array(':code' => (int) $file['error']));
        }

        return $this->store(
            file_get_contents($file['tmp_name']),
            isset($file['name']) ? (string) $file['name'] : NULL,
            isset($file['type']) ? (string) $file['type'] : NULL,
            $meta
        );
    }

    /**
     * Сохранить фото из data-URI (base64) — так присылает приложение.
     *
     * @param   string  $data_uri
     * @param   array   $meta
     * @return  array
     */
    public function save_base64($data_uri, array $meta = array())
    {
        $data_uri = trim((string) $data_uri);

        if ($data_uri === '')
        {
            throw new Kohana_Exception('Пустое изображение');
        }

        $mime = NULL;
        $payload = $data_uri;

        if (preg_match('#^data:([\w/+.-]+);base64,(.*)$#s', $data_uri, $match))
        {
            $mime    = strtolower($match[1]);
            $payload = $match[2];
        }

        $binary = base64_decode($payload, TRUE);

        if ($binary === FALSE OR $binary === '')
        {
            throw new Kohana_Exception('Не удалось декодировать изображение (ожидается base64)');
        }

        return $this->store($binary, NULL, $mime, $meta);
    }

    /**
     * Записать бинарные данные в хранилище и реестр.
     *
     * @param   string  $binary
     * @param   string  $name
     * @param   string  $mime
     * @param   array   $meta
     * @return  array
     */
    protected function store($binary, $name, $mime, array $meta)
    {
        $config = (array) Kohana::$config->load('erp199_sqr.media');

        $max_size = (int) Arr::get($config, 'max_size', 10485760);

        if (strlen($binary) > $max_size)
        {
            throw new Kohana_Exception('Файл больше допустимого размера (:size МБ)', array(
                ':size' => round($max_size / 1048576, 1),
            ));
        }

        $mime = $this->normalize_mime($mime, $binary);
        $ext  = ($mime !== NULL) ? $this->extension($mime) : NULL;

        if ($ext === NULL)
        {
            // Не изображение (или формат вне списка): расширение угадывать
            // нельзя — иначе текстовый файл лёг бы на диск с именем .jpg и
            // отдавался бы как фотография.
            throw new Kohana_Exception('Формат не поддерживается: принимаются изображения JPEG, PNG и WebP');
        }
        $allowed = (array) Arr::get($config, 'extensions', array('jpg', 'jpeg', 'png', 'webp'));

        if ( ! in_array($ext, $allowed, TRUE))
        {
            throw new Kohana_Exception('Формат :ext не поддерживается', array(':ext' => $ext));
        }

        $relative = 'uploads/erp199/'.date('Y/m');
        $dir      = rtrim((string) Arr::get($config, 'path', DOCROOT.'uploads/erp199/'), '/');
        $dir     .= '/'.date('Y/m');

        if ( ! is_dir($dir) AND ! mkdir($dir, 0775, TRUE) AND ! is_dir($dir))
        {
            throw new Kohana_Exception('Не удалось создать каталог для фото: :dir', array(':dir' => $dir));
        }

        $file_name = 'sqr_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $path      = $dir.'/'.$file_name;

        if (file_put_contents($path, $binary) === FALSE)
        {
            throw new Kohana_Exception('Не удалось сохранить фото: :path', array(':path' => $path));
        }

        $result = DB::insert('erp_199_media', array(
                'token_id', 'object_id', 'purpose', 'file_path', 'file_name', 'mime',
                'size_bytes', 'sha256', 'geo_lat', 'geo_lon', 'uploaded_by',
            ))
            ->values(array(
                Arr::get($meta, 'token_id') ?: NULL,
                Arr::get($meta, 'object_id') ?: NULL,
                Arr::get($meta, 'purpose', 'object'),
                $relative.'/'.$file_name,
                mb_substr((string) $name, 0, 160) ?: $file_name,
                $mime,
                strlen($binary),
                hash('sha256', $binary),
                Arr::get($meta, 'geo_lat'),
                Arr::get($meta, 'geo_lon'),
                Arr::get($meta, 'uploaded_by') ?: NULL,
            ))
            ->execute();

        $id = is_array($result) ? (int) reset($result) : (int) $result;

        return $this->get($id);
    }

    /**
     * Запись реестра по id.
     *
     * @param   integer $id
     * @return  array
     */
    public function get($id)
    {
        $row = DB::select()->from('erp_199_media')->where('id', '=', (int) $id)->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Фото #:id не найдено', array(':id' => (int) $id));
        }

        $row['url'] = '/'.ltrim((string) $row['file_path'], '/');

        return $row;
    }

    /**
     * Фотографии наклейки или объекта (свежие сверху).
     *
     * @param   array   $filters  token_id, object_id, limit
     * @return  array
     */
    public function search(array $filters = array())
    {
        $limit = max(1, min(100, (int) Arr::get($filters, 'limit', 20)));

        $query = DB::select()->from('erp_199_media');

        if (Arr::get($filters, 'token_id'))
        {
            $query->where('token_id', '=', (int) $filters['token_id']);
        }

        if (Arr::get($filters, 'object_id'))
        {
            $query->where('object_id', '=', (int) $filters['object_id']);
        }

        $rows = $query->order_by('id', 'DESC')->limit($limit)->execute()->as_array();

        foreach ($rows as &$row)
        {
            $row['url'] = '/'.ltrim((string) $row['file_path'], '/');
        }
        unset($row);

        return $rows;
    }

    /**
     * MIME по данным файла, если клиент прислал пустое или неверное значение.
     *
     * @param   string  $mime
     * @param   string  $binary
     * @return  string|null  MIME изображения или NULL, если это не картинка
     */
    protected function normalize_mime($mime, $binary)
    {
        $mime = strtolower(trim((string) $mime));

        if ($mime !== '' AND strpos($mime, 'image/') === 0)
        {
            return $mime;
        }

        if (function_exists('finfo_open'))
        {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $found = finfo_buffer($finfo, $binary);
            finfo_close($finfo);

            if ($found AND strpos($found, 'image/') === 0)
            {
                return $found;
            }
        }

        // Разбор сигнатуры: сервер не должен падать из-за отсутствия fileinfo.
        if (substr($binary, 0, 3) === "\xFF\xD8\xFF")
        {
            return 'image/jpeg';
        }

        if (substr($binary, 0, 8) === "\x89PNG\r\n\x1a\n")
        {
            return 'image/png';
        }

        if (substr($binary, 0, 4) === 'RIFF' AND substr($binary, 8, 4) === 'WEBP')
        {
            return 'image/webp';
        }

        // Ни заявленный тип, ни сигнатура не говорят «это изображение».
        return NULL;
    }

    /**
     * Расширение файла по MIME.
     *
     * Расширение выводится только из проверенного типа содержимого: имя файла,
     * которое прислал клиент, доверять нельзя (иначе «photo.jpg» с текстом
     * внутри ляжет на диск фотографией).
     *
     * @param   string  $mime
     * @return  string|null  расширение или NULL, если формат не поддерживается
     */
    protected function extension($mime)
    {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        );

        return isset($map[$mime]) ? $map[$mime] : NULL;
    }
}
