<?php
session_set_cookie_params(30 * 24 * 60 * 60); // 30 дней в секундах
session_start();
set_time_limit(0);
date_default_timezone_set('Europe/Minsk');

// Путь к файлу базы данных
$dbPath = 'db/html_test.db';
$db = new SQLite3('db/html_test.db');
$dir = __DIR__ . '/html_test';

// Функция загрузки шаблона с проверкой наличия файла
function _loadHtmlTemplate($fileName) {
    if (file_exists($fileName)) {
        return file_get_contents($fileName);
    } else {
        return "<!-- Шаблон $fileName не найден -->";
    }
}

function _getAllEmployees($db) {
    // Получаем всех сотрудников
    $stmt = $db->prepare('SELECT user_id, surname FROM users WHERE role_id = 3'); 
    $result = $stmt->execute();

    $employees = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $employees[] = $row;
    }
    return $employees;
}

// Функции для работы с базой данных
function _getUsrNameFromDB($db, $userId) {
    $stmt = $db->prepare('SELECT username FROM users WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    return $user ? $user['username'] : 'Unknown';
}

function _getSurNameFromDB($db, $userId) {
    $stmt = $db->prepare("SELECT surname, name FROM users WHERE user_id = :userId");
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($result) {
        return $result['surname'] . " " . $result['name'];
    }
    return null;
}

function _getAvailableMonths($db, $userId) {
    $stmt = $db->prepare('SELECT DISTINCT strftime("%Y-%m", send_date) as month FROM reports WHERE user_id = :user_id ORDER BY month');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $months = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $months[] = $row['month'];
    }
    return $months;
}

function _getReportsByMonth($db, $userId, $year, $month) {
    $stmt = $db->prepare('SELECT report_text, send_date FROM reports WHERE user_id = :user_id AND strftime("%Y", send_date) = :year AND strftime("%m", send_date) = :month ORDER BY send_date DESC');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':year', $year, SQLITE3_TEXT);
    $stmt->bindValue(':month', $month, SQLITE3_TEXT);
    $result = $stmt->execute();

    $reports = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $reports[] = $row;
    }
    return $reports;
}

function _generateMonthsHtml($db, $userId) {
    $availableMonths = _getAvailableMonths($db, $userId);
    $currentYear = date('Y'); // Получаем текущий год
    $allMonths = array(
        '01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель',
        '05' => 'Май', '06' => 'Июнь', '07' => 'Июль', '08' => 'Август',
        '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
    );

    $years = array();
    foreach ($availableMonths as $month) {
        list($year, $monthNum) = explode('-', $month);
        if (!isset($years[$year])) {
            $years[$year] = array();
        }
        $years[$year][] = $monthNum;
    }

    // Если доступных месяцев нет, добавляем текущий год в список
    if (empty($years)) {
        $years[$currentYear] = array();
    }

    $html = '';

    // Добавляем выпадающий список для выбора года
    $html .= '<select id="yearSelector" onchange="updateMonths()">';
    foreach ($years as $year => $months) {
        $html .= "<option value='$year'>$year</option>";
    }
    $html .= '</select>';

    $html .= '<div id="monthPanel">';
    
    // Генерация блоков с месяцами
    foreach ($years as $year => $months) {
        $monthLinks = '';
        foreach ($allMonths as $num => $name) {
            if (in_array($num, $months)) {
                $monthLinks .= "<a href='#' class='month-link' data-year='$year' data-month='$num'>$name</a> ";
            } else {
                $monthLinks .= "<span>$name</span> ";
            }
        }
        $yearHtml = _loadHtmlTemplate("html/date.html");
        $yearHtml = str_replace("{MOUNTH}", $monthLinks, $yearHtml);

        $html .= "<div id='month-$year' class='month-links' style='display:none;'>$yearHtml</div>";
    }

    $html .= '</div>';

    $html .= "<script>
    function updateMonths() {
        var year = document.getElementById('yearSelector').value;
        localStorage.setItem('selectedYear', year); // Сохраняем год

        document.querySelectorAll('.month-links').forEach(panel => panel.style.display = 'none');
        var selectedYearPanel = document.getElementById('month-' + year);
        if (selectedYearPanel) {
            selectedYearPanel.style.display = 'block';
        }
    }

    // Восстанавливаем сохраненные данные при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        var savedYear = localStorage.getItem('selectedYear') || '$currentYear';
        document.getElementById('yearSelector').value = savedYear;
        updateMonths();

        // Восстанавливаем выбранный месяц
        var savedMonth = localStorage.getItem('selectedMonth');
        if (savedMonth) {
            document.querySelectorAll('.month-link').forEach(link => {
                if (link.dataset.year === savedYear && link.dataset.month === savedMonth) {
                    link.classList.add('selected'); // Подсветка активного месяца
                }
            });
        }
            
    });


