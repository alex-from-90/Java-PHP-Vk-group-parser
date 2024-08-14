<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Получение подписчиков VK</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 50px; }
        .container { max-width: 800px; }
        .table th, .table td { vertical-align: middle; }
        .btn-sent { background-color: green; color: white; }
        #timer { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">Получение подписчиков VK</h1>
        <form method="post" class="mb-4" id="inputForm">
            <div class="form-group">
                <label for="public">Введите короткое имя сообщества:</label>
                <input type="text" id="public" name="public" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="date_example">Введите дату последнего визита (месяц/день/год):</label>
                <input type="text" id="date_example" name="date_example" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Получить подписчиков</button>
            <button type="button" class="btn btn-secondary" onclick="goBack()">Назад</button>
            <button type="button" class="btn btn-secondary" onclick="savePage()">Сохранить как веб страницу</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $token = "Впишите токен приложения";
            $version = '5.131';

            $public = $_POST['public'];
            $date_example = $_POST['date_example'];

            $date_format = DateTime::createFromFormat('m/d/Y', $date_example);
            if ($date_format) {
                $unix_time = $date_format->getTimestamp();

                function vkApiRequest($method, $params) {
                    global $token, $version;
                    $url = "https://api.vk.com/method/{$method}?" . http_build_query($params + array('access_token' => $token, 'v' => $version));
                    $response = file_get_contents($url);
                    $decoded_response = json_decode($response, true);

                    if (isset($decoded_response['error'])) {
                        echo "<div class='alert alert-danger'>Ошибка API VK: {$decoded_response['error']['error_msg']}</div>";
                        exit;
                    }

                    return $decoded_response;
                }

                function getting_group_info($public) {
                    $response = vkApiRequest('groups.getById', array('group_id' => $public, 'fields' => 'members_count'));
                    if (isset($response['response'][0])) {
                        return $response['response'][0];
                    } else {
                        echo "<div class='alert alert-danger'>Группа не найдена. Проверьте правильность ввода имени сообщества.</div>";
                        exit;
                    }
                }

                function getting_count($public_id) {
                    $response = vkApiRequest('groups.getMembers', array('group_id' => $public_id, 'offset' => 0));
                    return $response['response']['count'];
                }

                function get_all_followers($public, $unix_time) {
                    $group_info = getting_group_info($public);
                    echo "<div class='card mb-4'>";
                    echo "<div class='card-body'>";
                    echo "<h2 class='card-title'>Информация о группе</h2>";
                    echo "<p><strong>Название:</strong> {$group_info['name']}</p>";
                    echo "<p><strong>Количество подписчиков:</strong> {$group_info['members_count']}</p>";
                    echo "</div>";
                    echo "</div>";

                    $public_id = $group_info['id'];
                    $followers_info = array();
                    $offset = 0;
                    $count_followers = getting_count($public_id);
                    $maximal_offset = min(intdiv($count_followers, 1000) * 1000, 5000);
                    $total_requests = ceil($maximal_offset / 1000);

                    echo "<div class='progress mb-4'>";
                    echo "<div id='progress-bar' class='progress-bar' role='progressbar' style='width: 0%;' aria-valuenow='0' aria-valuemin='0' aria-valuemax='100'></div>";
                    echo "</div>";

                    echo "<div id='timer' class='mb-4'>Оставшееся время: <span id='remaining-time'></span> секунд</div>";

                    echo "<h2>Список подписчиков:</h2>";
                    echo "<table class='table table-striped'>";
                    echo "<thead class='thead-dark'>";
                    echo "<tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Фамилия</th>
                            <th>День рождения</th>
                            <th>Город</th>
                            <th>Страна</th>
                            <th>Мобильный телефон</th>
                            <th>Университет</th>
                            <th>Ссылка</th>
                          </tr>";
                    echo "</thead>";
                    echo "<tbody id='followers-list'>";

                    $start_time = time();

                    while ($offset <= $maximal_offset) {
                        usleep(200000); // Задержка 1 секунда

                        $response = vkApiRequest('groups.getMembers', array(
                            'group_id' => $public_id,
                            'sort' => 'id_desc',
                            'offset' => $offset,
                            'fields' => 'last_seen,city,bdate,country,has_mobile,university'
                        ));
                        $items = $response['response']['items'];

                        foreach ($items as $el) {
                            try {
                                if (isset($el['last_seen']) && $el['last_seen']['time'] >= $unix_time) {
                                    usleep(200000); // Задержка 1 секунда

                                    $user_response = vkApiRequest('users.get', array(
                                        'user_ids' => $el['id'],
                                        'fields' => 'first_name,last_name',
                                        'name_case' => 'nom'
                                    ));

                                    if (isset($user_response['response'][0])) {
                                        $user_info = array(
                                            'id' => $el['id'],
                                            'Имя' => $user_response['response'][0]['first_name'] ?? 'Неизвестно',
                                            'Фамилия' => $user_response['response'][0]['last_name'] ?? 'Неизвестно',
                                            'День рождения' => isset($el['bdate']) ? $el['bdate'] : '',
                                            'Город' => isset($el['city']['title']) ? $el['city']['title'] : '',
                                            'Страна' => isset($el['country']['title']) ? $el['country']['title'] : '',
                                            'Мобильный телефон' => isset($el['has_mobile']) ? $el['has_mobile'] : '',
                                            'Университет' => isset($el['university']['name']) ? $el['university']['name'] : '',
                                            'Ссылка' => 'https://vk.me/id' . $el['id']
                                        );
                                        $followers_info[] = $user_info;

                                        echo "<script>
                                                var progressBar = document.getElementById('progress-bar');
                                                progressBar.style.width = '" . ($offset / $maximal_offset * 100) . "%';
                                                progressBar.setAttribute('aria-valuenow', " . ($offset / $maximal_offset * 100) . ");
                                              </script>";

                                        echo "<tr>
                                                <td>{$user_info['id']}</td>
                                                <td>{$user_info['Имя']}</td>
                                                <td>{$user_info['Фамилия']}</td>
                                                <td>{$user_info['День рождения']}</td>
                                                <td>{$user_info['Город']}</td>
                                                <td>{$user_info['Страна']}</td>
                                                <td>{$user_info['Мобильный телефон']}</td>
                                                <td>{$user_info['Университет']}</td>
                                                <td><a href='{$user_info['Ссылка']}' target='_blank' class='btn btn-primary btn-sm send-message'>Отправить сообщение</a></td>
                                              </tr>";
                                    } else {
                                        echo "<div class='alert alert-danger'>Ошибка при получении данных пользователя ID: {$el['id']}</div>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "<div class='alert alert-danger'>Ошибка при обработке пользователя ID: {$el['id']} - " . $e->getMessage() . "</div>";
                                continue;
                            }
                        }

                        $offset += 1000;

                        echo "<script>
                                var totalRequests = $total_requests;
                                var elapsedTime = " . (time() - $start_time) . ";
                                var remainingTime = Math.ceil(elapsedTime / (offset / 1000) * (totalRequests - offset / 1000));
                                document.getElementById('remaining-time').innerText = remainingTime;
                              </script>";
                    }

                    if ($offset >= $maximal_offset) {
                        echo "<script>
                                var progressBar = document.getElementById('progress-bar');
                                progressBar.style.width = '100%';
                                progressBar.setAttribute('aria-valuenow', 100);
                                alert('Парсинг завершен');
                              </script>";
                    }

                    echo "</tbody>";
                    echo "</table>";

                    return array('group_info' => $group_info, 'followers' => $followers_info);
                }

                $result = get_all_followers($public, $unix_time);
            } else {
                echo "<div class='alert alert-danger'>Неверный формат даты. Пожалуйста, используйте формат месяц/день/год.</div>";
            }
        }
        ?>
    </div>
    <script>
        function goBack() {
            window.location.href = 'vk.php';
        }

        function savePage() {
            var blob = new Blob([document.documentElement.outerHTML], {type: 'text/html'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'page.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('send-message')) {
                event.preventDefault();
                let row = event.target.closest('tr');
                row.querySelector('.send-message').classList.add('btn-sent');
                row.querySelector('.send-message').innerHTML = 'Отправлено';
                row.querySelector('.send-message').classList.remove('btn-primary');
                window.open(event.target.href, '_blank');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            var sendMessageButtons = document.querySelectorAll('.send-message');
            sendMessageButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    let row = this.closest('tr');
                    row.querySelector('.send-message').classList.add('btn-sent');
                    row.querySelector('.send-message').innerHTML = 'Отправлено';
                    row.querySelector('.send-message').classList.remove('btn-primary');
                });
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
