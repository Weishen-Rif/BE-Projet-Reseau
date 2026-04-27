<?php
/**
 * ARCHITECTURE 3 TIERS - COUCHE DONNÉES
 * Ce fichier établit la connexion avec PostgreSQL.
 * Il doit être inclus au début de chaque script nécessitant la base de données.
 */

// 1. Démarrage de la session (Condition Multi-utilisateurs de votre cours)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Paramètres de connexion (Configuration standard Laragon / PostgreSQL)
// Adaptez le mot de passe si vous en avez défini un différent (ex: 'root' ou '')
$host = "localhost";
$port = "5432";
$dbname = "simulation_reseau";
$user = "postgres";
$password = "postgres"; 

// 3. Connexion native PostgreSQL
// Utilisation stricte de la syntaxe pg_connect() de votre cours
// La chaîne de connexion regroupe les paramètres
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";

$connect = pg_connect($conn_string) 
           or die("Erreur fatale : Impossible de se connecter à la base de données PostgreSQL.");

/** * Note : L'identifiant de connexion est stocké dans la variable $connect.
 * Elle sera utilisée par pg_exec() dans le fichier logique.php.
 */
?>