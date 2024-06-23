<?php
error_reporting(E_ERROR | E_PARSE);
session_set_cookie_params(
    array('lifetime' => 30 * 24 * 60 * 60) // 30 дней в секундах
);

session_start();
set_time_limit(0);
date_default_timezone_set('Europe/Minsk');

// Путь к файлу бД
$dbPath = 'db/html.db';
$dir = __DIR__ . '/html';

function _loadHtmlPattern() {
    return file_get_contents("html/header.html");
}

function _getUsrNameFromDB($db, $userId) {
    $stmt = $db->prepare('SELECT username FROM users WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    return $user ? $user['username'] : 'Unknown';
}

function _getAvailableMonths($db, $userId) {
    $stmt = $db->prepare('SELECT DISTINCT strftime("%Y-%m", send_date) as month FROM reports WHERE user_id = :user_id ORDER BY month');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $months = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $months[] = $row['month'];
    }
    return $months;
}

function _generateMonthsHtml($db, $userId) {
    $availableMonths = _getAvailableMonths($db, $userId);
    $allMonths = array(
        '01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель',
        '05' => 'Май', '06' => 'Июнь', '07' => 'Июль', '08' => 'Август',
        '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
    );

    $years = [];
    foreach ($availableMonths as $month) {
        list($year, $monthNum) = explode('-', $month);
        if (!isset($years[$year])) {
            $years[$year] = [];
        }
        $years[$year][] = $monthNum;
    }

    $html = '';
    foreach ($years as $year => $months) {
        $monthLinks = '';
        foreach ($allMonths as $num => $name) {
            if (in_array($num, $months)) {
                $monthLinks .= "<a href='index.php?year=$year&month=$num'>$name</a> ";
            } else {
                $monthLinks .= "<span>$name</span> ";
            }
        }
        $yearHtml = file_get_contents("html/date.html");
        $yearHtml = str_replace("{YEAR}", $year, $yearHtml);
        $yearHtml = str_replace("{MOUNTH}", $monthLinks, $yearHtml);
        $html .= $yearHtml;
    }
    return $html;
}


