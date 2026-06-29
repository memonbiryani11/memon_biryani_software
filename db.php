<?php
// Local
// $host = 'localhost';
// $db   = 'u164642147_support';
// $user = 'root'; 
// $pass = '';     
// $charset = 'utf8mb4';

// Server
$host = 'localhost';
$db   = 'u164642147_support';
$user = 'u164642147_support'; 
$pass = 'Admin@260902';     
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // FIX: Connection bante hi collation aur character set ko force kar rahe hain
     $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
     $pdo->exec("SET CHARACTER SET utf8mb4");
     $pdo->exec("SET collation_connection = utf8mb4_general_ci");
     
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>