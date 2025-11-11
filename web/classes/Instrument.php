<?php
require_once __DIR__ . '/../config/database.php';

class Instrument {
    private $conn;
    private $table = 'instrument';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function getAll() {
        $sql = "SELECT id, symbol, label FROM {$this->table} ORDER BY symbol ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDailyPrices($instrument_id, $periodDays = 30) {
        $sql = "SELECT date, close_price FROM daily_price 
                WHERE instrument_id = :id 
                ORDER BY date DESC LIMIT :limit";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int)$instrument_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$periodDays, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($symbol, $label, $isin, $currency = 'EUR', $type = 'STOCK') {
        $sql = "INSERT INTO {$this->table} (symbol, label, isin, currency, type)
                VALUES (:symbol, :label, :isin, :currency, :type)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':symbol', $symbol);
        $stmt->bindParam(':label', $label);
        $stmt->bindParam(':isin', $isin);
        $stmt->bindParam(':currency', $currency);
        $stmt->bindParam(':type', $type);
        $stmt->execute();
        return $this->conn->lastInsertId();
    }
}
