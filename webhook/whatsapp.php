<?php
include_once("../clases/class_whats.php");
$whats = new whats();
// ========= VARIABLES  =========
$VERIFY_TOKEN = WHATS_SECRET_C4Y;
$APP_SECRET   = WHATS_APP_SECRET;  

// ============================================

// VALIDAR VERIFICACIÓN INICIAL DE META
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'])) {
    if ($_GET['hub_verify_token'] === $VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo 'Token inválido';
        exit;
    }
}

// VALIDAR FIRMA DEL MENSAJE
$signature     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$raw_body      = file_get_contents('php://input');
$expected_hash = 'sha256=' . hash_hmac('sha256', $raw_body, $APP_SECRET);

if (!hash_equals($expected_hash, $signature)) {
    http_response_code(403);
    error_log('⚠️ Webhook: firma inválida');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = json_decode($raw_body, true);

    if (isset($json["entry"][0]["changes"][0]["value"]["statuses"])) {
        $whats->procesarWebhookStatus(["data" => $raw_body]);
    }

    if (isset($json["entry"][0]["changes"][0]["value"]["messages"])) {
        $whats->procesarWebhookWhatsApp(["data" => $raw_body]);
    }

    http_response_code(200);
    echo "OK";
}

?>