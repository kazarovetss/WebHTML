<?php
error_reporting(E_ERROR | E_PARSE);
session_set_cookie_params(array('lifetime' => 30 * 24 * 60 * 60)); // 30 дней в секундах

session_start();
set_time_limit(0);
date_default_timezone_set('Europe/Minsk');

// Путь к файлу бД
$dbPath = 'db/html_test.db';
$dir = __DIR__ . '/html_test';

function _loadHtmlTemplate($fileName) {
    return file_get_contents($fileName);
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
    $months = array();
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

    $years = array();
    foreach ($availableMonths as $month) {
        list($year, $monthNum) = explode('-', $month);
        if (!isset($years[$year])) {
            $years[$year] = array();
        }
        $years[$year][] = $monthNum;
    }

    $html = '';
    if (empty($availableMonths)) {
        $monthLinks = '';
        foreach ($allMonths as $num => $name) {
            $monthLinks .= "<span>$name</span> ";
            $year = "<span>NONE</span> ";
        }
        $yearHtml = _loadHtmlTemplate("html/date.html");
        $yearHtml = str_replace("{YEAR}", $year, $yearHtml);
        $yearHtml = str_replace("{MOUNTH}", $monthLinks, $yearHtml);
        $html .= $yearHtml;
    } else {
        foreach ($years as $year => $months) {
            $monthLinks = '';
            foreach ($allMonths as $num => $name) {
                if (in_array($num, $months)) {
                    $monthLinks .= "<a href='index.php?year=$year&month=$num'>$name</a> ";
                } else {
                    $monthLinks .= "<span>$name</span> ";
                }
            }
            $yearHtml = _loadHtmlTemplate("html/date.html");
            $yearHtml = str_replace("{YEAR}", $year, $yearHtml);
            $yearHtml = str_replace("{MOUNTH}", $monthLinks, $yearHtml);
            $html .= $yearHtml;
        }
    }
    return $html;
}

function displayUserPage($db, $role, $userId) {
    $header = _loadHtmlTemplate("html/header.html");
    $username = _getUsrNameFromDB($db, $userId);
    $user_info = "<div>Добро пожаловать, " . $username . "!</div>";
    $header = str_replace("{LOGIN-INFO}", $user_info, $header);

    $monthsHtml = _generateMonthsHtml($db, $userId);
    
    // Загрузка и замена основного шаблона для роли
    $bodyFile = "html/body-employee.html"; // default
    if ($role == 1) {
        $bodyFile = "html/body-head.html";
    } elseif ($role == 2) {
        $bodyFile = "html/admin.html";
    }
    $body = _loadHtmlTemplate($bodyFile);
    $body = str_replace("{DATE}", $monthsHtml, $body);

    echo $header . $body;
}

