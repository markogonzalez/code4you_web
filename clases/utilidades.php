<?php
    include_once("config.inc.php");
    require ruta_relativa.'/vendor/autoload.php';  // Ajusta la ruta si tu archivo está en otra carpeta
    include_once (core.'/database.php');
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Openpay\Data\Openpay;

	class utilidades extends database{
        public $sesion = [];

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

        public function getNegocioUsuario($params=null){
            error_log(print_r($params,true));
            $negocio = null;
            $id_usuario    = $this->cleanQuery($params["id_usuario"] ?? 0);
            $id_servicio    = $this->cleanQuery($params["id_servicio"] ?? 0);
            $qry_negocio = "SELECT * FROM cliente_negocio WHERE id_usuario =".$id_usuario." AND id_servicio =".$id_servicio." AND activo = 1";
            $res = $this->query($qry_negocio);
            if($res->num_rows > 0){
                $negocio = $res->fetch_assoc();
                
                $tsUltimo = strtotime($negocio['ultima_verificacion']);
                $tsDisponible = $tsUltimo + 600; // 10 minutos
                $ahora = time();
                $faltan = max(0, $tsDisponible - $ahora);
                $negocio['falta_reenviar'] = $faltan;
            }
            return ["OK",$negocio];
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
                "exp"     => $time + 3600 * 24,            // expira en 24 hora
                "id_usuario"     => $id_usuario,          // subject: ID de usuario
                "perfil_id"  => $perfil_id
            ];

            // 3) Generar token
            $token = JWT::encode($payload, $key, 'HS256');
            return $token;
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
                    if (!$arrPermisos['permisos'][$id]['r']) continue;
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

	}
?>
