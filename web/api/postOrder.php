<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/OrderTransaction.php';
require_once __DIR__ . '/../classes/Instrument.php';

$data = json_decode(file_get_contents("php://input"));
if (!$data) { echo json_encode(["status" => "error", "message" => "Invalid input."]); exit; }

$instrument_id = (int)($data->instrument_id ?? 0);

// If new instrument was entered
if ($instrument_id === 0 && !empty($data->new_symbol)) {
    $inst = new Instrument();
    $instrument_id = $inst->create(
        strtoupper(trim($data->new_symbol)),
        $data->new_label ?? $data->new_symbol,
        strtoupper(trim($data->new_isin ?? ''))
    );
}

if ($instrument_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Instrument missing."]);
    exit;
}

$order = new OrderTransaction();
$order->broker_account_id = 1;
$order->instrument_id = $instrument_id;
$order->order_type = strtoupper($data->order_type);
$order->quantity = (float)$data->quantity;
$order->price = (float)$data->price;
$order->trade_date = $data->trade_date;
$order->fees = (float)($data->fees ?? 0);

if ($order->create()) {
    echo json_encode(["status" => "success", "message" => "Order saved successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to insert order."]);
}
