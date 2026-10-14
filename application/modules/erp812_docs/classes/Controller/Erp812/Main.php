<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер интерактивной документации (erp812_docs).
 *
 * @package    Erp812
 * @category   Controllers
 */
class Controller_Erp812_Main extends Controller_Base
{
    /**
     * @var bool Документация доступна для свободного чтения всем пользователям и гостям
     */
    protected $auth_required = FALSE;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Интерактивная документация';
        $this->_meta['description'] = 'Интерактивная архитектурная и пользовательская документация PromERP, карты сквозных процессов и каталог модулей.';
        $this->_meta['keywords']    = 'документация, процессы, erp, архитектура, модули, mermaid, диаграммы, mcp';

        $this->set_menu(array(
            array(
                'title' => 'Обзор',
                'href'  => '/docs',
                'icon'  => 'fas fa-book-open',
                'acl'   => 'erp812.view',
            ),
            array(
                'title' => 'Процессы & Схемы',
                'href'  => '/docs/processes',
                'icon'  => 'fas fa-diagram-project',
                'acl'   => 'erp812.view',
            ),
            array(
                'title' => 'Каталог модулей',
                'href'  => '/docs/modules',
                'icon'  => 'fas fa-cubes',
                'acl'   => 'erp812.view',
            ),
            array(
                'title' => 'Архитектура',
                'href'  => '/docs/architecture',
                'icon'  => 'fas fa-sitemap',
                'acl'   => 'erp812.view',
            ),
            array(
                'title' => 'Инструменты MCP (AI)',
                'href'  => '/docs/mcp',
                'icon'  => 'fas fa-robot',
                'acl'   => 'erp812.view',
            ),
        ));

        // Добавляем Mermaid.js (локальный ассет без CDN)
        $this->_scripts[] = array(
            'src'   => '/assets/vendor/mermaid/mermaid.min.js',
            'defer' => FALSE,
        );

        // Скрипт инициализации Mermaid с поддержкой темной/светлой темы и защитой от FOUC
        $this->_scripts[] = array(
            'code' => "
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof mermaid !== 'undefined') {
                    // Сохраняем исходный текст диаграмм до парсинга
                    document.querySelectorAll('.mermaid').forEach(function(el) {
                        if (!el.dataset.originalCode) {
                            el.dataset.originalCode = el.textContent.trim();
                        }
                    });

                    function initMermaid() {
                        var isDark = document.documentElement.classList.contains('dark');
                        try {
                            mermaid.initialize({
                                startOnLoad: false,
                                theme: isDark ? 'dark' : 'neutral',
                                securityLevel: 'loose',
                                fontFamily: 'ui-sans-serif, system-ui, sans-serif',
                                flowchart: { useMaxWidth: true, htmlLabels: true, curve: 'basis' },
                                themeVariables: isDark ? {
                                    darkMode: true,
                                    background: '#1e293b',
                                    primaryColor: '#3b82f6',
                                    primaryBorderColor: '#60a5fa',
                                    primaryTextColor: '#f8fafc',
                                    lineBackground: '#64748b',
                                    textColor: '#f1f5f9',
                                    nodeBorder: '#475569'
                                } : {
                                    primaryColor: '#e0f2fe',
                                    primaryBorderColor: '#38bdf8',
                                    primaryTextColor: '#0f172a',
                                    lineBackground: '#94a3b8',
                                    textColor: '#1e293b'
                                }
                            });

                            document.querySelectorAll('.mermaid').forEach(function(el) {
                                if (el.getAttribute('data-processed')) {
                                    el.removeAttribute('data-processed');
                                    if (el.dataset.originalCode) {
                                        el.textContent = el.dataset.originalCode;
                                    }
                                }
                            });

                            mermaid.run();
                        } catch (e) {
                            console.warn('Mermaid rendering warning:', e);
                        }
                    }

                    initMermaid();
                    
                    // Реинициализация при смене темы
                    document.addEventListener('click', function(e) {
                        if (e.target.closest('.theme-toggle-btn, #theme-toggle-btn')) {
                            setTimeout(initMermaid, 50);
                        }
                    });
                }
            });
            ",
        );
    }
}
