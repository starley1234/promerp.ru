<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Страница документации модуля MDM.
 * Сгенерировано на основе технической спецификации системы.
 */
?>

<div>
    <div class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-4">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Единый реестр эталонных данных (MDM)</h1>
        <p class="text-lg text-gray-600 dark:text-gray-400">Интеллектуальная платформа консолидации, очистки и верификации мастер-данных холдинга.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Навигация -->
        <div class="lg:col-span-1">
            <nav class="sticky top-6 space-y-1">
                <a href="#overview" class="block px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-md">1. Обзор системы</a>
                <a href="#concepts" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">2. Ключевые концепции</a>
                <a href="#features" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">3. Функциональные возможности</a>
                <a href="#lifecycle" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">4. Жизненный цикл объекта</a>
                <a href="#tech" class="block px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800 rounded-md">5. Технологический стек</a>
            </nav>
        </div>

        <!-- Контент -->
        <div class="lg:col-span-3 space-y-12">
            
            <!-- Секция 1: Обзор -->
            <section id="overview">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">1</span>
                    Обзор системы
                </h2>
                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                    <p>Модуль <strong>Master Data Management</strong> предназначен для управления нормативно-справочной информацией (НСИ) по изделиям и компонентам. Система обеспечивает создание <strong>«Золотой записи» (Golden Record)</strong> — наиболее достоверного набора атрибутов объекта, собранного из различных источников (ERP, PLM, AI).</p>
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-4 my-4">
                        <p class="text-sm text-blue-700 dark:text-blue-300"><strong>Назначение:</strong> Устранение дублирования данных, разрешение конфликтов между системами-источниками и обеспечение сквозной прослеживаемости изменений.</p>
                    </div>
                </div>
            </section>

            <!-- Секция 2: Концепции -->
            <section id="concepts">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">2</span>
                    Ключевые концепции
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="text-amber-500 mb-2"><i class="fas fa-crown"></i></div>
                        <h4 class="font-bold mb-1">Golden Record</h4>
                        <p class="text-xs text-gray-500">Автоматическое формирование эталона на основе Trust Scores. Система выбирает атрибуты из наиболее надежных источников.</p>
                    </div>
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="text-blue-500 mb-2"><i class="fas fa-history"></i></div>
                        <h4 class="font-bold mb-1">Time-Travel</h4>
                        <p class="text-xs text-gray-500">Полная версионность через <code>tstzrange</code>. Возможность мгновенно восстановить состояние объекта на любую точку в прошлом.</p>
                    </div>
                    <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="text-indigo-500 mb-2"><i class="fas fa-shield-check"></i></div>
                        <h4 class="font-bold mb-1">Data Integrity</h4>
                        <p class="text-xs text-gray-500">Контроль целостности через Baselines и цепочки хешей. Защита данных на уровне строк (RLS).</p>
                    </div>
                </div>
            </section>

            <!-- Секция 3: Возможности -->
            <section id="features">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">3</span>
                    Функциональные возможности
                </h2>
                <div class="space-y-4">
                    <div class="flex items-start p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <i class="fas fa-sync text-blue-500 mt-1 mr-4"></i>
                        <div>
                            <h4 class="font-semibold text-sm">Консолидация и Xref</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Автоматический маппинг данных из внешних систем через кросс-референсы и разрешение конфликтов.</p>
                        </div>
                    </div>
                    <div class="flex items-start p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <i class="fas fa-sitemap text-purple-500 mt-1 mr-4"></i>
                        <div>
                            <h4 class="font-semibold text-sm">Управление структурами (BOM)</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Ведение графовых связей изделий (EBOM/MBOM) с использованием рекурсивных CTE запросов.</p>
                        </div>
                    </div>
                    <div class="flex items-start p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <i class="fas fa-magnifying-glass-chart text-emerald-500 mt-1 mr-4"></i>
                        <div>
                            <h4 class="font-semibold text-sm">Семантический поиск</h4>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Поиск аналогов с использованием векторных эмбеддингов (pg_vector) и нейросетевой индексации.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Секция 4: Жизненный цикл -->
            <section id="lifecycle">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">4</span>
                    Жизненный цикл объекта
                </h2>
                <div class="relative pl-8 border-l-2 border-gray-200 dark:border-gray-700 space-y-6">
                    <div class="relative">
                        <div class="absolute -left-[41px] top-0 w-4 h-4 rounded-full bg-gray-300 dark:bg-gray-600 border-4 border-white dark:border-gray-900"></div>
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white">Draft (Черновик)</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Первичное наполнение данных, верификация атрибутов и обогащение из внешних источников.</p>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[41px] top-0 w-4 h-4 rounded-full bg-green-500 border-4 border-white dark:border-gray-900"></div>
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white">Active (Активен)</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Эталонная запись, доступная для использования во всех смежных системах холдинга.</p>
                    </div>
                    <div class="relative">
                        <div class="absolute -left-[41px] top-0 w-4 h-4 rounded-full bg-blue-500 border-4 border-white dark:border-gray-900"></div>
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white">Merged (Слит)</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Терминальное состояние при объединении дубликатов с сохранением истории связей и аудита.</p>
                    </div>
                </div>
            </section>

            <!-- Секция 5: Технологии -->
            <section id="tech">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">5</span>
                    Технологический стек
                </h2>
                <div class="bg-gray-900 rounded-lg p-6 text-gray-300 font-mono text-xs">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-blue-400 mb-2">// Database Layer</p>
                            <ul class="space-y-1">
                                <li>PostgreSQL 14+</li>
                                <li>Extension: ltree (Hierarchies)</li>
                                <li>Extension: vector (AI Search)</li>
                                <li>Partitioning: by year</li>
                            </ul>
                        </div>
                        <div>
                            <p class="text-blue-400 mb-2">// Application Layer</p>
                            <ul class="space-y-1">
                                <li>EAV + Time-Travel Engine</li>
                                <li>RESTful API / JSON-RPC</li>
                                <li>Row Level Security (RLS)</li>
                                <li>Blockchain-like Baselines</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-between p-4 border border-dashed border-gray-300 dark:border-gray-700 rounded-2xl">
                    <div>
                        <p class="text-[10px] text-gray-500 uppercase font-bold">Версия модуля</p>
                        <p class="text-xs font-mono text-gray-600 dark:text-gray-400">mdm v5.2.0-stable</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-gray-500 uppercase font-bold">Статус безопасности</p>
                        <p class="text-xs text-green-600 dark:text-green-400 italic">RLS Active: Context-aware</p>
                    </div>
                </div>
            </section>

        </div>
    </div>
</div>