<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Переключатель режима интерфейса: `/ui/mode/simple`, `/ui/mode/pro`.
 *
 * Работает и для гостя, и в демо-режиме, поэтому наследует `Controller_Erp100_Base`
 * (без обязательного входа). Действие меняет только личное оформление, не данные,
 * потому это обычная ссылка (GET): так её пропускает режим «только чтение» демо,
 * а кнопка работает без JavaScript и сессии для CSRF-токена. В ссылках стоит
 * `rel="nofollow"`, чтобы поисковые и предзагружающие роботы её не нажимали.
 *
 * @package    Erp100
 * @category   Controllers
 */
class Controller_Erp100_Ui extends Controller_Erp100_Base
{
    /** @var boolean Страницы нет, только перенаправление. */
    public $auto_render = FALSE;

    /**
     * Выбрать режим и вернуться на страницу, откуда пришли.
     *
     * @return  void
     * @throws  HTTP_Exception_404
     */
    public function action_mode()
    {
        $mode = (string) $this->request->param('mode');

        if ( ! Ui_Mode::valid($mode))
        {
            throw new HTTP_Exception_404('Неизвестный режим интерфейса');
        }

        Ui_Mode::set($mode);

        $this->redirect($this->back());
    }

    /**
     * Куда вернуться: только адрес этого же сайта (без открытого перенаправления).
     *
     * @return  string
     */
    protected function back()
    {
        $back = (string) $this->request->query('back');

        if ($back === '' OR $back[0] !== '/' OR strpos($back, '//') === 0 OR strpos($back, '\\') !== FALSE)
        {
            return '/';
        }

        return $back;
    }
}