try {
    $db = new SQLite3($dbPath);

    // Обработка отправки отчетов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        //header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($_SESSION['user_id']) && isset($data['report_text'])) {
            $userId = $_SESSION['user_id'];
            $reportText = $data['report_text'];

            // Вставка отчета в базу данных
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

    // Создание таблицы ROLES, если она не существует
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT, 
        name TEXT UNIQUE
    )");

    // Создание таблицы USERS, если она не существует
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT, 
        username TEXT UNIQUE,
        pass TEXT, 
        role_id INTEGER,
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        report_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        report_text TEXT,
        send_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");

    $names = array('head', 'admin', 'user');

    // Подготовка выражения для проверки существования роли
    $checkRole = $db->prepare('SELECT COUNT(*) AS count FROM roles WHERE name = :name');

    $stmtRole = $db->prepare('INSERT INTO roles (name) VALUES (:name)');

    // Вставка имени если оно не существует
    foreach ($names as $name) {
        $checkRole->bindValue(':name', $name, SQLITE3_TEXT);
        $result = $checkRole->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result['count'] == 0) {
            $stmtRole->bindValue(':name', $name, SQLITE3_TEXT);
            $stmtRole->execute();
        }
    }

    $users = array(
        array('username' => 'head', 'pass' => 'passhead', 'role_id' => 1),
        array('username' => 'admin', 'pass' => 'passadmin', 'role_id' => 2),
        array('username' => 'user', 'pass' => 'passuser', 'role_id' => 3)
    );

    // Подготовка выражения для проверки существования пользователя
    $checkUser = $db->prepare('SELECT COUNT(*) AS count FROM users WHERE username = :username');

    $stmtUser = $db->prepare('INSERT INTO users (username, pass, role_id) VALUES (:username, :pass, :role_id)');

    // Вставка пользователя если он не существует
    foreach ($users as $user) {
        $checkUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
        $result = $checkUser->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result['count'] == 0) {
            $stmtUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
            $stmtUser->bindValue(':pass', $user['pass'], SQLITE3_TEXT);
            $stmtUser->bindValue(':role_id', $user['role_id'], SQLITE3_INTEGER);
            $stmtUser->execute();
        }
    }

    // Проверка куки для автоматической авторизации
    if (isset($_COOKIE['username']) && isset($_COOKIE['password'])) {
    
        if (isset($_POST['logout'])) {
            // Обработка выхода
            session_unset();
            session_destroy();
            setcookie("username", "", time() - 3600, "/");
            setcookie("password", "", time() - 3600, "/");
            $author = file_get_contents("html/authorization.html");
            echo $author;
            exit();
        }
    
        $username = $_COOKIE['username'];
        $password = $_COOKIE['password'];

        $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username AND pass = :pass');
        $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
        $stmtAuth->bindValue(':pass', $password, SQLITE3_TEXT);
        $result = $stmtAuth->execute();

        if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            // Пользователь найден, установка сессии
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];

            // Вывод нужной страницы в зависимости от роли
            switch ($user['role_id']) {
                case 1:
                    $header = _loadHtmlPattern();
                    $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                    $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                    $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                    echo $header;

                    $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                    $body = file_get_contents("html/body-employee.html");
                    $body = str_replace("{DATE}", $monthsHtml, $body);
                    echo $body;

                    exit();
                case 2:
                    $header = _loadHtmlPattern();
                    $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                    $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                    $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                    echo $header;

                    $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                    $body = file_get_contents("html/body-admin.html");
                    $body = str_replace("{DATE}", $monthsHtml, $body);
                    echo $body;

                    exit();
                case 3:
                    $header = _loadHtmlPattern();
                    $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                    $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                    $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                    echo $header;

                    $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                    $body = file_get_contents("html/body-head.html");
                    $body = str_replace("{DATE}", $monthsHtml, $body);
                    echo $body;

                    exit();
                default:
                    echo "Unknown role";
                    exit();
            }
        } else {
            // Очистка куки если пользователь не найден
            setcookie("username", "", time() - 3600, "/");
            setcookie("password", "", time() - 3600, "/");
        
            $author = file_get_contents("html/authorization.html");
            echo $author;
            exit();
        }
    } else {
        // Обработка данных формы авторизации
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
            $username = $_POST['username'];
            $password = $_POST['password'];

            // Подготовка и выполнение запроса для проверки пользователя
            $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username AND pass = :pass');
            $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
            $stmtAuth->bindValue(':pass', $password, SQLITE3_TEXT);
            $result = $stmtAuth->execute();

            if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
                // Пользователь найден, установка сессии и куки
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];

                setcookie("username", $username, time() + (30 * 24 * 60 * 60), "/");
                setcookie("password", $password, time() + (30 * 24 * 60 * 60), "/");

                // Вывод нужной страницы в зависимости от роли
                switch ($user['role_id']) {
                    case 1:
                        $header = _loadHtmlPattern();
                        $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                        $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                        $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                        echo $header;
    
                        $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                        $body = file_get_contents("html/body-employee.html");
                        $body = str_replace("{DATE}", $monthsHtml, $body);
                        echo $body;
    
                        exit();
                    case 2:
                        $header = _loadHtmlPattern();
                        $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                        $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                        $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                        echo $header;
    
                        $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                        $body = file_get_contents("html/body-admin.html");
                        $body = str_replace("{DATE}", $monthsHtml, $body);
                        echo $body;
    
                        exit();
                    case 3:
                        $header = _loadHtmlPattern();
                        $username = _getUsrNameFromDB($db, $_SESSION['user_id']);
                        $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
                        $header = str_replace("{LOGIN-INFO}", $user_info, $header);
                        echo $header;
    
                        $monthsHtml = _generateMonthsHtml($db, $_SESSION['user_id']);
                        $body = file_get_contents("html/body-head.html");
                        $body = str_replace("{DATE}", $monthsHtml, $body);
                        echo $body;
    
                        exit();
                    default:
                        echo "Unknown role";
                        exit();
                }
            } else {
                $error_message = "Неверное имя пользователя или пароль.";
                echo "<script type='text/javascript'>alert('$error_message');</script>";
                $author = file_get_contents("html/authorization.html");
                echo $author;
            }
        } else {
            // Вывод страницы авторизации
            $author = file_get_contents("html/authorization.html");
            echo $author;
        }
    }

} catch (Exception $e) {
    echo "Не удалось открыть базу данных: " . $e->getMessage();
}

