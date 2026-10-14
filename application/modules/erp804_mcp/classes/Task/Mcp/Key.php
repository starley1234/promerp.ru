<?php defined('SYSPATH') or die('No direct script access.');

/**
 * `bin/console mcp:key` — выдача и обслуживание ключей агентов.
 *
 * Примеры:
 *
 *   bin/console mcp:key --name="Агент склада" --scopes=erp402_production.view
 *   bin/console mcp:key --name="Мастер цеха" --scopes=erp402_production.* --actor=1
 *   bin/console mcp:key --name="Читающий агент" --scopes=erp402_production.view --read-only
 *   bin/console mcp:key --list
 *   bin/console mcp:key --revoke=3
 *
 * Токен печатается один раз: в базе хранится только его SHA-256.
 *
 * @package    Erp804
 * @category   Tasks
 */
class Task_Mcp_Key extends Minion_Task
{
    protected $_options = array(
        'name'      => NULL,
        'scopes'    => NULL,
        'client'    => NULL,
        'actor'     => NULL,
        'expires'   => NULL,
        'note'      => NULL,
        'read_only' => FALSE,
        'list'      => FALSE,
        'revoke'    => NULL,
        'enable'    => NULL,
        'delete'    => NULL,
    );

    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;
        $keys    = Service_Erp804_Keys::instance();

        // Minion отдаёт флаг без значения как NULL — читаем через _flag()/_value()
        $name    = $this->_value($options, 'name');
        $scopes  = $this->_value($options, 'scopes');

        if ($this->_flag($options, 'list'))
        {
            return $this->_list($keys);
        }

        if ($revoke = $this->_value($options, 'revoke'))
        {
            $key = $keys->revoke((int) $revoke);

            echo 'Ключ «'.$key['name'].'» отозван', PHP_EOL;

            return;
        }

        if ($enable = $this->_value($options, 'enable'))
        {
            $key = $keys->update((int) $enable, array('enabled' => TRUE));

            echo 'Ключ «'.$key['name'].'» включён', PHP_EOL;

            return;
        }

        if ($delete = $this->_value($options, 'delete'))
        {
            echo $keys->delete((int) $delete) ? 'Ключ удалён' : 'Ключ не найден', PHP_EOL;

            return;
        }

        if ( ! $name OR ! $scopes)
        {
            echo 'Укажите --name и --scopes (например --scopes=erp402_production.view,erp402_production.manage)', PHP_EOL;

            return;
        }

        $issued = $keys->issue(
            (string) $name,
            array_filter(array_map('trim', explode(',', (string) $scopes))),
            array(
                'client'     => $this->_value($options, 'client'),
                'actor_id'   => ($actor = $this->_value($options, 'actor')) ? (int) $actor : NULL,
                'expires_at' => $this->_value($options, 'expires'),
                'note'       => $this->_value($options, 'note'),
                'read_only'  => $this->_flag($options, 'read_only'),
            )
        );

        echo 'Ключ создан: #', $issued['key']['id'], ' «', $issued['key']['name'], '»', PHP_EOL;
        echo 'Права: ', implode(', ', $issued['key']['scopes']), PHP_EOL;
        echo 'Режим: ', $issued['key']['read_only'] ? 'только чтение' : 'чтение и изменения', PHP_EOL;
        echo PHP_EOL, 'Токен (показывается один раз):', PHP_EOL, $issued['token'], PHP_EOL, PHP_EOL;
        echo 'Подключение:', PHP_EOL;
        echo '  curl -s http://127.0.0.1:8080/mcp -H "Authorization: Bearer ', $issued['token'], '" \\', PHP_EOL;
        echo '       -H "Content-Type: application/json" -d \'{"jsonrpc":"2.0","id":1,"method":"tools/list"}\'', PHP_EOL;
    }

    /**
     * @param   Service_Erp804_Keys $keys
     * @return  void
     */
    protected function _list(Service_Erp804_Keys $keys)
    {
        $result = $keys->search(array(), 100, 0);

        echo 'Ключей: ', $result['total'], PHP_EOL, PHP_EOL;

        foreach ($result['rows'] as $key)
        {
            echo '#', str_pad($key['id'], 4), str_pad($key['name'], 30), $key['enabled'] ? 'включён' : 'ОТОЗВАН', PHP_EOL;
            echo '     права: ', implode(', ', $key['scopes']), PHP_EOL;
            echo '     вызовов: ', $key['calls_count'], ', последний: ', ($key['last_used_at'] ? $key['last_used_at'] : '—'), PHP_EOL;
        }
    }
}
