<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Интерфейс для всех провайдеров LLM.
 * Определяет методы для взаимодействия с API различных провайдеров.
 */
interface Interface_LlmProvider {

    /**
     * Отправляет запрос на Chat Completions API.
     * Этот метод универсален и может обрабатывать как текстовые, так и мультимодальные запросы.
     *
     * @param array $messages Массив сообщений в формате чата (e.g., [['role' => 'user', 'content' => 'Hello']]).
     *                        Для мультимодальности content может быть массивом.
     * @param array $options Дополнительные параметры для запроса (например, temperature, max_tokens, model, tools, tool_choice).
     * @return array Ответ API в виде ассоциативного массива.
     * @throws Kohana_Exception В случае ошибки API или некорректного ответа.
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Возвращает имя модели, используемой по умолчанию этим провайдером.
     * @return string
     */
    public function getDefaultModel(): string;

    /**
     * Возвращает имя провайдера.
     * @return string
     */
    public function getName(): string;
	
    /**
     * Возвращает информацию о конкретной модели, включая поддерживаемые/неподдерживаемые параметры.
     *
     * @param string $model_name Имя модели.
     * @return array Ассоциативный массив с информацией о модели.
     */
    public function getModelInfo(string $model_name): array;

    /**
     * Проверяет, поддерживает ли провайдер инструменты (tools).
     * Это может быть общая поддержка провайдера или зависеть от конкретной модели.
     *
     * @param string|null $model_name Имя модели. Если null, проверяется общая поддержка или поддержка дефолтной модели.
     * @return bool
     */
    public function supportsTools(?string $model_name = null): bool;
}