document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.month-link').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault(); // Останавливаем переход по ссылке

            var year = this.dataset.year;
            var month = this.dataset.month;
            var userId = document.getElementById('combobox-staff')?.value || '<?= $userId ?>';

            localStorage.setItem('selectedMonth', month); // Сохраняем выбранный месяц

            fetch(`index.php?year=${year}&month=${month}&user_id=${userId}`)
                .then(response => response.text())
                .then(data => {
                    // Создаем временный элемент для парсинга HTML
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(data, 'text/html');
                    
                    // Ищем textarea с отчетом в загруженном HTML
                    var reportText = doc.querySelector('#reportText');
                    if (reportText) {
                        document.getElementById('reportText').value = reportText.value; // Обновляем поле вывода
                    }
                })
                .catch(error => console.error('Ошибка загрузки отчета:', error));
        });
    });
});




    document.addEventListener('click', function(event) {
    if (event.target.classList.contains('month-link')) {
        localStorage.setItem('selectedMonth', event.target.dataset.month);
    }
});
    </script>";

    return $html;
}




function displayUserPage($db, $role, $isAdmin, $userId) {
    $header = _loadHtmlTemplate("html/header.html");
    $surname = _getSurNameFromDB($db, $userId);
    $reportText = '';
    $isDirector = ($role == 1);
    $isHeadUnit = ($role == 4);

    // Генерация списка сотрудников
    if ($isDirector || $isHeadUnit) {
        $employees = _getAllEmployees($db);
        
        // Проверяем, что функция вернула данные
        if (empty($employees)) {
            error_log("Нет сотрудников в базе данных или ошибка запроса!");
        }
    
        $employeeOptions = "";
        foreach ($employees as $employee) {
            $employeeOptions .= "<option value='{$employee['user_id']}'>{$employee['surname']}</option>";
        }
        
        $employeeSelect = "
            <div class='combox'>
                <label>Работник: </label>
                <select name='employee_id' id='combobox-staff'>
                    <option value=''>Выберите сотрудника</option>
                    $employeeOptions
                </select>
            </div>
        ";
    } else {
        $employeeSelect = "";
    }
    
    // Скрипт для AJAX-обновления месяцев
    $employeeSelect .= "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var employeeSelect = document.getElementById('combobox-staff');
        employeeSelect.addEventListener('change', function() {
            var selectedEmployee = this.value;
            if (selectedEmployee) {
                // Делаем запрос на сервер с параметром user_id и action
                fetch('index.php?user_id=' + selectedEmployee + '&action=getEmployeeMonths')
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('monthPanel').innerHTML = data;
                        updateMonths(); // Вызов обновления месяцев после загрузки
                    })
                    .catch(error => console.error('Ошибка загрузки данных:', error));
            }
        });
    });
    </script>";


    
    
    

    // Загружаем отчет, если выбран сотрудник и месяц
    if (isset($_GET['year']) && isset($_GET['month'])) {
        $year = $_GET['year'];
        $month = $_GET['month'];
        $selectedUserId = isset($_GET['user_id']) ? $_GET['user_id'] : $userId;

        $reports = _getReportsByMonth($db, $selectedUserId, $year, $month);
        if (!empty($reports)) {
            $reportText = htmlspecialchars($reports[0]['report_text']);
        }
    }


    // Определяем, какой файл загружать
    if ($role == 2) {
        $user_info = "<div>Добро пожаловать, admin!</div>";
        $bodyFile = "html/admin.html";
    } elseif ($isDirector) {
        $user_info = "<div>Добро пожаловать, " . htmlspecialchars($surname) . "!</div>";
        $bodyFile = "html/body-head.html";
    } elseif ($isHeadUnit) {
        $user_info = "<div>Добро пожаловать, " . htmlspecialchars($surname) . "!</div>";
        $bodyFile = "html/body-headUnit.html";
    } else {
        $user_info = "<div>Добро пожаловать, " . htmlspecialchars($surname) . "!</div>";
        $bodyFile = "html/body-employee.html";
    }

    $header = str_replace("{LOGIN-INFO}", $user_info, $header);
    $monthsHtml = _generateMonthsHtml($db, $userId);
    $body = _loadHtmlTemplate($bodyFile);
    $body = str_replace("{DATE}", $monthsHtml, $body);
    $body = str_replace("{EMPLOYEE_SELECT}", $employeeSelect, $body);
    $body = str_replace("{REPORT_TEXT}", $reportText, $body);

    if (isset($_GET['user_id'])) {
        $userId = (int)$_GET['user_id'];
        
        // Получаем HTML для месяцев и выводим
        $body = str_replace("{DATE}", $monthsHtml, $body);
        $body = str_replace("{EMPLOYEE_SELECT}", $employeeSelect, $body);
    $body = str_replace("{REPORT_TEXT}", $reportText, $body);
        exit(); // Завершаем выполнение, чтобы не загружать страницу дважды
    }

    echo $header . $body;
}

