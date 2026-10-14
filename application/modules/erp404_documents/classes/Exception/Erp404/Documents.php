<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ошибка модуля документов (erp404_documents).
 *
 * `reason()` — короткий машинный код: контроллер показывает по нему понятный
 * текст, JSON API отдаёт `error.reason`, тесты проверяют поведение, не разбирая
 * сообщения:
 *
 *  - `not_found`           — шаблона или документа нет (или он не читается
 *                            этим действием);
 *  - `invalid_transition`  — действие недопустимо в текущем состоянии
 *                            (выпущенный документ правят, архив отменяют);
 *  - `unknown_placeholder` — в шаблоне есть подстановка, которой мы не знаем
 *                            (`{{ref.такого_поля}}`): печатать её как текст
 *                            нельзя — молча испортит документ;
 *  - `unknown_ref`         — тип объекта шаблона не поддержан: некому отдать
 *                            данные подстановки;
 *  - `ref_not_found`       — объекта-источника нет: печатать нечего;
 *  - `dependency`          — недоступен модуль, данные которого нужны шаблону;
 *  - `duplicate_code`      — шаблон с таким кодом уже есть;
 *  - `empty_template`      — пустое тело шаблона: печатать нечего;
 *  - `not_issued`          — печатают черновик: сначала выпуск;
 *  - `in_use`              — шаблон выключен, но уже использован: удалять
 *                            нельзя, только выключать (удаление запрещено всем).
 *
 * @package    Erp404
 * @category   Exceptions
 */
class Exception_Erp404_Documents extends Kohana_Exception
{
    /** @var string */
    protected $_reason = 'documents_error';

    /**
     * @param   string          $message
     * @param   array|null      $variables
     * @param   string          $reason
     * @param   int             $code
     * @param   Exception|null  $previous
     */
    public function __construct($message, ?array $variables = NULL, $reason = 'documents_error', $code = 0, ?Exception $previous = NULL)
    {
        $this->_reason = (string) $reason;

        parent::__construct($message, is_array($variables) ? $variables : array(), $code, $previous);
    }

    /**
     * @return  string
     */
    public function reason()
    {
        return $this->_reason;
    }
}
