<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Вычисляем полный путь к app.php относительно текущей папки на сервере,
// чтобы редирект работал одинаково что в корне домена, что в подпапке (/beta/)
$currentDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
header('Location: ' . $currentDir . '/app.php');
exit;
