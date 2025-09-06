<?php
    include_once(__DIR__ . '/../config.inc.php');
    require ruta_relativa.'/vendor/autoload.php';  // Ajusta la ruta si tu archivo está en otra carpeta
    include_once (core.'/database.php');
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Openpay\Data\Openpay;

	class utilidades extends database{
        public $sesion = [];
        public $dias_semana = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];

        public function __construct() {
            parent::__construct();
            $token = isset($_POST['token']) ? $_POST['token'] : '';
            if($token !=="undefined"){
                $decoded = $this->validarToken($token);
                if($decoded){
                    $this->sesion = json_decode(json_encode($decoded), true);
                }
            }
        }

        public function getUsuario($params=null){
            $usuario = null;
            $id_usuario    = $this->cleanQuery($params["id_usuario"] ?? 0);
            $qry_usuario = "SELECT * FROM master_usuarios WHERE id_usuario =".$id_usuario;
            $res = $this->query($qry_usuario);
            if($res->num_rows > 0){
                $usuario = $res->fetch_assoc();
            }

            return $usuario;
            
        }

        public function getNegocio($params = null) {
            
            $id_usuario = isset($params["id_usuario"]) ? $this->cleanQuery($params["id_usuario"]) : 0;
            $id_servicio = isset($params["id_servicio"]) ? $this->cleanQuery($params["id_servicio"]) : 0;
            $numero_negocio = isset($params["numero_negocio"]) ? $this->cleanQuery($params["numero_negocio"]) : 0;
            $datos_negocio = null;

            if($id_usuario > 0 && $id_servicio > 0){
    
                $qry = "SELECT *,
                    n.id_negocio as id_negocio_cliente,
                    IFNULL(s.total_servicios,0) as total_servicios,
                    h.activo as horario_activo,
                    n.id_servicio as id_servicio_negocio,
                    CASE 
                        WHEN n.id_servicio = 1 THEN 'barberia'
                        ELSE 'code4you'
                    END as 'tipo_bot'
                    FROM cliente_negocio n 
                    LEFT JOIN negocio_horarios h ON n.id_negocio = h.id_negocio 
                    LEFT JOIN (SELECT COUNT(*) as total_servicios,id_negocio,servicio FROM negocio_servicios ) as s ON n.id_negocio = s.id_negocio
                    WHERE n.id_usuario = $id_usuario AND n.id_servicio = $id_servicio AND n.activo = 1";
                $res = $this->query($qry);
    
                if ($res->num_rows > 0) {
                    $horarios = [];
                    $negocioBase = null;
    
                    while ($row = $res->fetch_assoc()) {
    
                        $negocioBase = [
                            "id_negocio" => (int)$row['id_negocio_cliente'],
                            "nombre_negocio" => $row['nombre_negocio'],
                            "numero_negocio" => $row['numero_negocio'],
                            "address" => $row['address'],
                            "description" => $row['description'],
                            "email" => $row['email'],
                            "website" => $row['website'],
                            "tipo_bot" => $row['tipo_bot'],
                            "id_servicio" => (int)$row['id_servicio_negocio'],
                            "id_whats" => $row['id_whats'],
                            "status" => (int)$row['status'],
                            "description" => $row['description'],
                            "website" => $row['website'],
                            "email" => $row['email'],
                            "foto_perfil" => $row['foto_perfil'],
                            "fecha_creacion" => $row['fecha_creacion'],
                            "ultima_verificacion" => $row['ultima_verificacion'],
                            "fecha_actualizacion" => $row['fecha_actualizacion'],
                            "total_servicios" => (int)$row['total_servicios'],
                        ];
                        if ($row['dia'] != null) {
                            $horarios[$row['dia']] = [
                                'dia' => $row['dia'],
                                'inicio' => $row['hora_inicio'],
                                'fin' => $row['hora_fin'],
                                'activo' => $row['horario_activo']
                            ];
                        }
                        
                    }
                    
                    
                    $tsUltimo = strtotime($negocioBase['ultima_verificacion']);
                    $tsDisponible = $tsUltimo + 600;
                    $ahora = time();
                    $faltan = max(0, $tsDisponible - $ahora);
    
                    $negocioBase['falta_reenviar'] = $faltan;
                    $negocioBase['horarios'] = $horarios;
    
                    return ["OK", $negocioBase];
                }
    
                return ["OK", null];
            }else{

                // Ruta al archivo JSON cacheado
                $archivo_json = __DIR__ . "/../configuracion_negocio/negocio_{$numero_negocio}.json";
                if (file_exists($archivo_json)) {
                    $contenido = file_get_contents($archivo_json);
                    $datos_negocio = json_decode($contenido, true);
                    if (!is_array($datos_negocio)) {
                        return ["ERR", "JSON inválido o corrupto"];
                    }
                }
                return ["OK", $datos_negocio];
            }

        }

        public function openpay($params = null){

            $openpay = Openpay::getInstance(MERCHANT_ID, API_KEY_OPENPAY, 'MX', '127.0.0.1');
            return $openpay;
        }

        public function validarToken($token) {

            if(empty($token)) return false;
            $key = TOKEN_C4Y;
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
                // $decoded = \MyProject\JWT::decode($token, $key);

                // Puedes acceder a $decoded->sub, $decoded->perfil, etc.
                return (array)$decoded;

            } catch (\Exception $e) {
                // Token inválido o expirado
                error_log($e);
                return $decoded=null;
            }
        }

        public function generarToken($params = null){
            
            $token = null;
            $id_usuario    = $this->cleanQuery($params["id_usuario"] ?? 0);
            $perfil_id    = $this->cleanQuery($params["perfil_id"] ?? 0);

            // 2) Crear payload JWT
            $time    = time();
            $key     = TOKEN_C4Y;  // Defínela en config o variable de entorno
            $payload = [
                "iat"     => $time,                   // emitido en
                "exp"     => $time + 3600,            // expira en 24 hora
                "id_usuario"     => $id_usuario,          // subject: ID de usuario
                "perfil_id"  => $perfil_id
            ];

            // 3) Generar token
            $token = JWT::encode($payload, $key, 'HS256');
            return $token;
        }

        public function renovarTokenUtilidades($token) {

            $codigo = "ERR";
            $mensaje = "Token inválido o expirado.";
            $data = [];

            $clave = TOKEN_C4Y;
            try {
                $decoded = JWT::decode($token, new Key($clave, 'HS256'));
                $payload = (array) $decoded;

                // Verifica tiempo restante
                $exp = $payload['exp'];
                $now = time();

                if (($exp - $now) < 0) {
                    return [$codigo, "Token expirado."];
                }

                // Renueva token con nueva expiración
                $payload['iat'] = $now;
                $payload['exp'] = $now + (60 * 30); // 30 minutos extra

                $nuevo_token = JWT::encode($payload, $clave, 'HS256');
                $data = [ "token" => $nuevo_token ];
                return ["OK", $data];

            } catch (Exception $e) {
                return [$codigo, "Error al renovar token: " . $e->getMessage()];
            }
        }

        function verificarToken($token) {

            if (!$token) return [false, "Token no encontrado"];

            try {
                $decoded = JWT::decode($token, new Key(TOKEN_C4Y, 'HS256'));
                return [true, $decoded];
            } catch (Exception $e) {
                return [false, "Token inválido o expirado"];
            }
        }

        public function setPermisosPerfil($params = null){

            $arrPermisos= array();
            
            $id_modulo = 0;
            if(isset($params['id_modulo']) && $params['id_modulo'] >0) 
            $id_modulo = $this->cleanQuery($params['id_modulo']);

            $perfil_id = 0;
            if(isset($params['perfil_id']) && $params['perfil_id'] >0) 
            $perfil_id = $this->cleanQuery($params['perfil_id']);

            $qry_permisos = "SELECT 
                p.perfil_id,
                p.modulo_id,
                m.titulo as modulo,
                p.r,
                p.w,
                p.u,
                p.d 
                FROM permisos p 
                INNER JOIN catalogo_modulos m ON p.modulo_id = m.id_modulo 
                WHERE p.perfil_id =".$perfil_id;

            $res_premisos = $this->query($qry_permisos);
            while($row = $res_premisos->fetch_assoc()){
                $arrPermisos[$row['modulo_id']] = $row;
            }
            $permisos = "";
            $permisosModulos ="";

            if (count($arrPermisos) > 0){
                $permisos = $arrPermisos;
                $permisosModulos = isset($arrPermisos[$id_modulo]) ? $arrPermisos[$id_modulo] : "";
            }

            $permisosData = $permisos;
            $permisosModulosData = $permisosModulos;
            return [
                "permisos" => $permisosData,
                "permisos_modulos" => $permisosModulosData
            ];
        }

        public function generarMenu($id_usuario,$perfil_id) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $arrPermisos = $this->setPermisosPerfil(['perfil_id' => $perfil_id]);

            // Módulos activos y pagados
            $modulos_pagados = $this->getModulosPagados($id_usuario);

            $qry_menu = "SELECT * FROM catalogo_modulos WHERE status = 1";
            $res = $this->query($qry_menu);
            $modulos = [];
            while ($row = $res->fetch_assoc()) {
                $id = $row['id_modulo'];

                // Validaciones
                if($row['vista']!=""){
                    if (!isset($arrPermisos['permisos'][$id]) || !$arrPermisos['permisos'][$id]['r']) continue;
                    if ($row['pago'] == 1 && !isset($modulos_pagados[$id])) continue;
                }

                $modulos[$id] = $row;
            }

            // Agrupar padres e hijos
            $estructura_menu = [];

            foreach ($modulos as $modulo) {
                $id = $modulo['id_modulo'];
                $id_padre = $modulo['id_padre'];
                $is_padre = $modulo['padre'] == 1;

                $item = [
                    'id' => $id,
                    'titulo' => $modulo['titulo'],
                    'vista' => $modulo['vista'],
                    'icono' => $modulo['icono'],
                ];

                if ($is_padre) {
                    $estructura_menu[$id] = $item + ['hijos' => []];
                } else {
                    if (isset($estructura_menu[$id_padre])) {
                        $estructura_menu[$id_padre]['hijos'][] = $item;
                    } else {
                        // En caso de inconsistencia
                        $estructura_menu[$id_padre] = [
                            'id' => $id_padre,
                            'titulo' => 'Desconocido',
                            'vista' => null,
                            'icono' => 'ki-duotone ki-element-11',
                            'hijos' => [$item]
                        ];
                    }
                }
            }

            $data['estructura'] = array_values($estructura_menu); // Reset keys para JSON

            return [$codigo, $data];
        }

        public function getModulosPagados($id_usuario){
            $modulos_pagados = [];
            $qry_pagados = "
                SELECT cm.id_modulo,
                ss.fecha_proximo_cobro,
                ss.estatus,
                ss.id_suscripcion
                FROM servicios_suscripciones ss
                INNER JOIN servicios_planes sp ON ss.id_plan = sp.id_plan
                INNER JOIN catalogo_servicios cs ON cs.id_servicio = sp.id_servicio
                INNER JOIN catalogo_modulos cm ON cs.id_modulo = cm.id_modulo
                WHERE ss.id_usuario = $id_usuario
                AND ss.estatus = 1
                AND cm.status = 1
            ";

            $res_pagados = $this->query($qry_pagados);
            $modulos_pagados = [];
            while ($row = $res_pagados->fetch_assoc()) {
                $modulos_pagados[$row['id_modulo']] = $row;
            }
            return $modulos_pagados;
        }

        public function generarContrasenaAleatoria($longitud = 6) {
            $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
            $contrasena = '';
            $max = strlen($caracteres) - 1;

            for ($i = 0; $i < $longitud; $i++) {
                $contrasena .= $caracteres[random_int(0, $max)];
            }

            return $contrasena;
        }

        public function guardarRespuestaWhats($params = null) {
            
            $codigo = "OK";

            $id_cliente = isset($params["id_cliente"]) ? $this->cleanQuery($params["id_cliente"]) : 0;
            $id_negocio = isset($params["id_negocio"]) ? $this->cleanQuery($params["id_negocio"]) : 0;
            $mensaje = isset($params["mensaje"]) ? $this->cleanQuery($params["mensaje"]) : "";
            $tipo = isset($params["tipo"]) ? $this->cleanQuery($params["tipo"]) : "";
            $modulo_origen = isset($params["modulo_origen"]) ? $this->cleanQuery($params["modulo_origen"]) : "";
            $tipo_whats = isset($params["tipo_whats"]) ? $this->cleanQuery($params["tipo_whats"]) : "";
            $id_usuario = isset($params["id_usuario"]) ? $this->cleanQuery($params["id_usuario"]) : 0;
            $mensaje_id_externo = isset($params["mensaje_id_externo"]) ? $this->cleanQuery($params["mensaje_id_externo"]) : "";
            $estado_salida = isset($params["estado_salida"]) ? $this->cleanQuery($params["estado_salida"]) : "enviado";
            $respuesta_interactiva = isset($params["respuesta_interactiva"]) ? $this->cleanQuery($params["respuesta_interactiva"]) : 0;
            $metadata = isset($params["metadata"]) ? json_encode($params["metadata"]) : null;

             $qry_insert = "INSERT INTO mensajes_chat (
                    id_cliente,
                    id_negocio,
                    mensaje,
                    tipo,
                    modulo_origen,
                    tipo_whats,
                    id_usuario,
                    mensaje_id_externo,
                    estado_salida,
                    respuesta_interactiva,
                    metadata
                ) VALUES (
                    $id_cliente, 
                    $id_negocio,
                    '$mensaje',
                    '$tipo',
                    '$modulo_origen',
                    '$tipo_whats', 
                    $id_usuario,
                    '$mensaje_id_externo',
                    '$estado_salida',
                    $respuesta_interactiva,
                    '$metadata')";

            try {
                $this->query($qry_insert);
            } catch (Exception $e) {
                error_log("Error al guardar la respuesta: " . $e->getMessage());
                $codigo = "ERR";
            }

            return[$codigo];
        }

        public function actualizarIntencionWhats($params = null) {

            $id_cliente = $params['id_cliente'];
            $intencion = $params['intencion'];
            $espera_flujo = $params['espera_flujo'];

            $qry_update = "UPDATE negocio_clientes SET intencion = '".$intencion."', espera_flujo = '".$espera_flujo."' WHERE id_cliente = ".$id_cliente;

            try {
                $this->query($qry_update);
            } catch (Exception $e) {
                error_log("Error al actualizar el estado: " . $e->getMessage());
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

	}
?>
