<?php
include_once '../clases/class_whats.php';
include_once '../clases/utilidades.php';

class WebhookOpenPay extends utilidades{

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
            } else {
                $tipo_evento = $event['type'] ?? '';
                $data        = $event['transaction'] ?? [];

                $id_cargo = $data['id'] ?? null;
                $estatus  = $data['status'] ?? null;
                $monto    = $data['amount'] ?? 0;

                // Guardar siempre en logs
                $this->query("INSERT INTO openpay_webhooks (evento, referencia, payload) 
                              VALUES ('".$this->cleanQuery($tipo_evento)."',
                                      '".$this->cleanQuery($id_cargo)."',
                                      '".$this->cleanQuery(json_encode($event))."')");

                if (!$id_cargo) {
                    $codigo  = "ERR";
                    $mensaje = "No se recibió transaction_id";
                } else {
                    // Validar contra OpenPay
                    try {
                        $openpay  = $this->openpay();
                        $charge   = $openpay->charges->get($id_cargo);

                        if ($charge->id !== $id_cargo) {
                            $codigo  = "ERR";
                            $mensaje = "El cargo no coincide en OpenPay";
                        }
                    } catch (Exception $e) {
                        $codigo  = "ERR";
                        $mensaje = "Error validando origen: ".$e->getMessage();
                    }

                    // Procesar evento
                    if ($codigo == "OK") {
                        switch ($tipo_evento) {
                            case "charge.succeeded":
                                $this->query("UPDATE master_pagos 
                                              SET estatus='pagado' 
                                              WHERE referencia='$id_cargo'");
                                $mensaje = "Cargo $id_cargo confirmado como pagado.";
                                break;

                            case "charge.failed":
                                $this->query("UPDATE master_pagos 
                                              SET estatus='rechazado' 
                                              WHERE referencia='$id_cargo'");
                                $mensaje = "Cargo $id_cargo rechazado.";
                                break;

                            case "charge.cancelled":
                                $this->query("UPDATE master_pagos 
                                              SET estatus='cancelado' 
                                              WHERE referencia='$id_cargo'");
                                $mensaje = "Cargo $id_cargo cancelado.";
                                break;

                            case "charge.refunded":
                                $this->query("UPDATE master_pagos 
                                              SET estatus='reembolsado' 
                                              WHERE referencia='$id_cargo'");
                                $mensaje = "Cargo $id_cargo reembolsado.";
                                break;

                            default:
                                $mensaje = "Evento $tipo_evento recibido y logueado.";
                                break;
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $codigo  = "ERR";
            $mensaje = "Error inesperado: ".$e->getMessage();
        }

        // SIEMPRE devolver 200 a OpenPay
        http_response_code(200);

        return [$codigo, ["mensaje"=>$mensaje]];
    }

}