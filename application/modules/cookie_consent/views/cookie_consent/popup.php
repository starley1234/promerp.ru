<style>
.cookie-consent-popup {
    display: none; /* По умолчанию скрыто, будет показано JS */
    position: fixed; /* Или absolute, если вы хотите, чтобы он прокручивался со страницей */
    bottom: 20px;
    left: 20px;
    width: 90%; /* Или другая ширина */
    background-color: rgb(253 189 189 / 90%); /* Полупрозрачный фон */
    color: #000000;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    z-index: 9999; /* Чтобы было поверх всего */
    font-family: Arial, sans-serif;
    line-height: 1.4;
    /* Добавим padding-right, чтобы текст не перекрывался кнопкой */
    padding-right: 40px; /* Достаточно места для кнопки */
}

.cookie-consent-content {
    margin-right: 10%;
}

.cookie-consent-close {
    position: absolute; /* Позиционируем относительно ближайшего родителя с position: relative, fixed, или absolute */
    top: 4px; /* Отступ сверху от popup */
    right: 10px; /* Отступ справа от popup */
    left: auto; /* Убираем left, если используем right */
    font-size: 18px;
    cursor: pointer;
    /*color: #fff;*/
    text-decoration: none;
    line-height: 1;
    z-index: 10000; /* Убедимся, что кнопка поверх всего */
}

.cookie-consent-close:hover {
    /*color: #ccc;*/
}

.cookie-consent-popup p {
    margin-bottom: 14px;
    font-size: 12px;
}

.cookie-consent-popup p:last-child {
    margin-bottom: 0;
}

.cookie-consent-popup strong {
    font-weight: bold;
}
</style>

<div id="cookie-consent-popup" class="cookie-consent-popup">
    <div class="cookie-consent-content">
	
		<button type="button" class="cookie-consent-close btn btn-info" id="cookie-consent-close">
                <span>Понятно</span>
        </button>
		
        <p>
            <strong>Продолжая использовать наш сайт, Вы даете согласие на обработку файлов - COOKIES, пользовательских данных (файлы-cookies, IP-адрес, данные об идентификаторе браузера, дата и время осуществления доступа к сайту, история поисковых запросов) для сбора аналитической и статистической информации.</strong>
        </p>
        <p>
            Эти файлы позволяют рассчитывать посещения и источники трафика, измерять и улучшать производительность и функционирование веб-сайта, некоторые файлы-cookie устанавливаются только в ответ на совершенные действия пользователя, например, вход в систему или заполнение форм, функционирования сайта, проведения ретаргетинга и проведения статистических исследований и обзоров. Если Вы не хотите, чтобы Ваши данные обрабатывались, пожалуйста, покиньте сайт.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var popup = document.getElementById('cookie-consent-popup');
    var closeButton = document.getElementById('cookie-consent-close');

    // Проверяем, есть ли cookie, который указывает, что окно было закрыто
    // (Это будет сделано на стороне сервера, но для клиентской логики можно добавить проверку)
    // В данном случае, мы полагаемся на то, что сервер не выведет HTML, если cookie установлен.
    if (popup) {
        // Показываем окно через небольшой таймаут для плавности
        setTimeout(function() {
            popup.style.display = 'block';
        }, 500); // Задержка 0.5 секунды

        closeButton.addEventListener('click', function() {
            // Скрываем окно
            popup.style.display = 'none';

            // Отправляем AJAX-запрос на сервер, чтобы установить cookie
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/cookie_consent/close', true); // Используем маршрут
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); // Для определения AJAX на сервере
            xhr.send();

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // console.log('Cookie consent closed successfully.');
                }
            };
        });
    }
});
</script>
