<?php
/**
 * ARCHITECTURE 3 TIERS - COUCHE DONNÉES
 * Responsable : Robin RIGAL
 * Rôle : Persistance et structuration des données
 * Ce fichier établit la connexion avec PostgreSQL.
 * Il doit être inclus au début de chaque script nécessitant la base de données.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: "localhost";
$port = getenv('DB_PORT') ?: "5432";
$dbname = getenv('DB_NAME') ?: "simulation_reseau";
$user = getenv('DB_USER') ?: "postgres";
$password = getenv('DB_PASS') ?: "postgres"; 

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erreur fatale : Impossible de se connecter à la base de données PostgreSQL.");
}
?>
