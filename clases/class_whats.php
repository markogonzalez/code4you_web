<?php 
include_once 'utilidades.php';
include_once 'class_openAI.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
date_default_timezone_set('America/Mazatlan');

class whats extends utilidades {

    private $client;
    private $headers;
    private $openAI;
   
    public function __construct() {
        parent::__construct();

        $this->client = new \GuzzleHttp\Client();
        $this->headers = [
           'Authorization' => 'Bearer ' .WHATS_TOKEN,
            'Content-Type'  => 'application/json'
        ];
        $this->openAI = new openAI();
        
    }

    public function procesarWebhookWhatsApp($params = null) {
        $data = isset($params["data"]) ? json_decode($params["data"], true) : false;
        if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) return;

        $mensaje = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $mensaje_id = $mensaje['id'] ?? '';
        if ($this->yaFueProcesado($mensaje_id)) return;

        // Obtener número del negocio al que escribieron
        $numero_negocio = substr($data['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'],3) ?? '';
        $negocio = $this->getNegocio(["numero_negocio"=>$numero_negocio]); // debes crear esta función
        // Datos del cliente que escribió
        $nombre = $data['entry'][0]['changes'][0]['value']['contacts'][0]["profile"]["name"] ?? '';
        $numero = $mensaje['from'];
        $texto = strtolower(trim($mensaje['text']['body'] ?? ''));

        // Consultar ChatGPT para interpretar el mensaje
        $interpretacion = $this->openAI->interpretarConChatGPT(["mensaje" => $texto]);
        if ($interpretacion[0]!="OK") return;

        // Buscar o crear cliente
        $datos_cliente = $this->obtenerOInsertarCliente([
            "numero" => $numero,
            "nombre" => $nombre, 
            "texto" => $texto,
            "intencion" => $interpretacion[1]["intencion"],
            "id_negocio" => $negocio[1]['id_negocio']
        ]);

