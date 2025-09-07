<?php
require_once "vendor/autoload.php"; // Asegúrate de que JWT esté disponible

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class autenticacion {

    private static $secret = TOKEN_C4Y;

    public static function validar() {
        $headers = getallheaders();
        $token = isset($_GET['token']) ? $_GET['token'] :
                 (isset($_POST['token']) ? $_POST['token'] :
                 (isset($headers['Authorization']) ? str_replace("Bearer ", "", $headers['Authorization']) : null));

        if (!$token) {
            return ['code' => 'ERROR', 'mensaje' => 'Token no proporcionado'];
        }

        try {
            $decoded = JWT::decode($token, new Key(self::$secret, 'HS256'));
            return ['code' => 'OK', 'usuario' => (array) $decoded];
        } catch (Exception $e) {
            return ['code' => 'ERROR', 'mensaje' => 'Token inválido: ' . $e->getMessage()];
        }
    }

}