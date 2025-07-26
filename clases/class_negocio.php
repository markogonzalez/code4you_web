<?php 

    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class negocio extends utilidades{

        private $id_modulo = 0;
        private $ruta = "./";
        private $permisos = [];
        private $whats = null;
        
        public function __construct() {
            parent::__construct();
            @$this->whats = new whats();
            
        } //function __construct

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

        public function actualizarHorariosNegocio($params = null){
            
            $dias = isset($params["dias"]) ? $params["dias"] : [];
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : 0;
            
            $this->query("DELETE FROM negocio_horarios WHERE id_negocio = $id_negocio");

            foreach ($dias as $dia => $valores) {
                $activo = isset($valores["activo"]) ? 1 : 0;
                $inicio = isset($valores["inicio"]) ? $this->cleanQuery($valores["inicio"]) : null;
                $fin    = isset($valores["fin"]) ? $this->cleanQuery($valores["fin"]) : null;

                $qry = "INSERT INTO negocio_horarios (id_negocio, dia, activo, hora_inicio, hora_fin)
                        VALUES ($id_negocio, '$dia', $activo, " .
                        ($inicio ? "'$inicio'" : "NULL") . ", " .
                        ($fin ? "'$fin'" : "NULL") . ")";

                if (!$this->query($qry)) {
                    return ["ERR", ["mensaje_error" => "Error al guardar el horario para $dia"]];
                }
            }

            return ["OK", "Horarios del negocio actualizados correctamente"];
            
        }
        
        public function obtenerHorarioFinal($id_trabajador, $id_negocio) {
            $result = [];

            $dias = ['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];

            

            return $result;
        }

        public function getHorariosTrabajador($params=null){
            
            $data = [];
            $id_usuario = $this->cleanQuery($params["id_usuario"] ?? '');
            $id_negocio = $this->cleanQuery($params["id_negocio"] ?? '');
            
            foreach ($this->dias_semana as $dia) {
                $qry = $this->query("SELECT * FROM trabajador_horarios WHERE id_usuario = $id_usuario AND dia = '$dia'");
                $fila = $qry->fetch_assoc();

                if ($fila && $fila['personalizado'] == 1) {
                    $data[$dia] = $fila;
                }else{
                    $qry = $this->query("SELECT * FROM negocio_horarios WHERE id_negocio = $id_negocio AND dia = '$dia'");
                    $data[$dia] = $qry->fetch_assoc();
                }
            }

            return ["OK", $data];

        }

        public function actualizarHorariosTrabajador($params = null){
            
            $id_usuario = $this->cleanQuery($params["id_usuario"] ?? '');
            $dias = isset($params["dias"]) ? $params["dias"] : [];

            foreach ($dias as $dia => $valores) {
                $activo = isset($valores["activo"]) ? 1 : 0;
                $inicio = isset($valores["inicio"]) ? $this->cleanQuery($valores["inicio"]) : null;
                $fin    = isset($valores["fin"]) ? $this->cleanQuery($valores["fin"]) : null;

                $qry = "UPDATE trabajador_horarios SET
                                activo = $activo,
                                hora_inicio = '$inicio',
                                hora_fin = '$fin',
                                personalizado = 1
                              WHERE id_usuario = $id_usuario AND dia = '$dia'";
                if (!$this->query($qry)) {
                    return ["ERR", ["mensaje_error" => "Error al actualizar el horario para $dia"]];
                }
                
            }
            return ["OK", "Horarios actualizados correctamente"];
        }

        public function negocioServicios($params = null){
            
            $codigo = "OK";
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : "";

            $query = "SELECT nc.id_categoria,
                            nc.categoria,
                            ns.servicio,
                            ns.descripcion,
                            ns.duracion,
                            ns.precio,
                            ns.activo,
                            ns.id_servicio
                    FROM negocio_categorias nc 
                    LEFT JOIN negocio_servicios ns ON nc.id_categoria = ns.id_categoria
                    WHERE nc.id_negocio = " . $id_negocio." ORDER BY ns.id_servicio DESC";

            $result = $this->query($query);
            $categorias = [];
            $total_servicios_global = 0;

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id_categoria = $row['id_categoria'];

                    // Inicializar categoría si no existe
                    if (!isset($categorias[$id_categoria])) {
                        $categorias[$id_categoria] = [
                            'id_categoria' => $id_categoria,
                            'categoria' => $row['categoria'],
                            'total_servicios' => 0,
                            'servicios' => []
                        ];
                    }

                    // Agregar servicio si existe
                    if ($row['id_servicio'] != null) {
                        $categorias[$id_categoria]['servicios'][] = [
                            'id_servicio' => $row['id_servicio'],
                            'servicio' => $row['servicio'],
                            'descripcion' => $row['descripcion'],
                            'duracion' => $row['duracion'],
                            'precio' => $row['precio'],
                            'activo' => $row['activo'],
                            'categoria' => $row['categoria'],
                            'id_categoria' => $row['id_categoria'],
                        ];

                        $categorias[$id_categoria]['total_servicios']++;
                        $total_servicios_global++;
                    }
                }

                // Reindexar el array
                $categorias = array_values($categorias);
            }

            return [$codigo, ["categorias"=>$categorias, 'total_servicios' => $total_servicios_global]];
        }

        public function guardarCategoria($params = null){

            $codigo = "OK";
            $mensaje = "";

            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : "";
            $categoria = isset($params["categoria"]) ? $this->cleanQuery($params["categoria"]) : "";

            $categoria_repeat = "SELECT * FROM negocio_categorias WHERE categoria ='".$categoria."' AND id_negocio = ".$id_negocio;
            $res_repeat = $this->query($categoria_repeat);
            if($res_repeat->num_rows == 0){
                $query = "INSERT INTO negocio_categorias (categoria,id_negocio) VALUES ('".$categoria."',".$id_negocio.")";
                try {
                    $this->query($query);
                    $mensaje = "Categoria guardada exitosamente";
                } catch (Exception $e) {
                    $codigo = "ERR";
                    $mensaje = "Error al insertar categoria, intenta de nuevo";
                    error_log("Error al insertar categoria, intenta de nuevo - ".$e);
                }


            }else{
                $codigo = "ERR";
                $mensaje = "La categoria ya se encuentra registrada en el negocio.";
            }

            $data = [
                "mensaje" => $mensaje
            ];

            return [$codigo, $data];

        }

        public function guardarServicio($params = null){

            $codigo = "OK";
            $data = [];

            $id_servicio = isset($params["id_servicio"]) ? $this->cleanQuery($params["id_servicio"]) : 0;
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : 0;
            $id_categoria = isset($params["id_categoria"]) ? $this->cleanQuery($params["id_categoria"]) : 0;
            $servicio = isset($params["servicio"]) ? $this->cleanQuery($params["servicio"]) : "";
            $descripcion = isset($params["descripcion"]) ? $this->cleanQuery($params["descripcion"]) : "";
            $duracion = isset($params["duracion_minutos"]) ? $this->cleanQuery($params["duracion_minutos"]) : 0;
            $precio = isset($params["precio"]) ? $this->cleanQuery($params["precio"]) : 0;
            $activo = isset($params["activo"]) ? 1 : 0;

            if($id_servicio==0){
                $query = "INSERT INTO negocio_servicios (id_categoria,
                    id_negocio,
                    servicio,
                    descripcion,
                    duracion,
                    precio,
                    activo
                    ) VALUES (".$id_categoria.",
                        ".$id_negocio.",
                        '".$servicio."',
                        '".$descripcion."',
                        ".$duracion.",
                        ".$precio.",
                        ".$activo."
                    )";
            }else{
                $query = "UPDATE negocio_servicios SET 
                id_categoria = ".$id_categoria.",
                id_negocio = ".$id_negocio.",
                servicio = '".$servicio."',
                descripcion = '".$descripcion."',
                duracion = ".$duracion.",
                precio = ".$precio.",
                activo = ".$activo."
                WHERE id_servicio = ".$id_servicio;
            }

            try {
                $this->query($query);
                $mensaje = "Servicio guardado exitosamente";
            } catch (Exception $e) {
                $codigo = "ERR";
                $mensaje = "Error al insertar servicio, intenta de nuevo";
                error_log("Error al insertar servicio, intenta de nuevo - ".$e);
            }

            $data = [
                "mensaje" => $mensaje
            ];

            return [$codigo,$data];

        }

    }

?>