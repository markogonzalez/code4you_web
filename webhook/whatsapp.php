<?php
include_once("../clases/class_whats.php");

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
    $phoneId = $json["entry"][0]["changes"][0]["value"]["metadata"]["phone_number_id"] ?? null;

    if (!$phoneId) {
        http_response_code(400);
        error_log("⚠️ Webhook: evento sin phone_number_id");
        exit;
    }
    // ID DE PRUEBA CAMBIAR CUANDON TENGA EL DE INTERMEDICA
    if($phoneId=="606059029264648"){
        
        $url = "https://codeforyou.com.mx/dev/intermedica/webhook.php";
        $secret_token = TOKEN_C4Y;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Dispatcher-Token: '.$secret_token       // tu token secreto
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("📨 Webhook reenviado a Intermédica ($httpCode): $response");
        
    }else{
        $whats = new whats();
        if (isset($json["entry"][0]["changes"][0]["value"]["statuses"])) {
            $whats->procesarWebhookStatus(["data" => $raw_body]);
        }
    
        if (isset($json["entry"][0]["changes"][0]["value"]["messages"])) {
            $whats->procesarWebhookWhatsApp(["data" => $raw_body]);
        }
    }


    http_response_code(200);
    echo "OK";
}

?>