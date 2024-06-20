<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
    <link rel="stylesheet" href="html/main_window.css">
</head>
<body>
<header>
    <div class = "logo-container">
        <img src="src/logo.png" alt="Логотип компании" class="logo">
    </div>
    <div class="company-info">
        {company-info}
    </div>
    <div class="login-info">
        <?php
        if (isset($_SESSION['username'])) {
            echo "Авторизирован как " . htmlspecialchars($_SESSION['username']);
        } else {
            echo "Не авторизирован";
        }
        ?>
    </div>
</header>
</body>
</html>