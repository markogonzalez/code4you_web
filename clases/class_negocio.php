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

        public function setConfiguracionNegocio($params = null){
            $negocioConfiguracion = null;
            $codigo = "OK";
            $id_negocio    = $this->cleanQuery($params["id_negocio"] ?? 0);
            // 1. Info general del negocio
            $sqlNegocio = "SELECT 
                            cn.*,
                            CASE 
                                WHEN cn.id_servicio = 1 THEN 'barberia'
                                ELSE 'code4you'
                            END AS tipo_bot
                        FROM cliente_negocio cn
                        WHERE cn.id_negocio = $id_negocio";

            $resNegocio = $this->query($sqlNegocio);
            if (!$resNegocio || $resNegocio->num_rows == 0) return false;

            $negocio = $resNegocio->fetch_assoc();

            // 2. Servicios
            $sqlServicios = "SELECT id_servicio, servicio AS nombre, duracion ,precio
                            FROM negocio_servicios 
                            WHERE id_negocio = $id_negocio AND activo = 1";
            $resServicios = $this->query($sqlServicios);
            $servicios = [];
            while ($row = $resServicios->fetch_assoc()) {
                $servicios[] = $row;
            }

            // 3. Horarios
            $sqlHorarios = "SELECT dia, hora_inicio AS inicio, hora_fin AS fin, activo 
                            FROM negocio_horarios 
                            WHERE id_negocio = $id_negocio";
            $resHorarios = $this->query($sqlHorarios);
            $horarios = [];
            while ($row = $resHorarios->fetch_assoc()) {
                $horarios[$row['dia']] = [
                    "dia" => $row['dia'],
                    "inicio" => $row['inicio'],
                    "fin" => $row['fin'],
                    "activo" => (int)$row['activo']
                ];
            }

            // 4. Trabajadores
            $sqlTrabajadores = "SELECT ct.id_usuario_trabajador AS id_trabajador, mu.nombre
                                FROM cliente_trabajador ct
                                LEFT JOIN master_usuarios mu ON ct.id_usuario_trabajador = mu.id_usuario
                                WHERE ct.id_negocio = $id_negocio";
            $resTrabajadores = $this->query($sqlTrabajadores);
            $trabajadores = [];
            while ($row = $resTrabajadores->fetch_assoc()) {
                $trabajadores[] = $row;
            }

            // 5. Estructura final
            $json_final = [
                "id_negocio" => (int)$negocio['id_negocio'],
                "nombre_negocio" => $negocio['nombre_negocio'],
                "numero_negocio" => $negocio['numero_negocio'],
                "address" => $negocio['address'],
                "description" => $negocio['description'],
                "email" => $negocio['email'],
                "website" => $negocio['website'],
                "tipo_bot" => $negocio['tipo_bot'],
                "id_servicio" => (int)$negocio['id_servicio'],
                "id_whats" => $negocio['id_whats'],
                "status" => (int)$negocio['status'],
                "description" => $negocio['description'],
                "website" => $negocio['website'],
                "email" => $negocio['email'],
                "foto_perfil" => $negocio['foto_perfil'],
                "fecha_creacion" => $negocio['fecha_creacion'],
                "fecha_actualizacion" => $negocio['fecha_actualizacion'],
                "servicios" => $servicios,
                "horarios" => $horarios,
                "trabajadores" => $trabajadores
            ];

            // 6. Guardar archivo JSON
            $ruta = __DIR__ . '/../configuracion_negocio/negocio_'.$negocio['numero_negocio'].'.json';
            file_put_contents($ruta, json_encode($json_final, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if(file_exists($ruta)){
                $negocioConfiguracion = $this->getNegocio(["numero_negocio"=>$negocio['numero_negocio']]);
            }else{
                $codigo = "ERR";
                $negocioConfiguracion = "Error al obtener configuracion del negocio";
            }

            return [$codigo,$negocioConfiguracion[1]];
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

        // Funciones para bot
        public function checkDisponibilidad($params = null) {
            
            $id_trabajador = isset($params["id_trabajador"]) ? $this->cleanQuery($params["id_trabajador"]) : 0;
            $fecha = isset($params["fecha"]) ? $this->cleanQuery($params["fecha"]) : "";
            $duracion_minutos = isset($params["duracion_minutos"]) ? $this->cleanQuery($params["duracion_minutos"]) : 0;
            $slots_disponibles = [];

            // 1. Día de la semana en español
            $day = strtolower(date('l', strtotime($fecha)));
            $traducciones = [
                'sunday' => 'domingo',
                'monday' => 'lunes',
                'tuesday' => 'martes',
                'wednesday' => 'miercoles',
                'thursday' => 'jueves',
                'friday' => 'viernes',
                'saturday' => 'sabado'
            ];

            $dia_semana = $traducciones[$day] ?? null;
            if (!$dia_semana) return false;

            // 2. Obtener horario del trabajador para ese día
            $sqlHorario = "SELECT hora_inicio, hora_fin FROM trabajador_horarios 
                        WHERE id_usuario = $id_trabajador AND dia = '$dia_semana' AND activo = 1";

            $res = $this->query($sqlHorario);

            if ($res->num_rows == 0) return false;

            $horario = $res->fetch_assoc();
            $hora_inicio = $horario['hora_inicio'];
            $hora_fin = $horario['hora_fin'];

            $inicio_dia = strtotime("$fecha $hora_inicio");
            $fin_dia = strtotime("$fecha $hora_fin");

            // 3. Obtener citas ya agendadas ese día
            $sqlCitas = "SELECT hora_inicio, duracion FROM negocio_citas 
                        WHERE id_usuario = $id_trabajador AND fecha = '$fecha' AND estado = 'confirmada'";
            $resCitas = $this->query($sqlCitas);
            $bloques = [];

            while ($row = $resCitas->fetch_assoc()) {
                $inicio = strtotime("$fecha " . $row['hora_inicio']);
                $fin = $inicio + ($row['duracion'] * 60);
                $bloques[] = ['inicio' => $inicio, 'fin' => $fin];
            }

            // 4. Buscar bloques disponibles
            $bloque_actual = $inicio_dia;
            $requerido = $duracion_minutos * 60;

            while (($bloque_actual + $requerido) <= $fin_dia) {
                $bloque_valido = true;

                foreach ($bloques as $bloque) {
                    if (
                        ($bloque_actual < $bloque['fin']) &&
                        (($bloque_actual + $requerido) > $bloque['inicio'])
                    ) {
                        // Hay cruce con una cita
                        $bloque_valido = false;
                        $bloque_actual = $bloque['fin']; // Saltar al final de la cita
                        break;
                    }
                }

                if ($bloque_valido) {
                    $slots_disponibles[] = date("H:i", $bloque_actual);
                    $bloque_actual += $requerido;
                }
            }
            return count($slots_disponibles) > 0 ? $slots_disponibles : false;
        }

        public function obtenerHorariosDisponibles($params = null) {
            $codigo = "OK";
            $resultado = [];

            $id_servicio = isset($params["id_servicio"]) ? $this->cleanQuery($params["id_servicio"]) : 1;
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : 10;
            $fecha = isset($params["fecha"]) ? $this->cleanQuery($params["fecha"]) : "2025-07-28";
            $id_barbero_favorito = isset($params["id_barbero_favorito"]) ? $this->cleanQuery($params["id_barbero_favorito"]) : null;


            // 1. Obtener duración del servicio
            $queryDuracion = "SELECT duracion FROM negocio_servicios WHERE id_servicio = $id_servicio";
            $res = $this->query($queryDuracion);
            if (!$res || $res->num_rows == 0) {
                return ["ERROR", "Servicio no encontrado"];
            }

            $duracion = (int) $res->fetch_assoc()['duracion'];

            // 2. Obtener barberos que ofrecen ese servicio
            // $queryBarberos = "SELECT id_usuario FROM trabajador_servicio WHERE id_servicio = $id_servicio $filtro";
            // $resBarberos = $this->query($queryBarberos);
            // if (!$resBarberos || $resBarberos->num_rows == 0) {
                //     return ["ERROR", "No hay barberos disponibles para ese servicio"];
                // }
                
            $filtro = $id_barbero_favorito ? "AND ct.id_usuario = $id_barbero_favorito" : "";
            $queryBarberos = "SELECT ct.id_usuario_trabajador as id_usuario,mu.nombre,ct.id_negocio FROM cliente_trabajador ct 
                                LEFT JOIN master_usuarios mu ON ct.id_usuario_trabajador = mu.id_usuario 
                                WHERE ct.id_negocio = $id_negocio $filtro";

            $resBarberos = $this->query($queryBarberos);
            if (!$resBarberos || $resBarberos->num_rows == 0) {
                return ["ERROR", "No hay barberos disponibles para ese servicio"];
            }

            // 3. Buscar disponibilidad por barbero
            while ($row = $resBarberos->fetch_assoc()) {
                $id_trabajador = $row['id_usuario'];
                $disponibles = $this->checkDisponibilidad([
                    "id_trabajador"=>$id_trabajador,
                    "fecha" => $fecha,
                    "duracion_minutos" => $duracion]);

                if ($disponibles && is_array($disponibles)) {
                    $resultado[] = [
                        "barbero" => $row['nombre'],
                        "id_trabajador" => $id_trabajador,
                        "horarios" => $disponibles
                    ];

                    // Si es favorito, solo necesitamos uno
                    if ($id_barbero_favorito) break;
                }
            }

            return count($resultado) > 0 ? [$codigo, $resultado] : ["ERR", "No hay horarios disponibles para ese día"];
        }


    }

?>