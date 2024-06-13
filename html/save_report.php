<?php
// Подключение к базе данных
require_once "db_handler.php";

// Проверка, был ли отправлен текст отчета
if (isset($_POST['text']) && !empty($_POST['text'])) {
    // Получение текста отчета из POST-запроса
    $text = $_POST['text'];

    // Здесь нужно добавить код для определения текущего пользователя
    // Предполагается, что у вас есть система авторизации и пользователь сохранен в сессии
    session_start();
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        try {
            // Подготовка SQL-запроса для вставки данных в таблицу reports
            $sql = "INSERT INTO reports (user_id, report_text, report_date) VALUES (:user_id, :report_text, NOW())";
            $stmt = $pdo->prepare($sql);

            // Привязка параметров к запросу
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':report_text', $text);

            // Выполнение запроса
            $stmt->execute();

            // Отправка ответа клиенту
            echo "Отчет успешно сохранен в базе данных!";
        } catch (PDOException $e) {
            // Обработка ошибок
            echo "Ошибка: " . $e->getMessage();
        }
    } else {
        echo "Ошибка: пользователь не авторизован.";
    }
} else {
    // Если текст отчета не был отправлен, отправляем сообщение об ошибке
    echo "Ошибка: текст отчета не был отправлен.";
}