if (isset($_GET['user_id']) && !isset($_GET['year']) && !isset($_GET['month'])) {
    $userId = (int)$_GET['user_id'];
    $monthsHtml = _generateMonthsHtml($db, $userId);
    echo $monthsHtml; // Отправляем только HTML списка месяцев
    exit(); // Завершаем выполнение
}




try {
    $db = new SQLite3($dbPath);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($_SESSION['user_id']) && isset($data['report_text'])) {
            $userId = $_SESSION['user_id'];
            $reportText = $data['report_text'];

            $stmt = $db->prepare('INSERT INTO reports (user_id, report_text, send_date) VALUES (:user_id, :report_text, :send_date)');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':report_text', $reportText, SQLITE3_TEXT);
            $stmt->bindValue(':send_date', date('Y-m-d H:i:s'), SQLITE3_TEXT);

            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to execute statement'));
            }
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'get_users') {
            $stmt = $db->prepare('SELECT user_id, username, pass, surname, name, lastname, unit, role_id, is_admin FROM users');
            $result = $stmt->execute();
            $users = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $users[] = $row;
            }
            header('Content-Type: application/json');
            echo json_encode($users);
            exit();
        }

        if ($action === 'update_user') {
            $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $pass = isset($_POST['pass']) ? $_POST['pass'] : '';
            $surname = isset($_POST['surname']) ? $_POST['surname'] : '';
            $name = isset($_POST['name']) ? $_POST['name'] : '';
            $lastname = isset($_POST['lastname']) ? $_POST['lastname'] : '';
            
            // Преобразование поля unit в JSON-строку массива
            $unit = isset($_POST['unit']) ? implode(', ', array_map('trim', explode(',', $_POST['unit']))) : '';
            $roleId = isset($_POST['role_id']) ? $_POST['role_id'] : '';
        
            $stmt = $db->prepare('UPDATE users SET username = :username, pass = :pass, surname = :surname, name = :name, lastname = :lastname, unit = :unit, role_id = :role_id WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':pass', password_hash($pass, PASSWORD_DEFAULT), SQLITE3_TEXT);
            $stmt->bindValue(':surname', $surname, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':lastname', $lastname, SQLITE3_TEXT);
            $stmt->bindValue(':unit', $unit, SQLITE3_TEXT); // Сохраняем список подразделений как JSON-строку
            $stmt->bindValue(':role_id', $roleId, SQLITE3_INTEGER);
        
            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to execute statement'));
            }
            exit();
        }
        

        if ($action === 'add_user') {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';
            $surname = isset($_POST['surname']) ? trim($_POST['surname']) : '';
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
            $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        
            // Преобразование поля unit, только если роль - начальник (role_id = 1)
            $unit = null;
            if ($roleId === 1) {
                $unit = isset($_POST['unit']) ? implode(', ', array_map('trim', explode(',', $_POST['unit']))) : '';
            }
        
            // Проверка на обязательные поля
            if (empty($username) || empty($pass) || empty($surname) || empty($name) || $roleId === 0) {
                echo json_encode(array('status' => 'error', 'message' => 'Missing required fields'));
                exit();
            }
        
            try {
                // Подготовка SQL-запроса
                $stmt = $db->prepare('INSERT INTO users (username, pass, surname, name, lastname, unit, role_id) VALUES (:username, :pass, :surname, :name, :lastname, :unit, :role_id)');
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':pass', password_hash($pass, PASSWORD_DEFAULT), SQLITE3_TEXT);
                $stmt->bindValue(':surname', $surname, SQLITE3_TEXT);
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':lastname', $lastname, SQLITE3_TEXT);
                $stmt->bindValue(':unit', $unit, SQLITE3_TEXT); // Сохраняем подразделения, только если роль - начальник
                $stmt->bindValue(':role_id', $roleId, SQLITE3_INTEGER);
        
                // Выполнение SQL-запроса
                if ($stmt->execute()) {
                    echo json_encode(array('status' => 'success'));
                } else {
                    throw new Exception($db->lastErrorMsg());
                }
            } catch (Exception $e) {
                error_log('Ошибка добавления пользователя: ' . $e->getMessage());
                echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
            }
            exit();
        }
        
        
        

        if ($action === 'delete_user') {
            $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';

            $stmt = $db->prepare('DELETE FROM users WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to execute statement'));
            }
            exit();
        }

        if ($action === 'reset_password') {
            $userId = isset($_POST['user_id']) ? $_POST['user_id'] : '';
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

            // Безопасное хэширование пароля
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $db->prepare('UPDATE users SET pass = :pass WHERE user_id = :user_id');
            $stmt->bindValue(':pass', $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to reset password'));
            }
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
            if (isset($_SESSION['user_id']) && !empty($_POST['report_text'])) {
                $userId = $_SESSION['user_id'];
                $reportText = $_POST['report_text'];
                $currentMonth = date('Y-m'); // Текущий год и месяц
        
                // Проверяем, есть ли уже отчет за этот месяц
                $stmt = $db->prepare('SELECT report_text FROM reports WHERE user_id = :user_id AND strftime("%Y-%m", send_date) = :current_month');
                $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':current_month', $currentMonth, SQLITE3_TEXT);
                $result = $stmt->execute();
                $existingReport = $result->fetchArray(SQLITE3_ASSOC);
        
                if ($existingReport) {
                    // Если отчет есть, обновляем его, добавляя новый текст
                    $updatedText = $existingReport['report_text'] . "\n" . $reportText;
                    $stmt = $db->prepare('UPDATE reports SET report_text = :report_text WHERE user_id = :user_id AND strftime("%Y-%m", send_date) = :current_month');
                    $stmt->bindValue(':report_text', $updatedText, SQLITE3_TEXT);
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':current_month', $currentMonth, SQLITE3_TEXT);
                } else {
                    // Если отчета нет, создаем новый
                    $stmt = $db->prepare('INSERT INTO reports (user_id, report_text, send_date) VALUES (:user_id, :report_text, :send_date)');
                    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':report_text', $reportText, SQLITE3_TEXT);
                    $stmt->bindValue(':send_date', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                }
        
                if ($stmt->execute()) {
                    echo "<script>alert('Отчет успешно обновлен!');</script>";
                } else {
                    echo "<script>alert('Ошибка при добавлении отчета.');</script>";
                }
            } else {
                echo "<script>alert('Текст отчета не может быть пустым.');</script>";
            }
        
            // Перезагрузка страницы для обновления списка отчетов
            header("Location: index.php");
            exit();
        }
        
    }

    // Создание таблиц
    $db->exec("CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)");
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT, 
        username TEXT UNIQUE, 
        pass TEXT, 
        surname TEXT, 
        name TEXT, 
        lastname TEXT, 
        unit INTEGER, 
        role_id INTEGER,
        is_admin INTEGER DEFAULT 0,
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INTEGER PRIMARY KEY AUTOINCREMENT, 
        user_id INTEGER, 
        report_text TEXT, 
        send_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");

    // Добавление пользователей с булевым признаком is_admin
    $names = array('Начальник','Работник','НачальникСектора');
    $checkRole = $db->prepare('SELECT COUNT(*) AS count FROM roles WHERE name = :name');
    $stmtRole = $db->prepare('INSERT INTO roles (name) VALUES (:name)');

