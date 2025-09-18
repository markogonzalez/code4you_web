<?php
include_once 'class_whats.php';

class openpay extends utilidades{

    public function __construct() {
        parent::__construct();
        
    } //function __construct

    public function procesarWebhook($params = null){
        $codigo  = "OK";
        $mensaje = "";

        try {
            $input = @file_get_contents("php://input");
            $event = json_decode($input, true);

            if (!$event) {
                $codigo  = "ERR";
                $mensaje = "Payload inválido";
            } 
            // 🔹 1. Caso verificación inicial
            elseif (isset($event['verification_code'])) {
                $verification_code = $event['verification_code'];

                // Guardar en logs
                $this->query("INSERT INTO openpay_webhooks (evento, referencia, payload, observaciones) 
                              VALUES ('verificacion',
                                      '".$this->cleanQuery($verification_code)."',
                                      '".$this->cleanQuery(json_encode($event))."',
                                      'Código de verificación recibido')");

                $mensaje = "Código de verificación recibido: ".$verification_code;

                // 👀 Mostrar en pantalla para copiarlo directo
                echo $mensaje;
                http_response_code(200);
                exit;
            } 
            // 🔹 2. Eventos normales
            else {
                $tipo_evento = $event['type'] ?? '';
                $data        = $event['transaction'] ?? [];

                $id_cargo = $data['id'] ?? null;

                // Guardar en logs
                $this->query("INSERT INTO openpay_webhooks (evento, referencia, payload) 
                              VALUES ('".$this->cleanQuery($tipo_evento)."',
                                      '".$this->cleanQuery($id_cargo)."',
                                      '".$this->cleanQuery(json_encode($event))."')");

                // Procesar cargos como antes (succeeded, failed, etc.)
                if ($id_cargo && $tipo_evento) {
                    switch ($tipo_evento) {
                        case "charge.succeeded":
                            $this->query("UPDATE master_pagos SET estatus='pagado' WHERE referencia='$id_cargo'");
                            $mensaje = "Cargo $id_cargo confirmado como pagado.";
                            break;
                        case "charge.failed":
                            $this->query("UPDATE master_pagos SET estatus='rechazado' WHERE referencia='$id_cargo'");
                            $mensaje = "Cargo $id_cargo rechazado.";
                            break;
                        case "charge.cancelled":
                            $this->query("UPDATE master_pagos SET estatus='cancelado' WHERE referencia='$id_cargo'");
                            $mensaje = "Cargo $id_cargo cancelado.";
                            break;
                        case "charge.refunded":
                            $this->query("UPDATE master_pagos SET estatus='reembolsado' WHERE referencia='$id_cargo'");
                            $mensaje = "Cargo $id_cargo reembolsado.";
                            break;
                        default:
                            $mensaje = "Evento $tipo_evento recibido y logueado.";
                            break;
                    }
                }
            }

        } catch (Exception $e) {
            $codigo  = "ERR";
            $mensaje = "Error inesperado: ".$e->getMessage();
        }

        http_response_code(200);

        return [$codigo, ["mensaje"=>$mensaje]];
    }

}