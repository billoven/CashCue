<?php
require_once __DIR__ . '/../config/database.php';

class OrderTransaction {
    private $conn;
    private $table = 'order_transaction';

    public $id;
    public $broker_account_id;
    public $instrument_id;
    public $order_type;
    public $quantity;
    public $price;
    public $trade_date;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function create() {
        $sql = "INSERT INTO {$this->table} (broker_account_id, instrument_id, order_type, quantity, price, trade_date)
                VALUES (:broker_account_id, :instrument_id, :order_type, :quantity, :price, :trade_date)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':broker_account_id', $this->broker_account_id);
        $stmt->bindParam(':instrument_id', $this->instrument_id);
        $stmt->bindParam(':order_type', $this->order_type);
        $stmt->bindParam(':quantity', $this->quantity);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':trade_date', $this->trade_date);
        return $stmt->execute();
    }

    public function readRecent($limit = 10) {
        $sql = "SELECT o.id, i.symbol, o.order_type, o.quantity, o.price, o.trade_date
                FROM {$this->table} o
                JOIN instrument i ON i.id = o.instrument_id
                ORDER BY o.trade_date DESC
                LIMIT :limit";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