        list($codigo,$id_mensaje) =$this->guardarRespuestaWhats([
            "id_cliente" => $datos_cliente['id_cliente'],
            "id_negocio" => $negocio[1]['id_negocio'],
            "mensaje" => $texto,
            "tipo" => "cliente",
            "modulo_origen" => $negocio[1]['tipo_bot'],
            "tipo_whats" => $mensaje['type'],
            "mensaje_id_externo" => $mensaje_id,
            "respuesta_interactiva" => isset($mensaje['interactive']) ? 1 : 0,
            "metadata" => [
                "numero_contacto" => $numero,
                "raw" => $mensaje // Puedes guardar el payload completo si quieres
            ]
        ]);
        $fechaISO = date("c", strtotime(date("Y-m-d H:i:s")));
        $horas = new DateTime($fechaISO);
        if($codigo=="OK" && $id_mensaje > 0){
            $this->emitirSocket(
                "mensajeRecibido",
                "sala_cliente_".$datos_cliente['id_cliente'],
                [
                    "id_mensaje" => $id_mensaje,
                    "id_cliente" => $datos_cliente['id_cliente'],
                    "id_negocio" => $negocio[1]['id_negocio'],
                    "mensaje" => $texto,
                    "fecha_iso" => $fechaISO,
                    "fecha_mostrar" => $horas->format('H:i'),
                    "tipo" => "cliente",
                    "numero_whats" => $datos_cliente['numero_whats'],
                ]
            );

            if($datos_cliente['atencion']=="bot"){
                $tipo_bot = $negocio[1]['tipo_bot']; // ej. 'barberia'
                $clase_bot = "bot_" . $tipo_bot;     // 'bot_barberia'
                $archivo_clase = __DIR__ . "/bots/class_$clase_bot.php";
        
                if (file_exists($archivo_clase)) {
                    include_once($archivo_clase);
                    if(class_exists($clase_bot)){
                        $bot = new $clase_bot($datos_cliente, $interpretacion[1],$negocio[1]);
                        $bot->despachar();
                    }else{
                        error_log("Clase no encontrada: $clase_bot");
                    }
                }else{
                    error_log("Archivo de clase no encontrado: $archivo_clase");
                }
            }

        }

    }

    public function procesarWebhookStatus($params = []) {
        $data_raw = $params["data"] ?? "";
        $json = json_decode($data_raw, true);
        // error_log("Procesando webhook de estado: " . print_r($json, true));
        $statusData = $json["entry"][0]["changes"][0]["value"]["statuses"] ?? [];
        // Obtener número del negocio al que escribieron
        $numero_negocio = substr($json['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'],3) ?? '';
        $negocio = $this->getNegocio(["numero_negocio"=>$numero_negocio]); // debes crear esta función

        $datos_cliente = $this->obtenerOInsertarCliente([
            "numero" => $statusData[0]['recipient_id'],
            "id_negocio" => $negocio[1]['id_negocio']
        ]);
        $id_mensaje_externo = $statusData[0]["id"] ?? null;
        $qry = "SELECT id_mensaje FROM negocio_chats WHERE mensaje_id_externo = '$id_mensaje_externo'";

        $res = $this->query($qry);
        $row = $res->fetch_assoc();
        $id_mensaje = $row['id_mensaje'] ?? null;

        foreach ($statusData as $status) {

            $status = $status["status"] ?? null;
            $timestamp = $status["timestamp"] ?? null;
            $conversation_id = $status["conversation"]["id"] ?? null;

            $error_code    = $status["errors"][0]["code"] ?? null;
            $error_title   = $status["errors"][0]["title"] ?? null;
            $error_details = $status["errors"][0]["details"] ?? null;

            // Armar detalle si hubo error
            $detalle_estado = null;
            if ($status === 'failed') {
                $detalle_estado = "Código: $error_code. Título: $error_title. Detalle: $error_details";
            }

            // Actualizar en BD
            if($id_mensaje !== null && $status !== null){
                $sql = "UPDATE negocio_chats 
                    SET estado_salida = '$status', 
                        detalle_estado = '$detalle_estado'
                    WHERE mensaje_id_externo = '$id_mensaje_externo'";

                try {
                    $this->query($sql);
                    $this->emitirSocket(
                        "actualizar_estado_mensaje",
                        "sala_cliente_".$datos_cliente['id_cliente'],
                        [
                            "id_mensaje" => $id_mensaje,
                            "id_cliente" => $datos_cliente['id_cliente'],
                            "estado_salida" => $status,
                        ]
                    );
                } catch (Exception $e) {
                    error_log("Error al guardar la respuesta: " . $e->getMessage());
                    $codigo = "ERR";
                }
            }
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
            return $this->ErrorWhats($e);
        }


        return [$codigo, $data];
    }

    private function ErrorWhats($e) {
        $codigo = "ERR";
        $mensaje = "Ocurrió un error inesperado al procesar la solicitud";
        $errorMeta = [];

        if ($e->hasResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            error_log("Error WhatsApp API: " . print_r($body, true));

            $mensaje = $body['error']['error_user_msg'] ?? $mensaje;
            $errorMeta = $body['error'];
        }

        return [
            $codigo,
            [
                "mensaje_error" => $mensaje,
                "error_meta" => $errorMeta
            ]
        ];
    }

    public function enviarRespuesta($params = null) {

        $destinatario = isset($params["destinatario"]) ? $this->normalizarNumeroWhatsapp($params["destinatario"]) : false;
        $mensaje     = isset($params["mensaje"]) ? $params["mensaje"] : "";
        $tipo        = isset($params["tipo"]) ? $params["tipo"] : "";
        $template    = isset($params["template"]) ? $params["template"] : "";
        $variables   = isset($params["variables"]) && is_array($params["variables"]) ? $params["variables"] : [];
        $botones     = isset($params["botones"]) && is_array($params["botones"]) ? $params["botones"] : [];
        $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
        $idioma_plantilla = isset($params["idioma_plantilla"]) ? $this->cleanQuery($params["idioma_plantilla"]) : "es_MX";

        $url = "https://graph.facebook.com/".WHATS_VERSION."/".$id_whats."/messages";
        $data = [
            "messaging_product" => "whatsapp",
            "to" => $destinatario,
        ];

        if($tipo === "text"){

            $data["type"] = "text";
            $data["text"] = ["body" => $mensaje];
        
        }elseif ($tipo === "template"){

            if (empty($template)) {
                $codigo = "ERR";
                return [$codigo, ["mensaje_error" => "El nombre del template está vacío"]];
            }

            $params_array = [];
            foreach ($variables as $valor) {
                $params_array[] = [
                    "type" => "text",
                    "text" => $valor
                ];
            }

            $componentes = [];

            if (!empty($params_array)) {
                $componentes[] = [
                    "type" => "body",
                    "parameters" => $params_array
                ];
            }

            if (!empty($botones)) {
                foreach ($botones as $i => $btn) {
                    if (!isset($btn['sub_type']) || !isset($btn['param'])) continue;

                    $subtype = $btn['sub_type'];
                    $param_value = $btn['param'];
                    $param_type = in_array($subtype, ['quick_reply', 'flow']) ? 'payload' : 'text';

                    $componentes[] = [
                        "type" => "button",
                        "sub_type" => $subtype,
                        "index" => (string) $i,
                        "parameters" => [
                            [
                                "type" => $param_type,
                                $param_type => $param_value
                            ]
                        ]
                    ];
                }
            }

            $data["type"] = "template";
            $data["template"] = [
                "name" => $template,
                "language" => ["code" => $idioma_plantilla],
            ];

            if (!empty($componentes)) {
                $data["template"]["components"] = $componentes;
            }

        }else{
            $codigo = "ERR";
            return [$codigo, ["mensaje_error" => "Tipo no valido"]];
        }

        // Enviar la petición con Guzzle
        list($codigoApi,$response) = $this->request("POST", $url, $data);
        return [$codigoApi,$response];
    }

    public function registrarNumero($params = null) {
        
        $codigo = "OK";
        $data = [];
        
        $nombre_negocio = isset($params["nombre_negocio"]) ? $this->cleanQuery($params["nombre_negocio"]) : "";
        $numero_negocio = isset($params["numero_negocio"]) ? $this->cleanQuery($params["numero_negocio"]) : "";
        $verificacion = isset($params["verificacion"]) ? $this->cleanQuery($params["verificacion"]) : "";
        $id_servicio = isset($params["id_servicio"]) ? $this->cleanQuery($params["id_servicio"]) : "";

        $url = "https://graph.facebook.com/".WHATS_VERSION."/".WABA_ID."/phone_numbers";
        list($codigoApi, $response) = $this->request('POST', $url, [
            "cc" => "52",
            "verified_name" => $nombre_negocio,
            "phone_number" => $numero_negocio
        ]);

        if ($codigoApi !== "OK") {
            return [$codigoApi, $response];
        }

        $id_whats = $response['id'];
        $qry_insert = "INSERT INTO cliente_negocio (
            id_usuario,
            id_whats,
            id_servicio,
            nombre_negocio, 
            numero_negocio,
            status
            ) VALUES (
            ".$this->sesion['id_usuario'].",
            '".$id_whats."',
            ".$id_servicio.",
            '".$nombre_negocio."',
            '".$numero_negocio."',
            1)";
            
        if (!$this->query($qry_insert)) {
            return ["ERR", ["mensaje_error" => "Error al guardar en base de datos."]];
        }

        // Disparar solicitud de código
        list($codigoApi, $responseCodigo) = $this->enviarCodigoVerificacion([
            "id_whats" => $id_whats,
            "verificacion" => $verificacion,
        ]);

        if ($codigoApi !== "OK") {
            return [$codigoApi, $responseCodigo];
        }

        $data = [
            "mensaje" => $codigoApi === "OK" ? "Número registrado exitosamente, en breve recibirá el código de verificación." : "Número registrado, pero no se pudo solicitar el código de verificación.",
        ];
    
        return [$codigo, $data];
            
    }

    public function enviarCodigoVerificacion($params = null) {
        
        $mensaje = "";
        $data = [];
        
        $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
        $verificacion = isset($params["verificacion"]) ? $this->cleanQuery($params["verificacion"]) : "SMS";
        
        $qry_select = "SELECT ultima_verificacion FROM cliente_negocio WHERE id_whats = '$id_whats'";
        $res = $this->query($qry_select);
        $row = $res->fetch_assoc();
        if ($res && strtotime($row['ultima_verificacion']) > strtotime('-10 minutes')) {
            return ["ERR", ["mensaje_error" => "Ya se solicitó un código recientemente. Intenta más tarde."]];
        }

        list($codigo,$response) = $this->intentarCodigo($id_whats, $verificacion);
        if ($codigo === "OK") {
            $this->query("UPDATE cliente_negocio SET ultima_verificacion = NOW(),status = 2 WHERE id_whats = '$id_whats'");
            return [$codigo,$response];
        }

        if ($verificacion === "SMS") {
            list($codigo,$response) = $this->intentarCodigo($id_whats, "VOICE");

            if ($codigo[0] === "OK") {
                $this->query("UPDATE cliente_negocio SET ultima_verificacion = NOW(),status = 2 WHERE id_whats = '$id_whats'");
                return [$codigo, $data];
            }

        }
        
    }

    private function intentarCodigo($id_whats, $metodo = "SMS") {
        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/$id_whats/request_code";

        $body = [
            "code_method" => $metodo,
            "language" => "es_MX"
        ];

        $respuesta = $this->request('POST', $url, $body);
        return $respuesta;
    }

    public function verificarCodigo($params = null) {

        $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
        $codigo_verificacion = isset($params["codigo_verificacion"]) ? $this->cleanQuery($params["codigo_verificacion"]) : "";

        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/{$id_whats}/verify_code";

        list($codigo,$response) = $this->request('POST', $url, [
            "code" => $codigo_verificacion
        ]);

        if ($codigo == "OK") {
            $this->query("UPDATE cliente_negocio SET status = 3 WHERE id_whats = '{$id_whats}'");
        }

        return [$codigo,$response];
    }


    public function actualizarPerfil($params = null){

        $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
        $about = isset($params["about"]) ? $this->cleanQuery($params["about"]) : "";
        $description = isset($params["description"]) ? $this->cleanQuery($params["description"]) : "";
        $address = isset($params["address"]) ? $this->cleanQuery($params["address"]) : "";
        $email = isset($params["email"]) ? $this->cleanQuery($params["email"]) : "";
        $website = isset($params["website"]) ? $this->cleanQuery($params["website"]) : "";

        $url = "https://graph.facebook.com/" . WHATS_VERSION . "/" . $id_whats . "/whatsapp_business_profile";

        list($codigo, $response) = $this->request('POST', $url, [
            "messaging_product" => "whatsapp",
            "description" => $description,
            "address" => $address,
            "email" => $email,
            "website" => $website,
        ]);

        return [$codigo,$response];
        
    }

    private function yaFueProcesado($mensaje_id) {
        $query = "SELECT 1 FROM mensajes_procesados WHERE mensaje_id = '$mensaje_id'";
        $res = $this->query($query);
        if ($res->num_rows > 0) return true;

        $this->query("INSERT INTO mensajes_procesados (mensaje_id) VALUES ('$mensaje_id')");
        return false;
    }

    private function obtenerOInsertarCliente($params = null) {

        $numero = isset($params["numero"]) ? $this->cleanQuery($params["numero"]) : "";
        $nombre = isset($params["nombre"]) ? $this->cleanQuery($params["nombre"]) : "";
        $texto = isset($params["texto"]) ? $this->cleanQuery($params["texto"]) : "";
        $intencion = $params["intencion"];
        $id_negocio = $params["id_negocio"];
                
        $query = "SELECT activo, id_cliente, espera_flujo,nombre_whats,numero_whats,intencion,atencion FROM negocio_clientes WHERE numero_whats = '".$numero."' AND id_negocio =".$id_negocio;
        $res = $this->query($query);

        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $this->actualizarIntencionWhats([
                "id_cliente" => $data['id_cliente'],
                "intencion" => $intencion,
                "espera_flujo" => ""
            ]);

            return $data;
        }

        $qry_insert = "INSERT INTO negocio_clientes (numero_whats, nombre_whats, intencion ,id_negocio) VALUES ('".$numero."', '".$nombre."', '".$intencion."',".$id_negocio.")";
        $this->query($qry_insert);
        $id_cliente = $this->conexMySQL->insert_id;
        return ['intencion' => $intencion, 'id_cliente' => $id_cliente, 'espera_flujo' => null,"nombre_whats"=>$nombre,"numero_whats"=>$numero,"atencion"=>"bot"];
    }

    function emitirSocket($evento, $sala, $data, $namespace = "code4you") {
        error_log("🧪 Emitiendo evento: $evento a la sala $sala con datos: " . json_encode($data));
        $url = "https://becd44a30a00.ngrok-free.app/api"; // O IP real si estás en producción

        $payload = json_encode([
            "evento" => $evento,
            "datos" => $data,
            "sala" => $sala,
            "namespace" => $namespace
        ]);
        $tokenJWT = $this->generarToken([
            "id_cliente" => $data['id_cliente'],
            "pefil_id" => 4 // Perfil cliente
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Content-Length: " . strlen($payload),
            "Authorization: Bearer $tokenJWT",
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        error_log($response);
        curl_close($ch);

        if ($error) {
            error_log("❌ Error al emitir socket: " . $error);
        } else {
            error_log("✅ Socket emitido: " . $response);
        }
    }

}
?>
