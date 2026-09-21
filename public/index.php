<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP ', PHP_VERSION, PHP_EOL;

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'),
            getenv('DB_PORT'),
            getenv('DB_NAME'),
        ),
        (string) getenv('DB_USER'),
        (string) getenv('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );

    echo 'MySQL ', $pdo->query('SELECT VERSION()')->fetchColumn(), PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'MySQL: ', $e->getMessage(), PHP_EOL;
}
