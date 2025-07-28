<?php 
include_once 'utilidades.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class openAI extends utilidades {

    private $client;
    private $headers;
   
    public function __construct() {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client();
        $this->headers = [
           'Authorization' => 'Bearer ' .OPENAI_TEST_KEY,
            'Content-Type'  => 'application/json'
        ];
        if(!$this->sesion){
            return ["ERR", "No se ha iniciado sesión correctamente."];
        }
        
    }

    private function request($method, $endpoint, $body = []) {
        $codigo = "OK";
        $data = [];

        try {
            $options = [];
            $options['headers'] = $this->headers;

            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $data = json_decode($response->getBody(), true);
            

        } catch (RequestException $e) {
            return $this->ErrorOpenai($e);
        }

        return [$codigo, $data];
    }

    public function ErrorOpenai($e){
        $codigo = "ERR";
        $mensaje = "Ocurrió un error al consultar la API de OpenAI";
        $errorOpenai = [];

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            error_log("Error OpenAI API: " . print_r($body, true));

            $mensaje = $body['error']['message'] ?? $mensaje;
            $errorOpenai = $body['error'] ?? $body;
        }

        return [
            $codigo,
            [
                "mensaje_error" => $mensaje,
                "error_openai" => $errorOpenai
            ]
        ];
    }

    public function interpretarConChatGPT($params = null) {
        
        $mensaje = isset($params["mensaje"]) ? $this->cleanQuery($params["mensaje"]) : "Quiero cortarme el paquete amazonas con ale mañana a las 6";

        $promptBase = file_get_contents((__DIR__ . '/../prompts/barberia.txt'));
        $prompt = str_replace("{{texto_usuario}}", $mensaje, $promptBase);


        $url = "https://api.openai.com/v1/chat/completions";
        list($codigoApi, $response) = $this->request('POST', $url, [
            "model" => "gpt-4o",
            "messages" => [
                [
                    "role" => "system",
                    "content" => "Responde únicamente con el JSON solicitado, sin explicación adicional."
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "temperature" => 0.3
        ]);
        return[$codigoApi,$response];

       
    }
}
