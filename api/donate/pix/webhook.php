<?php
require_once("../../../config/config.php");

function applyPromotion($value) {
    $multiplier = 1;
    
    $value = (int) $value;

    foreach ($config['MercadoPago']['promotions'] as $promotion) {
        if (($value >= $promotion['min_value']) && ($promotion['max_value'] === null || $promotion <= $promocao['max_value'])) {
            if ($promotion['multiplier'] > $multiplier) {
                $multiplier = $promotion['multiplier'];
            }
        }
    }
    
    return round((float) $value * $multiplier);
}

$body = file_get_contents("php://input");
$receivedData = json_decode($body, true);

$paymentId = $receivedData["data"]["id"] ?? null;

if (!$paymentId) {
    die;
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'content-type: application/json',
        'Authorization: Bearer ' . $config["MercadoPago"]["token"]
    ]
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    die;
}

$response = json_decode($response, true);
curl_close($curl);

if (!$response) {
    die;
}

$MPID              = $response['id'] ?? null;
$status            = $response['status'] ?? null;
$valuePayment      = $response['transaction_amount'] ?? 0;

if (!$MPID || !$status) {
    die;
}

$pdo = new PDO(
    'mysql:host=' . $config["api"]["host"] . ';dbname=' . $config["api"]["database"],
    $config["api"]["databaseUser"],
    $config["api"]["databasePass"]
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql  = "SELECT id, status, transaction_amount, account_id FROM transactions_mercadopagopix WHERE mercado_pago_id = :mercado_pago_id";
$stmt = $pdo->prepare($sql);

$stmt->bindValue(':mercado_pago_id', $MPID);

$stmt->execute(); 

$paymentDatabase = $stmt->fetch(PDO::FETCH_ASSOC) ?? false;

if (!$paymentDatabase) {
    die;
}

if ((int) $paymentDatabase["transaction_amount"] != (int) $valuePayment) {
    die;
}

if ($paymentDatabase["status"] == "pending") {
    $sql  = "UPDATE transactions_mercadopagopix SET status = :status, points = :points, updated_at = :updated_at WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    $value = applyPromotion($paymentDatabase["transaction_amount"]);
    
    $stmt->bindValue(':status',     $status);
    $stmt->bindValue(':points',     $value);
    $stmt->bindValue(':updated_at', date('Y-m-d H:i:s'));
    $stmt->bindValue(':id',         $paymentDatabase['id']);
    
    $stmt->execute();
    
    if ($status == "approved") {
        $sql  = "UPDATE accounts SET points + :value WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindValue(':value',     $value);
        $stmt->bindValue(':id',        $paymentDatabase["account_id"]);
        $stmt->execute();
    }
}