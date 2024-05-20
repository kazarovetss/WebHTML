<?php
require_once "db_handler.php";

function authenticate($pdo, $name, $pass) {
    $sql = "SELECT * FROM users WHERE username = :username AND pass = :pass";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':username', $name);
    $stmt->bindParam(':pass', $pass);

    $stmt->execute();
    return $stmt->fetch();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['username'];
    $pass = $_POST['password'];

    try {
        $user = authenticate($pdo, $name, $pass);

        if ($user) {
            // Установка куки при успешной аутентификации
            setcookie("username", $name, time() + (30 * 24 * 60 * 60), "/");
            setcookie("password", $pass, time() + (30 * 24 * 60 * 60), "/");

            if (headers_sent($file, $line)) {
                die("Ошибка: заголовки уже отправлены в файле $file на строке $line.");
            }
            // Перенаправление на главную страницу после успешной авторизации
            header("Location: index.php");
            exit;
        } else {
            echo "Неверные имя пользователя или пароль";
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    // Форма авторизации
    include "authorization.php";
}
