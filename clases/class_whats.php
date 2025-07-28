<?php 
include_once 'utilidades.php';
include_once 'class_openAI.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
        $numero_negocio = $data['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'] ?? '';
        $negocio = $this->getNegocioUsuario(["numero_negocio"=>$numero_negocio]); // debes crear esta función

        // Datos del cliente que escribió
        $nombre = $data['entry'][0]['changes'][0]['value']['contacts'][0]["profile"]["name"] ?? '';
        $numero = $mensaje['from'];
        $texto = strtolower(trim($mensaje['text']['body'] ?? ''));

        // Buscar o crear cliente
        $datos_cliente = $this->obtenerOInsertarCliente([
            "numero" => $numero,
            "nombre" => $nombre, 
            "texto" => $texto,
            "negocio" =>$negocio
        ]);
        $estado = $datos_cliente['estado'];
        $id_cliente = $datos_cliente['id_cliente'];

        if ($mensaje['type'] === 'interactive' && $mensaje['interactive']['type'] === 'nfm_reply') {
            $this->guardarFlujo($datos_cliente, $mensaje);
            $estado = "Ejecutivo";
        }

        // 3. Consultar ChatGPT para interpretar el mensaje
        $interpretacion = $this->openAI->interpretarConChatGPT(["mensaje" => $texto]);
        error_log("Respuesta de la interpretacion de chatGPT en el webhook de Whatsapp");
        error_log(print_r($interpretacion,true));
        // if (!$interpretacion) return;

        // $json = json_decode($interpretacion, true);
        // $intencion = $json['intencion'] ?? 'otra';
        // $respuesta = $json['respuesta'] ?? '';

        // // 4. Si es conocer_servicios o no entendió nada, respondemos con servicios
        // if ($intencion === 'conocer_servicios' || $intencion === 'otra') {
        //     $this->enviarServiciosDisponibles($numero, $negocio['id_negocio']);
        //     return;
        // }

        // // 5. Si hay respuesta del modelo, mándala
        // if ($respuesta) {
        //     $this->enviarMensajeWhatsApp($numero, $respuesta);
        // }




        // $this->despacharEstado($estado, $texto, $datos_cliente);
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











    

    

    private function despacharEstado($status, $texto, $datos_cliente) {
        $handlers = [
            "Bienvenida" => fn() => $this->mensajeBienvenida($datos_cliente),
            "SegmentoServicio" => fn() => $this->handleSegmentoServicio($texto, $datos_cliente),
            // Flujo para las respuestas de opcion 1 "Desarrollo web"
            "WebSegmento" => fn() => $this->handleWebSegmento($texto, $datos_cliente),
            // Flujo para las respuestas de opcion 2 "Apps moviles"
            "AppSegmento" => fn() => $this->handleAppSegmento($texto, $datos_cliente),
            // Flujo para las respuestas de opcion 3 "Sistemas a la medida"
            "SistemasSegmento" => fn() => $this->handleSistemasSegmento($texto, $datos_cliente),
            "EsperaFlujo" => fn() => $this->handleEsperaFlujo($datos_cliente),
            "Ejecutivo" => fn() => $this->handleEsperaEjecutivo($datos_cliente),
        ];

        ($handlers[$status] ?? fn() => $this->mensajeDefault($datos_cliente))();
    }

    private function handleSegmentoServicio($texto, $datos_cliente) {
        if ($texto === "1") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_desarrollo_web"
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Gracias por tu interés en desarrollo web...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "WebSegmento", "");
            }
        }elseif ($texto === "2") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_apps"
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Gracias por tu interés en Apps moviles...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "AppSegmento", "");
            }
        }elseif ($texto === "3") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_sistemas"
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Gracias por tu interés en Sistemas a la medida...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "SistemasSegmento", "");
            }
            // Modificar logica posteriormente
        }elseif ($texto === "4") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_status_sin_proyectos"
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Gracias por tu interés en Sistemas a la medida...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'],"Bienvenida","");
            }
        }elseif ($texto === "5") {
            $mensaje = $this->mensajeAsesor($datos_cliente);
        } else {
            $this->mensajeDefault($datos_cliente);
        }
    }

    private function handleWebSegmento($texto, $datos_cliente) {
        if ($texto == "1") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_web_informativo",
                "botones" => [["sub_type" => "flow", "param" => "629273403488025"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Sitio web informativo...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_web_informativo");
            }
        }elseif ($texto == "2") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_web_tienda",
                "botones" => [["sub_type" => "flow", "param" => "570647869417858"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Tienda en línea (eCommerce)...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_web_tienda");
            }
        }elseif ($texto == "3") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_web_landing",
                "botones" => [["sub_type" => "flow", "param" => "9986606708088477"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Landing Page...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_web_landing");
            }
        }elseif ($texto == "4") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_web_otro",
                "botones" => [["sub_type" => "flow", "param" => "1440786557148814"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Otro...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_web_otro");
            }
        } else {
            $this->mensajeDefault($datos_cliente);
        }
    }

    private function handleAppSegmento($texto, $datos_cliente) {
        if ($texto == "1") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_apps_clientes",
                "botones" => [["sub_type" => "flow", "param" => "689462217385677"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección App para clientes...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_apps_clientes");
            }
        }elseif ($texto == "2") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_apps_interno",
                "botones" => [["sub_type" => "flow", "param" => "2214843195617352"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección App para uso interno...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_apps_interno");
            }
        }elseif ($texto == "3") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_apps_asesor",
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Asesor para la app...", 1);
                $this->mensajeAsesor($datos_cliente);
            }
        }else {
            $this->mensajeDefault($datos_cliente);
        }
    }

    private function handleSistemasSegmento($texto, $datos_cliente) {
        if ($texto == "1") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_sistemas_administrativo",
                "botones" => [["sub_type" => "flow", "param" => "3413432628799701"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Sistema a la medida administrativo...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_sistemas_administrativo");
            }
        }elseif ($texto == "2") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_sistemas_clientes",
                "botones" => [["sub_type" => "flow", "param" => "1222745339583824"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Sistema a la medida clientes...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_sistemas_clientes");
            }
        }elseif ($texto == "3") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_sistemas_registro",
                "botones" => [["sub_type" => "flow", "param" => "1238860304300262"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección Sistema a la medida registro...", 1);
                $this->actualizarEstado($datos_cliente['cliente_id'], "EsperaFlujo", "c4y_sistemas_registro");
            }
        }elseif ($texto == "4") {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_sistemas_otro_",
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "Excelente elección sistema otro...", 1);
                $this->mensajeAsesor($datos_cliente);
            }
        }else {
            $this->mensajeDefault($datos_cliente);
        }
    }

    private function handleEsperaFlujo($datos_cliente) {
        if ($datos_cliente['espera_flujo'] === 'c4y_web_informativo') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_web_informativo",
                "botones" => [["sub_type" => "flow", "param" => "629273403488025"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_web_tienda') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_web_tienda",
                "botones" => [["sub_type" => "flow", "param" => "570647869417858"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_web_landing') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_web_landing",
                "botones" => [["sub_type" => "flow", "param" => "9986606708088477"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_web_otro') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_web_otro",
                "botones" => [["sub_type" => "flow", "param" => "1440786557148814"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_apps_clientes') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_apps_clientes",
                "botones" => [["sub_type" => "flow", "param" => "689462217385677"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_apps_interno') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_apps_interno",
                "botones" => [["sub_type" => "flow", "param" => "2214843195617352"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_sistemas_administrativo') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_sistemas_administrativo",
                "botones" => [["sub_type" => "flow", "param" => "3413432628799701"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_sistemas_clientes') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_sistemas_clientes",
                "botones" => [["sub_type" => "flow", "param" => "1222745339583824"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        }elseif ($datos_cliente['espera_flujo'] === 'c4y_sistemas_registro') {
            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_flujo_sistemas_registro",
                "botones" => [["sub_type" => "flow", "param" => "742698484986789"]]
            ]);
            if ($mensaje[0] == "OK") {
                $this->guardarRespuesta($datos_cliente['cliente_id'], "📄 Estamos esperando tu respuesta...", 1);
            }
        } else {
            $this->mensajeBienvenida($datos_cliente);
        }
    }
    private function handleEsperaEjecutivo($datos_cliente) {
        
    }

    public function guardarFlujo($datos_cliente,$mensaje) {
        try {
            if($mensaje['type'] !== 'interactive' || $mensaje['interactive']['type'] !== 'nfm_reply' || !isset($mensaje['interactive']['nfm_reply']['response_json'])) {
                error_log("No es un mensaje de flujo interactivo válido.");
                return false;
            }

            $response_raw = $mensaje['interactive']['nfm_reply']['response_json'];
            $flow_data = json_decode($response_raw, true);

            $sql = "INSERT INTO respuestas_flujos (cliente_id, flujo_json) VALUES (
                        '".$this->cleanQuery($datos_cliente['cliente_id'])."',
                        '".$this->cleanQuery($response_raw)."'
                    )";
            $this->query($sql);
            $respuesta_id = $this->conexMySQL->insert_id;

            foreach ($flow_data as $clave => $valor) {
                if (is_array($valor)) {
                    $valor = implode(', ', $valor);
                }
                $campo = $this->cleanQuery($clave);
                $valor = $this->cleanQuery($valor);

                $sqlCampo = "INSERT INTO respuestas_campos_flujos (id_respuesta, campo, valor) VALUES (
                                $respuesta_id, '$campo', '$valor'
                            )";
                $this->query($sqlCampo);
            }


            $mensaje = $this->enviarRespuesta([
                "numero" => $datos_cliente['numero_whats'],
                "tipo" => "template",
                "template" => "c4y_gracias_informacion"
            ]);
            if($mensaje[0]=="OK"){
                $mensaje = $this->enviarRespuesta([
                    "numero" => "5215515030452",
                    "tipo" => "text",
                    "mensaje" => "*".$datos_cliente['nombre_whats']."* / *".$datos_cliente['numero_whats']."* respondio algún flujo",
                ]);
                $this->actualizarEstado($datos_cliente['cliente_id'], "Ejecutivo","");
            }

        } catch (Exception $e) {
            error_log("Error al guardar flujo dinámico: " . $e->getMessage());
            return false;
        }
    }

    public function mensajeBienvenida($datos_cliente){
        $mensaje = $this->enviarRespuesta([
            "numero" => $datos_cliente['numero_whats'],
            "tipo" => "template",
            "template" => "c4y_bienvenida",
            "variables" => [$datos_cliente['nombre_whats']]
        ]);
        if($mensaje[0]=="OK"){
            $this->guardarRespuesta($datos_cliente['cliente_id'],"Gracias por contactar a Code4You ...",1);
            $this->actualizarEstado($datos_cliente['cliente_id'], "SegmentoServicio","");
        }
    }

    public function mensajeAsesor($datos_cliente){
        $mensaje = $this->enviarRespuesta([
            "numero" => "5215515030452",
            "tipo" => "text",
            "mensaje" => "El posible cliente *".$datos_cliente['nombre_whats']."* con número de celular *".$datos_cliente['numero_whats']."* esta solicitando ayuda",
        ]);

        $mensaje2 = $this->enviarRespuesta([
            "numero" => $datos_cliente['numero_whats'],
            "tipo" => "template",
            "template" => "c4y_asesor",
        ]);
        if($mensaje[0]=="OK" && $mensaje2[0]=="OK"){
            $this->guardarRespuesta($datos_cliente['cliente_id'],"Transferido con un asesor ...",1);
            $this->actualizarEstado($datos_cliente['cliente_id'], "Ejecutivo","");
        }
    }

    public function mensajeDefault($datos_cliente){
        $mensaje = $this->enviarRespuesta([
            "numero" => $datos_cliente['numero_whats'],
            "tipo" => "template",
            "template" => "c4y_default"
        ]);
        if($mensaje[0]=="OK"){
            $this->guardarRespuesta($datos_cliente['cliente_id'],"💡 Ups, parece que tu respuesta no coincide con lo que te pedimos ... ",1);
        }
    }

    public function actualizarEstado($cliente_id, $status,$espera_flujo) {

        $qry_update = "UPDATE master_posibles_clientes SET status = '".$status."', espera_flujo = '".$espera_flujo."' WHERE cliente_id = ".$cliente_id;

        try {
            $this->query($qry_update);
        } catch (Exception $e) {
            error_log("Error al actualizar el estado: " . $e->getMessage());
        }
    }

    public function guardarRespuesta($cliente_id, $texto, $tipo_mensaje) {
        $qry_insert = "INSERT INTO conversaciones_whats (mensaje, cliente_id,tipo_mensaje) VALUES ('".$texto."', ".$cliente_id.", ".$tipo_mensaje.")";
        try {
            $this->query($qry_insert);
        } catch (Exception $e) {
            error_log("Error al guardar la respuesta: " . $e->getMessage());
        }
    }
    
    public function normalizarNumeroWhatsapp($numero_raw) {
        
        $numero = preg_replace('/[^0-9]/', '', $numero_raw);
        if (strpos($numero, '52') === 0 && strlen($numero) > 12) {
            $numero = substr($numero, 0, 2) . substr($numero, 3);
        }
        if (strlen($numero) === 10) {
            $numero = "521" . $numero;
        }
        if (strlen($numero) < 12 || strlen($numero) > 13) {
            error_log("Número inválido para WhatsApp API: " . $numero_raw);
            return false;
        }
        return $numero;
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
        $negocio = isset($params["negocio"]) ? $this->cleanQuery($params["negocio"]) : [];

        
        $query = "SELECT activo, id_cliente, espera_flujo,nombre_whats,numero_whats,estado FROM negocio_clientes WHERE numero_whats = '".$numero."' AND id_negocio =".$negocio['id_negocio'];
        $res = $this->query($query);

        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $this->guardarRespuesta($data['id_cliente'], $texto, 2);
            return $data;
        }

        $estado = "Inicio";
        $this->query("INSERT INTO negocio_clientes (numero_whats, nombre_whats, estado,id_negocio) VALUES ('".$numero."', '".$nombre."', '".$estado."',".$negocio['id_negocio'].")");
        $id_cliente = $this->conexMySQL->insert_id;
        $this->guardarRespuesta($id_cliente, $texto, 2);

        return ['estado' => $estado, 'id_cliente' => $id_cliente, 'espera_flujo' => null,"nombre_whats"=>$nombre,"numero_whats"=>$numero];
    }

}
?>
