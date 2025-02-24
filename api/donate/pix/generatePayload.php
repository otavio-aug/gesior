<?php
require_once("../../../config/config.php");

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function sanitizeValue($value) {
    return number_format((float) preg_replace("/\D/", "", $value), 2, ".", "");
}

$baseSiteUrl                 = "http://158.69.175.192";
$notificationUrl             = $baseSiteUrl . "/api/donate/pix/webhook.php";

$conn = new PDO(
    'mysql:host=' . $config["api"]["host"] . ';dbname=' . $config["api"]["database"],
    $config["api"]["databaseUser"],
    $config["api"]["databasePass"]
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$body         = file_get_contents("php://input");
$receivedData = json_decode($body, true);
$value        = sanitizeValue(filter_var($receivedData["value"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
$name         = htmlspecialchars($receivedData["name"], ENT_QUOTES, 'UTF-8');
$email        = filter_var($receivedData["email"], FILTER_VALIDATE_EMAIL);
$playerName   = htmlspecialchars($receivedData["playerName"], ENT_QUOTES, 'UTF-8');

if (!$value || !$name || !$email || !$playerName) {
    die;
}

$stmt = $conn->prepare("SELECT id, account_id FROM players WHERE name = :name");
$stmt->execute(array("name" => $playerName));
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$result) {
    die;
}

$playerId = $result["id"];
$accountId = $result["account_id"];

$stmt = $conn->prepare("SELECT name FROM accounts WHERE id = :id");
$stmt->execute(array("id" => $accountId));
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$accountName = $result["name"];

$externalReference = generateUUID();
$MPRequestHeaders = [
    "Authorization: Bearer " . $config["MercadoPago"]["token"],
    "X-Idempotency-Key: " . generateUUID(),
    "Content-Type: application/json"
];

$MPRequestData = [
    "notification_url"      => $notificationUrl,
    "description"           => "$value points in game.",
    "external_reference"    => $externalReference,
    "transaction_amount"    => (float) $value,
    "payment_method_id"     => "pix",
    "payer" => [
        "first_name" => $name,
        "last_name" => "",
        "email" => $email,
    ]
];

$ch = curl_init("https://api.mercadopago.com/v1/payments/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $MPRequestHeaders);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($MPRequestData));
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode >= 200 && $httpCode < 300) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");
    $response = json_decode($response, true);

    $stmt = $conn->prepare("INSERT INTO transactions_mercadopagopix 
        (account_id, account_name, player_name, player_id, external_reference, mercado_pago_id, transaction_amount, points, payer_email, payer_first_name, status, created_at)
        VALUES (:account_id, :account_name, :player_name, :player_id, :external_reference, :mercado_pago_id, :transaction_amount, :points, :payer_email, :payer_first_name, :status, NOW())");

    $status = "pending";
    $mercadoPagoId = $response["id"];

    if (!$stmt->execute(array(
        "account_id"             => $accountId,
        "account_name"           => $accountName,
        "player_name"            => $playerName,
        "player_id"              => $playerId,
        "external_reference"     => $externalReference,
        "mercado_pago_id"        => $mercadoPagoId,
        "transaction_amount"     => $value,
        "points"                 => $value,
        "payer_email"            => $email,
        "payer_first_name"       => $name,
        "status"                 => $status
    ))) {
        die;
    }

    echo json_encode([
        "qr_code" => $response["point_of_interaction"]["transaction_data"]["qr_code_base64"]
    ]);
}
