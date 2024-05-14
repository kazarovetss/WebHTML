<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['username'];
    $pass = $_POST['password'];

    try {
        require_once "db_handler.php";
            $sql = "SELECT * FROM users WHERE username = :username AND pass = :pass";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':username', $name);
            //ДОБАВИТЬ ХЭШИРОВАНИЕ ПАРОЛЕЙ? 
            //$hashedPass = password_hash($pass, PASSWORD_DEFAULT); 
            $stmt->bindParam(':pass', $pass);

            $stmt->execute();

            $user = $stmt->fetch();
    
            if ($user) {
                //ДОБАВИТЬ ЗАВИСИМОСТЬ ОТ РОЛИ ПОЛЬЗОВАТЕЛЯ
                header("Location: employee_window.php");
            } else {
                echo "Неверные имя пользователя или пароль";
            }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    header("Location: authorization.php");
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