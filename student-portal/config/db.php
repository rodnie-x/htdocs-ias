<?php
// >>> PLACE AT: student-portal/config/db.php  (replace the old file ON THE LIVE SERVER only; keep your XAMPP copy separate)
/**
 * Database connection. Same database as the Class Scheduling System.
 * FILL IN the 4 values below from InfinityFree: Client Area > MySQL Databases (same values as css-system).
 * Never use 'root' on a live server.
 */
define('DB_HOST', 'sql313.infinityfree.com');   // InfinityFree → sqlXXX.infinityfree.com
define('DB_NAME', 'if0_43074378_css_system'); // InfinityFree → if0_XXXXXXXX_css_system
define('DB_USER', 'if0_43074378');       // InfinityFree → if0_XXXXXXXX
define('DB_PASS', 'ni21uEmwihXstg9');       // InfinityFree → your MySQL password

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Keep the reason in the server log only; an uncaught exception could print the connection details.
            error_log('Student portal DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Cannot connect to the database. Please tell the Registrar.');
        }
    }
    return $pdo;
}