<?php
// config/database.php — PDO singleton using .env credentials
require_once __DIR__ . '/env.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'casestudy';
            $port = getenv('DB_PORT') ?: '3307';
            $name = getenv('DB_NAME') ?: 'quiz_generator';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // REAL prepared statements
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Never expose DB credentials in error messages
                error_log('DB Connection failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }

    // Prevent cloning / unserialization
    private function __clone() {}
    public function __wakeup() { throw new Exception('Cannot unserialize singleton.'); }
}

/**
 * Convenience wrapper — returns a prepared and executed PDOStatement.
 * All params are bound; no string interpolation used anywhere.
 */
function db_query(string $sql, array $params = []): PDOStatement {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_last_id(): string {
    return Database::getInstance()->lastInsertId();
}
