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

// Проверка наличия куки
if (isset($_COOKIE['username']) && isset($_COOKIE['password'])) {
    $name = $_COOKIE['username'];
    $pass = $_COOKIE['password'];

    $user = authenticate($pdo, $name, $pass);

    if ($user) {
        // Пользователь успешно аутентифицирован с использованием куки
        echo "Welcome back, " . htmlspecialchars($user['username']) . "!";
        include "employee_window.php"; // Загружает пользовательскую страницу
        exit;
    } else {
        // Печеньки недействительны, перенаправление на страницу авторизации
        header("Location: login.php");
        exit;
    }
} else {
    // Печенек нет, перенаправление на страницу авторизации
    header("Location: login.php");
    exit;
}


//АВТОРИЗАЦИЯ
// function get_user(object $pdo, string $name){
        //     $query = "SELECT * FROM users WHERE username = :username;";
        //     $stmt = $pdo->prepare($query);
        //     $stmt->bindParam(":username", $name);
        //     $stmt->execute();

        //     $result = $stmt->fetch(PDO::FETCH_ASSOC);
        //     return $result;
        // }

        // function is_username_wrong(bool|array $result){
        //     if(!result){
        //         return true;
        //     } else{
        //         return false;
        //     }
        // }

        // function is_password_wrong(string $pass, string $hashedPass){
        //     if(!password_verify($pwd,$hashedPass)){
        //         return true;
        //     } else{
        //         return false;
        //     }
        // }

    //РЕГИСТРАЦИЯ
        // $query = "INSERT INTO users (username, pass) VALUES (?, ?);";

        // $stmt = $pdo->prepare($query);
        // $stmt->execute([$name, $pass]);

        // $pdo = null;
        // $stmt = null;

        // header("Location: autho)rization.php");

        // die();