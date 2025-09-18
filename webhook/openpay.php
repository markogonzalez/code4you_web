<?php
// Guarda todo lo que llegue en un archivo
$input = file_get_contents("php://input");
file_put_contents(__DIR__."/log_webhook.txt", date("Y-m-d H:i:s")." ".$input.PHP_EOL, FILE_APPEND);

// Intenta parsear
$data = json_decode($input, true);

if (isset($data['verification_code'])) {
    // Muestra el código en pantalla (para copiarlo en OpenPay)
    echo "Código de verificación: ".$data['verification_code'];
} else {
    // Muestra el payload para otros eventos
    echo "Evento recibido: ".print_r($data, true);
}

// Responde 200 siempre
http_response_code(200);