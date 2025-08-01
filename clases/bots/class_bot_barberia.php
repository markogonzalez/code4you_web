<?php 

    include_once __DIR__ . '/../clases/class_whats.php';

    class bot_barberia{

        private $whats;
        private $datos_cliente;
        private $interpretacion;
        
        public function __construct($datos_cliente,$interpretacion) {
            @$this->whats = new whats();
            $this->datos_cliente = $datos_cliente;
            $this->interpretacion = $interpretacion;
        } //function __construct

        public function despachar() {
            $intencion = $this->interpretacion['intencion'];

            $handlers = [
                'saludo' => fn() => $this->intencionSaludo(),
                'agendar_cita' => fn() => $this->intencionAgendarCita(),
                'conocer_servicios' => fn() => $this->intencionServicios(),
                'comprar_producto' => fn() => $this->intencionCompra(),
                'cancelar_cita' => fn() => $this->intencionCancelar(),
                'realizar_pago' => fn() => $this->intencionPago(),
                'otra' => fn() => $this->intencionGenerica()
            ];

            ($handlers[$intencion] ?? $handlers['otra'])();
        }

        private function intencionSaludo($params = null) {

            if($this->interpretacion['intencion']=="saludo"){
                $mensaje = $this->whats->enviarRespuesta([
                    "destinatario" => $this->datos_cliente['numero_whats'],
                    "tipo" => "text",
                    "mensaje" => $this->interpretacion['respuesta'],
                    "id_whats" => $this->datos_cliente['negocio']["id_whats"]
                ]);
                if($mensaje[0]=="OK"){
                    $this->guardarRespuestaWhats($this->datos_cliente['id_cliente'],$this->interpretacion['respuesta'],1);
                }
            }
        }

        private function intencionAgendarCita() {
            $vars = $this->interpretacion['variables'];
            $faltantes = [];

            // Validamos solo los esenciales
            if (empty($vars['servicio'])) $faltantes[] = "servicio";
            if (empty($vars['fecha'])) $faltantes[] = "fecha";
            if (empty($vars['hora'])) $faltantes[] = "hora";

            if (count($faltantes)) {
                $mensaje = "Para agendar tu cita necesito: " . implode(", ", $faltantes) . ". ¿Podrías indicarlo?";
                $this->whats->enviarRespuesta([
                    "destinatario" => $this->datos_cliente['numero_whats'],
                    "tipo" => "text",
                    "mensaje" => $mensaje,
                    "id_whats" => $this->datos_cliente['negocio']["id_whats"]
                ]);
                return;
            }

            // Si NO especificó barbero, preguntamos si desea uno o le asignamos
            if (empty($vars['barbero'])) {
                $mensaje = "¿Tienes algún barbero de preferencia o quieres que asignemos al primero disponible en ese horario?";
                $this->guardarEstadoConversacion([
                    "cliente_id" => $this->datos_cliente['id_cliente'],
                    "espera" => "barbero_preferencia",
                    "contexto" => json_encode($vars)
                ]);
                $this->whats->enviarRespuesta([
                    "destinatario" => $this->datos_cliente['numero_whats'],
                    "tipo" => "text",
                    "mensaje" => $mensaje,
                    "id_whats" => $this->datos_cliente['negocio']["id_whats"]
                ]);
                return;
            }

            // Si todo está listo, validar disponibilidad
            list($disponible, $motivo) = $this->validarDisponibilidad([
                "barbero" => $vars['barbero'],
                "fecha" => $vars['fecha'],
                "hora" => $vars['hora'],
                "duracion" => 60
            ]);

            if (!$disponible) {
                $this->whats->enviarRespuesta([
                    "destinatario" => $this->datos_cliente['numero_whats'],
                    "tipo" => "text",
                    "mensaje" => "No hay disponibilidad en ese horario. ¿Deseas que te proponga otro?",
                    "id_whats" => $this->datos_cliente['negocio']["id_whats"]
                ]);
                return;
            }

            // Confirmación
            $resumen = "Te agendaría para *{$vars['servicio']}* el *{$vars['fecha']}* a las *{$vars['hora']}* con *{$vars['barbero']}*. ¿Deseas confirmar?";
            $this->whats->enviarRespuesta([
                "destinatario" => $this->datos_cliente['numero_whats'],
                "tipo" => "text",
                "mensaje" => $resumen,
                "id_whats" => $this->datos_cliente['negocio']["id_whats"]
            ]);
        }

        public function actualizarPerfil($params = null) {
            $codigo = "OK";
            $data = [];

            // Sanitización de parámetros
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : 0;
            $id_whats = isset($params["id_whats"]) ? $this->cleanQuery($params["id_whats"]) : "";
            $foto_perfil = isset($params["foto_perfil"]) ? $this->cleanQuery($params["foto_perfil"]) : "";
            $nombre = isset($params["nombre"]) ? $this->cleanQuery($params["nombre"]) : "";
            $about = isset($params["about"]) ? $this->cleanQuery($params["about"]) : "";
            $address = isset($params["address"]) ? $this->cleanQuery($params["address"]) : "";
            $description = isset($params["description"]) ? $this->cleanQuery($params["description"]) : "";
            $email = isset($params["email"]) ? $this->cleanQuery($params["email"]) : "";
            $website = isset($params["website"]) ? $this->cleanQuery($params["website"]) : "";
            $fotoBase64 =  isset($params["foto"]) ? $this->cleanQuery($params["foto"]) : "";
            
            if ($fotoBase64 != "") {
                $fotoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fotoBase64));
            
                // Crear nombre único del archivo
                $nombreArchivo = 'perfil_' . $this->sesion['id_usuario'] . '_' . time() . '.jpg';
            
                // Ruta del directorio de perfiles
                $directorio = __DIR__ . '/../uploads/perfiles/';
            
                // Verificar si el directorio existe, si no, crearlo
                if (!is_dir($directorio)) {
                    if (!mkdir($directorio, 0755, true)) {
                        return ["ERR", ["mensaje_error" => "No se pudo crear el directorio de destino"]];
                    }
                }
            
                // Ruta completa del archivo 
                $ruta = $directorio . $nombreArchivo;
            
                // Guardar la imagen
                if (file_put_contents($ruta, $fotoData)) {
                    list($codigo, $response) = $this->whats->enviarRespuesta([
                        "id_whats" => WHATS_PHONE_ID,
                        "destinatario" => DESTINATARIO_CODE4YOU,
                        "tipo" => "template",
                        "template" => "app_cambio_imagen",
                        "variables" => [$nombre, $nombreArchivo],
                    ]);
                    if ($codigo != "OK") {
                        return ["ERR", ["mensaje_error" => "Error al subir foto de perfil"]];
                    }
                    $foto_perfil = $nombreArchivo;
                } else {
                    return ["ERR", ["mensaje_error" => "Error al guardar la imagen en el servidor"]];
                }
            }

            list($codigoApi, $response) = $this->whats->actualizarPerfil([
                "messaging_product" => "whatsapp",
                "id_whats" => $id_whats,
                "about" => $about,
                "description" => $description,
                "address" => $address,
                "email" => $email,
                "website" => $website,
            ]);

            if ($codigoApi !== "OK") {
                return ["ERR", $response];
            }

            $qry_update = "
                UPDATE cliente_negocio SET
                description = '".$description."',
                address = '".$address."',
                email = '".$email."',
                website = '".$website."',
                foto_perfil = '".$foto_perfil."'
                WHERE id_negocio = ".$id_negocio."
            ";

            $this->query($qry_update);

            $data = [
                "mensaje" => "Perfil actualizado correctamente.",
                "meta_response" => $response
            ];

            return [$codigo, $data];
        }

    }
?>