try {
    $db = new SQLite3($dbPath);

    // Обработка отправки отчетов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
    
        if ($action === 'get_users') {
            $stmt = $db->prepare('SELECT user_id, username, pass, surname, name, lastname, unit, role_id FROM users');
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
            $unit = isset($_POST['unit']) ? $_POST['unit'] : '';
            $roleId = isset($_POST['role_id']) ? $_POST['role_id'] : '';
    
            $stmt = $db->prepare('UPDATE users SET username = :username, pass = :pass, surname = :surname, name = :name, lastname = :lastname, unit = :unit, role_id = :role_id WHERE user_id = :user_id');
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':pass', $pass, SQLITE3_TEXT);
            $stmt->bindValue(':surname', $surname, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':lastname', $lastname, SQLITE3_TEXT);
            $stmt->bindValue(':unit', $unit, SQLITE3_TEXT); 
            $stmt->bindValue(':role_id', $roleId, SQLITE3_INTEGER);
    
            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to execute statement'));
            }
            exit();
        }
    
        if ($action === 'add_user') {
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $pass = isset($_POST['pass']) ? $_POST['pass'] : '';
            $surname = isset($_POST['surname']) ? $_POST['surname'] : '';
            $name = isset($_POST['name']) ? $_POST['name'] : '';
            $lastname = isset($_POST['lastname']) ? $_POST['lastname'] : '';
            $unit = isset($_POST['unit']) ? $_POST['unit'] : '';
            $roleId = isset($_POST['role_id']) ? $_POST['role_id'] : '';
    
            $stmt = $db->prepare('INSERT INTO users (username, pass, surname, name, lastname, unit, role_id) VALUES (:username, :pass, :surname, :name, :lastname, :unit, :role_id)');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':pass', $pass, SQLITE3_TEXT);
            $stmt->bindValue(':surname', $surname, SQLITE3_TEXT);
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':lastname', $lastname, SQLITE3_TEXT);
            $stmt->bindValue(':unit', $unit, SQLITE3_TEXT);
            $stmt->bindValue(':role_id', $roleId, SQLITE3_INTEGER);
    
            if ($stmt->execute()) {
                echo json_encode(array('status' => 'success'));
            } else {
                echo json_encode(array('status' => 'error', 'message' => 'Failed to execute statement'));
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
    }

    // Создание таблиц, если они не существуют
    $db->exec("CREATE TABLE IF NOT EXISTS roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)");
    $db->exec("CREATE TABLE IF NOT EXISTS users (user_id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, pass TEXT, surname TEXT, name TEXT, lastname TEXT, unit INTEGER, role_id INTEGER, FOREIGN KEY (role_id) REFERENCES roles(role_id))");
    $db->exec("CREATE TABLE IF NOT EXISTS reports (report_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, report_text TEXT, send_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(user_id))");
    
    $names = array('head', 'admin', 'user');
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
        array('username' => 'head', 'pass' => 'passhead', 'surname' => 'Иванов', 'name' => 'Иван', 'lastname' => 'Иванович', 'unit' => 143, 'role_id' => 1),
        array('username' => 'admin', 'pass' => 'passadmin', 'surname' => 'Петров', 'name' => 'Петр', 'lastname' => 'Петрович', 'unit' => 142, 'role_id' => 2),
        array('username' => 'user', 'pass' => 'passuser', 'surname' => 'Сергеев', 'name' => 'Сергей', 'lastname' => 'Сергеевич', 'unit' => 141, 'role_id' => 3)
    );
    
    $checkUser = $db->prepare('SELECT COUNT(*) AS count FROM users WHERE username = :username');
    $stmtUser = $db->prepare('INSERT INTO users (username, pass, surname, name, lastname, unit, role_id) VALUES (:username, :pass, :surname, :name, :lastname, :unit, :role_id)');
    
    foreach ($users as $user) {
        $checkUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
        $result = $checkUser->execute()->fetchArray(SQLITE3_ASSOC);
    
        if ($result['count'] == 0) {
            $stmtUser->bindValue(':username', $user['username'], SQLITE3_TEXT);
            $stmtUser->bindValue(':pass', $user['pass'], SQLITE3_TEXT);
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

        $username = $_COOKIE['username'];
        $password = $_COOKIE['password'];

        $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username AND pass = :pass');
        $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
        $stmtAuth->bindValue(':pass', $password, SQLITE3_TEXT);
        $result = $stmtAuth->execute();

        if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];

            displayUserPage($db, $user['role_id'], $_SESSION['user_id']);
            exit();
        } else {
            setcookie("username", "", time() - 3600, "/");
            setcookie("password", "", time() - 3600, "/");
            echo _loadHtmlTemplate("html/authorization.html");
            exit();
        }
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];

            $stmtAuth = $db->prepare('SELECT * FROM users WHERE username = :username AND pass = :pass');
            $stmtAuth->bindValue(':username', $username, SQLITE3_TEXT);
            $stmtAuth->bindValue(':pass', $password, SQLITE3_TEXT);
            $result = $stmtAuth->execute();

            if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];

                setcookie("username", $username, time() + (30 * 24 * 60 * 60), "/");
                setcookie("password", $password, time() + (30 * 24 * 60 * 60), "/");

                displayUserPage($db, $user['role_id'], $_SESSION['user_id']);
                exit();
            } else {
                echo "<script type='text/javascript'>alert('Неверное имя пользователя или пароль.');</script>";
                echo _loadHtmlTemplate("html/authorization.html");
            }
        } else {
            echo _loadHtmlTemplate("html/authorization.html");
        }
    }
} catch (Exception $e) {
    echo "Не удалось открыть базу данных: " . $e->getMessage();
}
