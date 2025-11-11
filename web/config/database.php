<?php
class Database {
    private $pdo;
    private $configFile;

    public function __construct($env = 'production') {
        // Choose config file based on environment
        if ($env === 'development') {
            $this->configFile = '/etc/cashcue/cashcue-dev.conf';
        } else {
            $this->configFile = '/etc/cashcue/cashcue.conf';
        }
        $this->connect();
    }

    private function parseConfig() {
        if (!file_exists($this->configFile)) {
            throw new Exception("CashCue config file not found: {$this->configFile}");
        }

        if (!is_readable($this->configFile)) {
            throw new Exception("CashCue config file is not readable: {$this->configFile}");
        }
        
        $lines = file($this->configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            // Remove inline comments (anything after an unquoted #)
            if (preg_match('/(^[^#]*)/', $line, $matches)) {
                $line = trim($matches[1]);
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip optional surrounding quotes (single or double)
                if (
                    (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                ) {
                    $value = substr($value, 1, -1);
                }

                $config[$key] = $value;
            }
        }
        return $config;
    }


    private function connect() {
        $config = $this->parseConfig();

        $host = $config['DB_HOST'] ?? 'localhost';
        $port = $config['DB_PORT'] ?? 3306;
        $db   = $config['DB_NAME'] ?? 'cashcue';
        $user = $config['DB_USER'] ?? 'cashcue_user';
        $pass = $config['DB_PASS'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}