foreach ($names as $name) {
    $checkRole->bindValue(':name', $name, SQLITE3_TEXT);
    $result = $checkRole->execute()->fetchArray(SQLITE3_ASSOC);

    if ($result['count'] == 0) {
        $stmtRole->bindValue(':name', $name, SQLITE3_TEXT);
        $stmtRole->execute();
    }
}
    $users = array(
        array('username' => 'head', 'pass' => 'passhead', 'surname' => 'Иванов', 'name' => 'Иван', 'lastname' => 'Иванович', 'unit' => 143, 'role_id' => 1,'is_admin' => 0),
        array('username' => 'admin', 'pass' => 'passadmin', 'surname' => 'Петров', 'name' => 'Петр', 'lastname' => 'Петрович', 'unit' => 142,'role_id' => 2 ,'is_admin' => 1),
        array('username' => 'user', 'pass' => 'passuser', 'surname' => 'Сергеев', 'name' => 'Сергей', 'lastname' => 'Сергеевич', 'unit' => 141, 'role_id' => 3,'is_admin' => 0),
        array('username' => 'headUnit', 'pass' => 'passheadunit', 'surname' => 'Сергеев', 'name' => 'Сергей', 'lastname' => 'Сергеевич', 'unit' => 141, 'role_id' => 4,'is_admin' => 0)
    );

    $checkUser = $db->prepare('SELECT COUNT(*) AS count FROM users WHERE username = :username');
    $stmtUser = $db->prepare('INSERT INTO users (username, pass, surname, name, lastname, unit, role_id, is_admin) VALUES (:username, :pass, :surname, :name, :lastname, :unit, :role_id, :is_admin)');

    foreach ($users as $user) {
        $checkUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
        $result = $checkUser->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result['count'] == 0) {
            // Хэширование пароля
            $hashedPassword = password_hash($user['pass'], PASSWORD_DEFAULT);

            // Вставка пользователя с хэшированным паролем и булевым признаком is_admin
            $stmtUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
            $stmtUser->bindValue(':pass', $hashedPassword, SQLITE3_TEXT);
            $stmtUser->bindValue(':surname', $user['surname'], SQLITE3_TEXT);
            $stmtUser->bindValue(':name', $user['name'], SQLITE3_TEXT);
            $stmtUser->bindValue(':lastname', $user['lastname'], SQLITE3_TEXT);
            $stmtUser->bindValue(':unit', $user['unit'], SQLITE3_INTEGER);
            $stmtUser->bindValue(':role_id', $user['role_id'], SQLITE3_INTEGER);
            $stmtUser->execute();
        }
    }

    // Проверка куки для автоматической авторизации
    if (isset($_COOKIE['username']) && isset($_COOKIE['password'])) { 
        if (isset($_POST['logout'])) {
            session_unset();
            session_destroy();
            setcookie("username", "", time() - 3600, "/");
            setcookie("password", "", time() - 3600, "/");
            echo _loadHtmlTemplate("html/authorization.html");
            exit();
        }

        $username = $_COOKIE['username'] ?? null; 
        $password = $_COOKIE['password'] ?? null;

        if ($username && $password) {
            $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username');
            $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmtAuth->execute();

            if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
                if (password_verify($password, $user['pass'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    displayUserPage($db,$user['role_id'], $user['is_admin'], $_SESSION['user_id']);
                    exit();
                } else {
                    // Очищаем куки и показываем окно авторизации
                    setcookie("username", "", time() - 3600, "/");
                    setcookie("password", "", time() - 3600, "/");
                    echo _loadHtmlTemplate("html/authorization.html");
                    exit();
                }
            } else {
                setcookie("username", "", time() - 3600, "/");
                setcookie("password", "", time() - 3600, "/");
                echo _loadHtmlTemplate("html/authorization.html");
                exit();
            }
        }
    } else {
        // Если пользователь еще не авторизован, обработка авторизации
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';

            $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username');
            $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmtAuth->execute();

            if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
                if (password_verify($password, $user['pass'])) {
                    // Устанавливаем сессию и куки при успешной авторизации
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    setcookie("username", $username, time() + (30 * 24 * 60 * 60), "/");
                    setcookie("password", $password, time() + (30 * 24 * 60 * 60), "/");

                    displayUserPage($db, $user['role_id'], $user['is_admin'], $_SESSION['user_id']);
                    exit();
                } else {
                    echo "<script>alert('Неверное имя пользователя или пароль.');</script>";
                    echo _loadHtmlTemplate("html/authorization.html");
                }
            } else {
                echo "<script>alert('Неверное имя пользователя или пароль.');</script>";
                echo _loadHtmlTemplate("html/authorization.html");
            }
            
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            // Сначала обрабатываем JSON-запрос (иначе вернется HTML!)
            if (isset($_GET['action']) && $_GET['action'] === 'getEmployees') {
                header('Content-Type: application/json; charset=utf-8'); // Отправляем JSON
                header('Access-Control-Allow-Origin: *'); // Разрешаем кросс-доменные запросы (если нужно)
        
                $employees = _getAllEmployees($db); // Функция получения сотрудников из БД
        
                echo json_encode($employees, JSON_UNESCAPED_UNICODE); // Кодируем в JSON без экранирования
                exit(); // Завершаем выполнение, чтобы не отправлять HTML
            }
        
            // Дальше обрабатываем стандартные GET-запросы
        
            if (isset($_GET['year']) && isset($_GET['month']) && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
                $role = $_SESSION['role_id'];
                $isAdmin = $_SESSION['is_admin'];
        
                // Отображаем страницу с отчетом
                displayUserPage($db, $role, $isAdmin, $userId);
                exit();
            }                
        
            // Если нет параметров year и month, показываем главную страницу пользователя
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
                $role = $_SESSION['role_id'];
                $isAdmin = $_SESSION['is_admin'];
                displayUserPage($db, $role, $isAdmin, $userId);
            } else {
                echo _loadHtmlTemplate("html/authorization.html");
            }
        }
        
    }
}
catch( Exeption $e){
    echo "Не удалось открыть базу данных: ". $e->getMesssge();
}
