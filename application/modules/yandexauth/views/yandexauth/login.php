<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="min-h-[calc(100vh-64px)] flex items-center justify-center px-4 -mt-32">

      
				
					<div class="w-full max-w-3xl p-8 dark:bg-gray-800 rounded-lg shadow-lg">
					  <div class="flex justify-center mb-8">
						  <div class="w-full max-w-md p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700">

							  <h5 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-6 text-center">Войти через Яндекс</h5>

							  <div id="yandex-button-container" class="flex justify-center mb-4"></div>

							  <div id="status-message" class="message text-green-600 dark:text-green-400 text-center mb-2"></div>
							  <div id="error-message" class="error text-red-600 dark:text-red-400 text-center"></div>

						  </div>
					  </div>
					</div>
				
				
           

</div>




<script src="https://yastatic.net/s3/auth/front/form/build/auth-sdk.js"></script>
<script>
    window.onload = function() {
        const clientId = '<?php echo $client_id; ?>';
        const redirectUri = '<?php echo $redirect_uri; ?>';
        const tokenPageOrigin = '<?php echo $token_page_origin; ?>';

        window.YaAuthSuggest.init(
            {
                client_id: clientId,
                response_type: 'token',
                redirect_uri: redirectUri
            },
            tokenPageOrigin,
            {
                view: 'button',
                parentId: 'yandex-button-container',
                buttonView: 'main',
                buttonTheme: 'light',
                buttonSize: 'l',
                buttonBorderRadius: 5
            }
        )
        .then(function(result) {
            return result.handler();
        })
        .then(function(data) {
            console.log('Сообщение с токеном: ', data);
            document.getElementById('status-message').innerText = 'Авторизация успешна!';
            // window.showNotification('Авторизация успешна! Получен токен.', 'success');

            // Отправляем токен на сервер для получения информации о пользователе
            fetch('<?php echo URL::site('yandexauth/get_user_info'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'oauth_token=' + data.access_token
            })
            .then(response => response.json())
            .then(serverData => {
                if (serverData.status === 'success') {
                    // document.getElementById('status-message').innerText = 'Информация о пользователе получена!';
                    // window.showNotification('Информация о пользователе получена!', 'success');
                    console.log('Информация о пользователе:', serverData.user_info);
                    // Перенаправляем пользователя на страницу профиля или главную
                    window.location.href = '<?php echo URL::site('yandexauth/profile'); ?>';
                } else {
                    document.getElementById('error-message').innerText = 'Ошибка при получении информации о пользователе: ' + serverData.message;
                    window.showNotification('Ошибка при получении информации о пользователе: ' + serverData.message, 'error');
                    console.error('Ошибка сервера:', serverData.message);
                }
            })
            .catch(error => {
                document.getElementById('error-message').innerText = 'Ошибка при отправке токена на сервер: ' + error;
                window.showNotification('Ошибка при отправке токена на сервер: ' + error, 'error');
                console.error('Ошибка fetch:', error);
            });

        })
        .catch(function(error) {
            document.getElementById('error-message').innerText = 'Что-то пошло не так при авторизации: ' + JSON.stringify(error);
            window.showNotification('Что-то пошло не так при авторизации: ' + JSON.stringify(error), 'error');
            console.error('Что-то пошло не так: ', error);
        });
    };
</script>