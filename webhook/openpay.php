<?php
include_once("../clases/class_openpay.php");
$openpay = new openpay();
$response = $openpay->procesarWebhook();

// Opcional: log para depuración
error_log("Webhook OpenPay: ".print_r($response, true));

// OpenPay solo necesita 200 OK
http_response_code(200);
echo "OK";