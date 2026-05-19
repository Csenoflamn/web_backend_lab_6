<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $config = require __DIR__ . '/../config/db.php';
        try {
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}",
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Ошибка подключения к базе данных.');
        }
    }
    return $pdo;
}