<?php
/**
 * AgriConnect - Database Configuration
 * MySQL PDO connection singleton
 *
 * Credentials are read from environment variables so the same code runs
 * locally (falls back to the original defaults) and on hosted environments.
 *
 * Optional TLS (required by most managed MySQL providers):
 *   DB_SSL_CA                  path or PEM contents of the CA certificate
 *   DB_SSL_NO_VERIFY=1         skip server-cert verification (self-signed hosts)
 */

function agriEnv($key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === null || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($v === null || $v === '') ? $default : $v;
}

define('DB_HOST',    agriEnv('DB_HOST', 'localhost'));
define('DB_NAME',    agriEnv('DB_NAME', 'agriconnect_db'));
define('DB_USER',    agriEnv('DB_USER', 'root'));
define('DB_PASS',    agriEnv('DB_PASS', ''));
define('DB_CHARSET', agriEnv('DB_CHARSET', 'utf8mb4'));
define('DB_PORT',    agriEnv('DB_PORT', '3306'));

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            // Opt-in TLS for managed MySQL hosts that require encrypted connections.
            // DB_SSL_CA may be a file path OR inline PEM certificate text.
            $sslCa = agriEnv('DB_SSL_CA');
            if ($sslCa) {
                if (strpos($sslCa, 'BEGIN CERTIFICATE') !== false) {
                    // Inline PEM: write to a temp file that PDO can read.
                    $caPath = sys_get_temp_dir() . '/agriconnect_mysql_ca.pem';
                    file_put_contents($caPath, $sslCa);
                    @chmod($caPath, 0600);
                } else {
                    $caPath = $sslCa;
                }
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                if (agriEnv('DB_SSL_NO_VERIFY')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone() {}
}

// Helper function to get DB connection
function getDB() {
    return Database::getInstance()->getConnection();
}
?>
