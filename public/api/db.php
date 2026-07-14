<?php
/**
 * Подключение к базе данных.
 * Использует public/api/db_config.php — этот файл НЕ в git,
 * он генерируется автоматически при деплое из GitHub Secrets.
 * Локально/для теста можно создать db_config.php вручную по тому же формату:
 *
 * <?php
 * return [
 *     'db_host' => '...',
 *     'db_user' => '...',
 *     'db_pass' => '...',
 *     'db_name' => '...',
 * ];
 */

function db_connect(): mysqli {
    $configPath = __DIR__ . '/db_config.php';

    if (!file_exists($configPath)) {
        http_response_code(500);
        die(json_encode(['error' => 'Конфигурация базы данных отсутствует (db_config.php не найден)']));
    }

    $config = require $configPath;

    mysqli_report(MYSQLI_REPORT_OFF); // сами обрабатываем ошибки, без исключений по умолчанию

    $conn = mysqli_connect(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );

    if (!$conn) {
        http_response_code(500);
        die(json_encode(['error' => 'Не удалось подключиться к базе данных']));
    }

    mysqli_set_charset($conn, 'utf8mb4');

    return $conn;
}
