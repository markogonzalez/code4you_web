<?php 
include_once 'utilidades.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class wppconnect extends utilidades {
    private $base_url = 'http://wppconnect:21465/api/';
    private $tokenWPPConnect;
    private $client;
    private $nombre_sesion;

    public function __construct() {
        parent::__construct();

        if (!$this->sesion) {
            error_log("No hay sesión activa para WPPConnect");
            throw new Exception("Sesión no válida");
        }

        $this->nombre_sesion = "cliente_cod4you_" . $this->sesion['id_usuario'];
        $this->tokenWPPConnect = $this->obtenerTokenHttp();

        // El cliente se inicializa sin headers, se agregan dinámicamente según el método
        $this->client = new Client([
            'base_uri' => $this->base_url,
            'timeout'  => 10.0,
        ]);
    }

    private function obtenerTokenHttp() {
        $headers = getallheaders();
        return $headers['Authorization'] ?? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    }

    private function request($method, $endpoint, $body = [], $usar_token = true) {
        try {
            $options = [];

            if ($usar_token && $this->tokenWPPConnect) {
                $options['headers'] = [
                    'Authorization' => 'Bearer ' . $this->tokenWPPConnect,
                    'Accept'        => 'application/json',
                ];
            }

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->client->request($method, $endpoint, $options);
            return [true, json_decode($response->getBody(), true)];

        } catch (RequestException $e) {
            $error = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            error_log("Error en WPPConnect: $error");
            return [false, $error];
        }
    }

    public function generarTokenWppConnect() {
        $url = "{$this->nombre_sesion}/" . rawurlencode(API_WPPCONNECT) . "/generate-token";
        return $this->request('POST', $url, [], false);
    }

    public function iniciarSesion() {
        $url = "{$this->nombre_sesion}/start-session";
        return $this->request('POST', $url, ["webhook" => "", "waitQrCode" => true]);
    }

    public function getQR() {
        $url = "{$this->nombre_sesion}/qrcode-session";
        return $this->request('GET', $url);
    }

    public function infoSesion() {
        $url = "{$this->nombre_sesion}/session-info";
        return $this->request('GET', $url);
    }

    public function verificarEstado() {
        $url = "{$this->nombre_sesion}/status-session";
        return $this->request('GET', $url);
    }
}