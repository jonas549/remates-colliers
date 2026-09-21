<?php

/*
 * Arranque de phpunit.mysql.xml: apunta la suite a una base MySQL/MariaDB propia y la crea si no existe.
 * Se fija antes de que Laravel lea el .env (Dotenv no pisa variables que ya existen).
 */

require __DIR__ . '/../vendor/autoload.php';

$conexion = [
    'DB_HOST' => getenv('PRUEBAS_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('PRUEBAS_DB_PORT') ?: '3306',
    'DB_USERNAME' => getenv('PRUEBAS_DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('PRUEBAS_DB_PASSWORD') ?: '',
];
foreach ($conexion as $nombre => $valor) {
    putenv("{$nombre}={$valor}");
    $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
}

$base = getenv('DB_DATABASE') ?: 'colliers_pruebas';

try {
    $pdo = new PDO("mysql:host={$conexion['DB_HOST']};port={$conexion['DB_PORT']}", $conexion['DB_USERNAME'], $conexion['DB_PASSWORD']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    fwrite(STDERR, "\nNo hay MySQL/MariaDB en {$conexion['DB_HOST']}:{$conexion['DB_PORT']} ({$e->getMessage()}).\n"
        . "Enciende el MySQL de Laragon o define PRUEBAS_DB_HOST / PRUEBAS_DB_PORT / PRUEBAS_DB_USER / PRUEBAS_DB_PASSWORD.\n\n");
    exit(1);
}
