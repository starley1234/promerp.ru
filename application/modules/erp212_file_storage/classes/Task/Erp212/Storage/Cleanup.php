<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Очистка хранилища erp212: просроченные временные ссылки и «осиротевшие» файлы.
 *
 *   bin/console erp212:storage:cleanup                     # ссылки: удалить просроченные, файлы: старше 7 дней
 *   bin/console erp212:storage:cleanup --days=30           # порог по возрасту файлов
 *   bin/console erp212:storage:cleanup --limit=500         # лимит записей за прогон
 *   bin/console erp212:storage:cleanup --dry-run           # только показать, что будет удалено
 *   bin/console erp212:storage:cleanup --links-only        # только ссылки
 *   bin/console erp212:storage:cleanup --files-only        # только файлы
 *
 * Файл удаляется, только если на него нет живых ссылок и он не помечен публичным.
 * Ранее здесь лежал заготовок из Yii (`CConsoleCommand`, `Yii::app()`), который
 * Minion находил по имени файла, но выполнить не мог — падал с fatal error.
 *
 * Запускать по расписанию: `0 4 * * * cd /srv/promerp && bin/console erp212:storage:cleanup`
 *
 * @package Minion
 */
class Task_Erp212_Storage_Cleanup extends Minion_Task {

    protected $_options = [
        'days'       => 7,
        'limit'      => 1000,
        'links-only' => FALSE,
        'files-only' => FALSE,
        'dry-run'    => FALSE,
    ];

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $days     = (int) $this->_value($options, 'days', 7);
        $limit    = (int) $this->_value($options, 'limit', 1000);
        $links    = ! $this->_flag($options, 'files-only');
        $files    = ! $this->_flag($options, 'links-only');
        $dry_run  = $this->_flag($options, 'dry-run');

        $days  = ($days >= 1) ? $days : 7;
        $limit = ($limit >= 1 AND $limit <= 10000) ? $limit : 1000;

        echo sprintf('Очистка erp212: возраст файлов %d дн., лимит %d%s%s',
            $days, $limit, $dry_run ? ', режим проверки (dry-run)' : '', PHP_EOL);

        $manager = Service_Erp212_Storage::instance();

        $deleted_links = 0;
        $deleted_files = 0;

        if ($links)
        {
            $deleted_links = $this->_cleanup_links($manager, $limit, $dry_run);
        }

        if ($files)
        {
            $deleted_files = $this->_cleanup_files($manager, $days, $limit, $dry_run);
        }

        echo sprintf('Готово: ссылок %d, файлов %d%s',
            $deleted_links, $deleted_files, PHP_EOL);

        Kohana::$log->add(Log::INFO, 'erp212: очистка хранилища — ссылок :links, файлов :files', [
            ':links'  => $deleted_links,
            ':files'  => $deleted_files,
        ])->write();
    }

    /**
     * Просроченные временные ссылки.
     *
     * @param   Service_Erp212_Storage  $manager
     * @param   integer                 $limit
     * @param   boolean                 $dry_run
     * @return  integer
     */
    protected function _cleanup_links($manager, $limit, $dry_run)
    {
        $expired = ORM::factory('Erp212_Link')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->order_by('expires_at', 'ASC')
            ->limit($limit)
            ->find_all();

        $count = 0;

        foreach ($expired as $link)
        {
            if ($dry_run)
            {
                echo '  [dry-run] ссылка #'.$link->id.' истекла '.$link->expires_at.PHP_EOL;
                $count++;
                continue;
            }

            try
            {
                $link->delete();
                $count++;
            }
            catch (Exception $e)
            {
                echo '  ошибка удаления ссылки #'.$link->id.': '.$e->getMessage().PHP_EOL;
            }
        }

        return $count;
    }

    /**
     * Файлы старше N дней, на которые нет живых ссылок.
     *
     * @param   Service_Erp212_Storage  $manager
     * @param   integer                 $days
     * @param   integer                 $limit
     * @param   boolean                 $dry_run
     * @return  integer
     */
    protected function _cleanup_files($manager, $days, $limit, $dry_run)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-'.$days.' days'));

        $candidates = ORM::factory('Erp212_File')
            ->where('created_at', '<', $cutoff)
            ->where('is_public', '=', 0)
            ->order_by('created_at', 'ASC')
            ->limit($limit)
            ->find_all();

        $count = 0;

        foreach ($candidates as $file)
        {
            $alive = (int) DB::select([DB::expr('COUNT(*)'), 'total'])
                ->from('erp_212_file_links')
                ->where('file_id', '=', (int) $file->id)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->execute()
                ->get('total');

            if ($alive > 0)
            {
                continue;
            }

            if ($dry_run)
            {
                echo '  [dry-run] файл #'.$file->id.' '.$file->filename.' ('.$file->created_at.')'.PHP_EOL;
                $count++;
                continue;
            }

            try
            {
                DB::delete('erp_212_file_links')->where('file_id', '=', (int) $file->id)->execute();

                // purge() удаляет файл и физически, и из реестра (без проверки прав:
                // в CLI нет пользователя, а задача регламентная)
                $manager->purge($file->id);
                $count++;
            }
            catch (Exception $e)
            {
                echo '  ошибка удаления файла #'.$file->id.': '.$e->getMessage().PHP_EOL;
            }
        }

        return $count;
    }
}
