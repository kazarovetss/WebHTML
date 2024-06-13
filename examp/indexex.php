<?php
set_time_limit(0);
date_default_timezone_set('Europe/Minsk');

// Укажите полный путь к файлу базы данных
$dbPath = 'C:/xampp/htdocs/WebHTML/examp/db/firmwares.db';
$dir = __DIR__ . '/firmwares';

try {
    // Подключение к базе данных SQLite
    $db = new SQLite3($dbPath);

    // Создание таблицы, если она не существует
    $db->exec("CREATE TABLE IF NOT EXISTS firmwares (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        Project_id TEXT, 
        MCU_id TEXT, 
        Major INTEGER, 
        Minor INTEGER, 
        Text TEXT, 
        AUX_INFO TEXT, 
        Date INTEGER, 
        Path TEXT
    )");

    echo "Таблица создана или уже существует.";

    // Получение значений из $_GET с проверкой на наличие
    $pr_id = isset($_GET['pr_id']) ? $_GET['pr_id'] : '';
    $mcu_id = isset($_GET['mcu_id']) ? $_GET['mcu_id'] : '';
    $ma_fr = isset($_GET['ma_fr']) ? (int)$_GET['ma_fr'] : 0;
    $ma_to = isset($_GET['ma_to']) ? (int)$_GET['ma_to'] : 65535;
    $mi_fr = isset($_GET['mi_fr']) ? (int)$_GET['mi_fr'] : 0;
    $mi_to = isset($_GET['mi_to']) ? (int)$_GET['mi_to'] : 65535;
    $date_fr = isset($_GET['date_fr']) ? $_GET['date_fr'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Пример вставки данных в таблицу
    $stmt = $db->prepare('INSERT INTO firmwares (Project_id, MCU_id, Major, Minor, Text, AUX_INFO, Date, Path) VALUES (:pr_id, :mcu_id, :major, :minor, :text, :aux_info, :date, :path)');
    $stmt->bindValue(':pr_id', $pr_id, SQLITE3_TEXT);
    $stmt->bindValue(':mcu_id', $mcu_id, SQLITE3_TEXT);
    $stmt->bindValue(':major', $ma_fr, SQLITE3_INTEGER);
    $stmt->bindValue(':minor', $mi_to, SQLITE3_INTEGER);
    $stmt->bindValue(':text', 'Example text', SQLITE3_TEXT); // Замените на ваше значение
    $stmt->bindValue(':aux_info', 'Example aux info', SQLITE3_TEXT); // Замените на ваше значение
    $stmt->bindValue(':date', strtotime($date_fr), SQLITE3_INTEGER); // Преобразование даты в таймштамп
    $stmt->bindValue(':path', 'path/to/firmware', SQLITE3_TEXT); // Замените на ваше значение
    $stmt->execute();

    echo "Данные успешно добавлены в таблицу.";
} catch (Exception $e) {
    echo "Не удалось открыть базу данных: " . $e->getMessage();
}

if(file_exists('countview.num')){// количество материалов на странице
    $countView = intval(file_get_contents('countview.num'));
}
else{
    $countView = 10; 
}

// Строка таблицы
$tableStr = "";
// Показ страниц
$pages = "";

// номер страницы
if(isset($_GET['page'])){
    $pageNum = (int)$_GET['page'];
}else{
    $pageNum = 1;
}
$startIndex = ($pageNum-1)*$countView; // с какой записи начать выборку

if (isset($_GET['submit_btn'])) {
    $query = "SELECT *  from firmwares WHERE ";
    if(isset($_GET['pr_id']) && $_GET['pr_id']){
        $query.="Project_id = '".$_GET['pr_id']."' AND ";
    }
    if(isset($_GET['mcu_id']) && $_GET['mcu_id']){
        $query.="MCU_id = '".$_GET['mcu_id']."' AND ";
    }
    if(isset($_GET['date_fr']) && $_GET['date_fr']){
        $query.="Date >= '".date_timestamp_get(date_create_from_format('Y-m-d H:i:s', $_GET['date_fr']." 00:00:00"))."' AND ";
    }
    if(isset($_GET['date_to']) && $_GET['date_to']){
        $query.="Date <= '".date_timestamp_get(date_create_from_format('Y-m-d H:i:s', $_GET['date_to']." 23:59:59"))."' AND ";
    }
    if(isset($_GET['ma_fr']) && isset($_GET['ma_to'])){
        $query.="Major BETWEEN '".$_GET['ma_fr']."' AND '".$_GET['ma_to']."' AND ";
    }
    if(isset($_GET['mi_fr']) && isset($_GET['mi_to'])){
        $query.="Minor BETWEEN '".$_GET['mi_fr']."' AND '".$_GET['mi_to']."' ";
    }
    $count = $db->query("SELECT COUNT(*)".substr($query, 8))->fetchArray();
    $query.= "ORDER BY Date DESC LIMIT $startIndex, $countView";
    $result = $db->query($query);
}
else{
    $result = $db->query("SELECT *  from firmwares ORDER BY Date DESC LIMIT $startIndex, $countView");
    $count = $db->query("SELECT COUNT(*) from firmwares")->fetchArray();
    $tableStr = $tableStr."<script> 
       document.getElementById('majfrom').value = 0;
       document.getElementById('majto').value = 65535;
       document.getElementById('minfrom').value = 0;
       document.getElementById('minto').value = 65535;
      </script>";
}

// номер последней страницы
$lastPage = ceil($count[0]/$countView);

$tableStr = $tableStr."<table class='downtable'><tr>";
$tableStr = $tableStr.'<th>'."Скачать".'</th>';
$tableStr = $tableStr.'<th>'."Ст. версия".'</th>';
$tableStr = $tableStr.'<th>'."Мл. версия".'</th>';
$tableStr = $tableStr.'<th>'."Описание".'</th>';
$tableStr = $tableStr.'<th>'."Дополнительно".'</th>';
$tableStr = $tableStr.'<th>'."Дата".'</th>';
$tableStr = $tableStr."</tr>\n";

while($res=$result->fetchArray())
{
    $tableStr = $tableStr."<tr>";
    $file = basename($res[8]);
    $link = strstr($res[8], "firmwares");
    $date = date('H:i:s d.m.Y', $res[7]);
    $tableStr = $tableStr."<td><a href='$link' download>$file</a></td>";
    $tableStr = $tableStr."<td>  $res[3]</td>";
    $tableStr = $tableStr."<td>  $res[4]</td>";
    $str = mb_convert_encoding($res[5], "UTF-8", "Windows-1251");
    $str = str_replace("\n", "</br>", $str);
    $tableStr = $tableStr."<td><p class='tdtxt'>  $str</p></td>";
    $tableStr = $tableStr."<td>  $res[6]</td>";
    $tableStr = $tableStr."<td>  $date</td>";
    $tableStr = $tableStr."</tr>\n";
}
$tableStr = $tableStr."</table>";

// Генерация контента номеров страниц
if(isset($_GET['submit_btn']))
{
    if($pageNum > 1)
    {
        $pages = $pages.'<li><a href="/download.php?page=1&pr_id='.$_GET['pr_id'].'&mcu_id='.$_GET['mcu_id'].'&ma_fr='.$_GET['ma_fr'].'&ma_to='.$_GET['ma_to'].'&mi_fr='.$_GET['mi_fr'].'&mi_to='.$_GET['mi_to'].'&date_fr='.$_GET['date_fr'].'&date_to='.$_GET['date_to'].'&submit_btn=Применить">&lt;&lt;</a></li>';
        $pages = $pages.'<li><a href="/download.php?page='.strval($pageNum-1).'&pr_id='.$_GET['pr_id'].'&mcu_id='.$_GET['mcu_id'].'&ma_fr='.$_GET['ma_fr'].'&ma_to='.$_GET['ma_to'].'&mi_fr='.$_GET['mi_fr'].'&mi_to='.$_GET['mi_to'].'&date_fr='.$_GET['date_fr'].'&date_to='.$_GET['date_to'].'&submit_btn=Применить">&lt;</a></li>';
    }
    
    for($i = 1; $i<=$lastPage; $i++)
    {
        $temp = ($i == $pageNum) ? 'class="current"' : '';
        $pages = $pages.'<li '.$temp.'> <a href="/download.php?page='.strval($i).'&pr_id='.$_GET['pr_id'].'&mcu_id='.$_GET['mcu_id'].'&ma_fr='.$_GET['ma_fr'].'&ma_to='.$_GET['ma_to'].'&mi_fr='.$_GET['mi_fr'].'&mi_to='.$_GET['mi_to'].'&date_fr='.$_GET['date_fr'].'&date_to='.$_GET['date_to'].'&submit_btn=Применить">'.strval($i).'</a> </li>'.PHP_EOL;
    }
    
    if($pageNum < $lastPage)
    {
        $pages = $pages.'<li><a href="/download.php?page='.strval($pageNum+1).'&pr_id='.$_GET['pr_id'].'&mcu_id='.$_GET['mcu_id'].'&ma_fr='.$_GET['ma_fr'].'&ma_to='.$_GET['ma_to'].'&mi_fr='.$_GET['mi_fr'].'&mi_to='.$_GET['mi_to'].'&date_fr='.$_GET['date_fr'].'&date_to='.$_GET['date_to'].'&submit_btn=Применить">&gt;</a></li>'.PHP_EOL;
        $pages = $pages.'<li><a href="/download.php?page='.strval($lastPage).'&pr_id='.$_GET['pr_id'].'&mcu_id='.$_GET['mcu_id'].'&ma_fr='.$_GET['ma_fr'].'&ma_to='.$_GET['ma_to'].'&mi_fr='.$_GET['mi_fr'].'&mi_to='.$$_GET['mi_to'].'&date_fr='.$_GET['date_fr'].'&date_to='.$_GET['date_to'].'&submit_btn=Применить">&gt;&gt;</a></li>'.PHP_EOL;
    }
} 
else
{
    if($pageNum > 1)
    {
        $pages = $pages.'<li><a href="/download.php?page=1">&lt;&lt;</a></li>';
        $pages = $pages.'<li><a href="/download.php?page='.strval($pageNum-1).'">&lt;</a></li>';
    }
    
    for($i = 1; $i<=$lastPage; $i++)
    {
        $temp = ($i == $pageNum) ? 'class="current"' : '';
        $pages = $pages.'<li '.$temp.'> <a href="/download.php?page='.strval($i).'">'.strval($i).'</a> </li>';
    }
    
    if($pageNum < $lastPage)
    {
        $pages = $pages.'<li><a href="/download.php?page='.strval($pageNum+1).'">&gt;</a></li>';
        $pages = $pages.'<li><a href="/download.php?page='.strval($lastPage).'">&gt;&gt;</a></li>';
    }
}

// Загружаем шаблоны
$header = file_get_contents("html/header.html");
$footer = file_get_contents("html/footer.html");
$body = file_get_contents("html/download.html");

// Заменяем метки на динамичесий контент
$body = str_replace("{TABLE}", $tableStr, $body);
$body = str_replace("{PROJECT}", isset($_GET['pr_id']) ? $_GET['pr_id'] : '', $body);
$body = str_replace("{MCU_ID}", isset($_GET['mcu_id']) ? $_GET['mcu_id'] : '', $body);
$body = str_replace("{MAJFROM}", isset($_GET['ma_fr']) ? $_GET['ma_fr'] : '', $body);
$body = str_replace("{MAJTO}", isset($_GET['ma_to']) ? $_GET['ma_to'] : '', $body);
$body = str_replace("{MINFROM}", isset($_GET['mi_fr']) ? $_GET['mi_fr'] : '', $body);
$body = str_replace("{MINTO}", isset($_GET['mi_to']) ? $_GET['mi_to'] : '', $body);
$body = str_replace("{DATEFROM}", isset($_GET['date_fr']) ? $_GET['date_fr'] : '', $body);
$body = str_replace("{DATETO}", isset($_GET['date_to']) ? $_GET['date_to'] : '', $body);

$footer = str_replace("{PAGES}", $pages, $footer);

// Вывод страницы
echo $header;
echo $body;
echo $footer;
?>
