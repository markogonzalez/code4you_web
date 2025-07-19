<?php 

    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class login extends utilidades{
        private $whats = null;
        public function __construct() {
            parent::__construct();
            @$this->whats = new whats();
        } //function __construct
        
        public function autenticar($params = null) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $celular    = $this->cleanQuery($params["celular"] ?? '');
            $contrasena = $this->cleanQuery($params["contrasena"] ?? '');

            // 1) Verificar credenciales
            $query = "SELECT id_usuario, CONCAT(nombre, ' ', apellidos) as nombre_completo,perfil_id,password,celular FROM master_usuarios 
                    WHERE celular = '$celular' AND status = 1 LIMIT 1";
            $res   = $this->query($query);
            $usuario = $res->fetch_assoc();

            if ($res->num_rows !== 1 || !password_verify($contrasena, $usuario['password'])) {
                $codigo  = "ERR";
                $mensaje = "Usuario o contraseña incorrectos.";
                return [$codigo, ["mensaje" => $mensaje]];
            }
            
            $token = $this->generarToken([
                "id_usuario"=>$usuario['id_usuario'],
                "perfil_id"=>$usuario['perfil_id'],
            ]);

            $menu = $this->generarMenu($usuario['id_usuario'],$usuario['perfil_id']);

            // 4) Devolver token y datos mínimos
            $data = [
                "token"  => $token,
                "nombre" => $usuario['nombre_completo'],
                "celular" => $usuario['celular'],
                "estructura" => $menu[1]['estructura']
            ];

            return [$codigo, $data];
        }

        public function ResetPass($params = null) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];
            $fin = true;
            $nuevo = false;

            $paso = isset($params["paso"]) ? $this->cleanQuery($params["paso"]) : 0;
            switch ($paso) {
                case 1:
                    $response = $this->generarCodigo($params);
                    if($response[0]!= "OK"){
                        $codigo = $response[0];
                        $mensaje = $response[1]["mensaje"];
                    }
                    $fin = false;
                    break;
                case 2:
                    $response = $this->verificarCodigo($params);
                    if($response[0]!= "OK"){
                        $codigo = $response[0];
                        $mensaje = $response[1]["mensaje"];
                    }
                    $fin = false;
                    break;
                case 3:
                    $response = $this->guardarContrasena($params);
                    if($response[0]!= "OK"){
                        $codigo = $response[0];
                        $mensaje = $response[1]["mensaje"];
                        $fin = false;
                    }else{
                        $nuevo = true;
                    }
                    break;
                
            }

            $data["mensaje"] = $mensaje;
            $data["fin"] = $fin;
            $data["nuevo"] = $nuevo;
            return [$codigo, $data];
        }

        private function generarCodigo($params = null) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $token = rand(100000, 999999);
            $fecha_actual = date("Y-m-d H:i:s");
            $celular = isset($params["celular"]) ? $this->cleanQuery($params["celular"]) : "";

            $qry_update = "UPDATE codigo_recuperacion SET usado = 1 WHERE celular = '$celular' AND usado = 0";
            $qry_insert = "INSERT INTO codigo_recuperacion (celular, codigo, creado_en) 
                        VALUES ('$celular', '$token', '$fecha_actual')";
            if($celular!=""){
                try {
                    $this->query($qry_update);
                    $this->query($qry_insert);
    
                    $res_envio = $this->whats->enviarRespuesta([
                        "numero" => $celular,
                        "tipo" => "template",
                        "template" => "c4y_codigo_reset_contrasena",
                        "variables" => [$token],
                        "botones" => [
                            [
                                "sub_type" => "url",
                                "param" => $token
                            ]
                        ]
                    ]);
    
                    if ($res_envio[0] != "OK") {
                        $codigo = "ERR";
                        $mensaje = "Error al enviar mensaje de WhatsApp";
                    } else {
                        $mensaje = "Código enviado correctamente.";
                    }
    
                } catch (Exception $e) {
                    $codigo = "ERR";
                    $mensaje = "Error al insertar token de recuperación.";
                    error_log("Error en ResetPass: " . $e->getMessage());
                }
            }else{
                $codigo = "ERR";
                $mensaje = "Ningún número celular enviado";
            }

            $data["mensaje"] = $mensaje;
            return [$codigo, $data];

        }

        private function verificarCodigo($params = null){
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $celular = isset($params["celular"]) ? $this->cleanQuery($params["celular"]) : "";
            $codigo_verificar = isset($params["codigo"]) ? $this->cleanQuery($params["codigo"]) : "";

            // Buscar el último código válido (no usado, generado hace menos de 10 minutos)
            $qry = "SELECT id, creado_en 
                    FROM codigo_recuperacion 
                    WHERE celular = '$celular' 
                    AND codigo = '$codigo_verificar' 
                    AND usado = 0 
                    AND TIMESTAMPDIFF(MINUTE, creado_en, NOW()) <= 10 
                    ORDER BY creado_en DESC 
                    LIMIT 1";

            $res = $this->query($qry);

            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $update = "UPDATE codigo_recuperacion SET usado = 1 WHERE id = ". $row['id'];
                $this->query($update);

                $mensaje = "Código verificado correctamente.";
            } else {
                $codigo = "ERR";
                $mensaje = "El código ingresado es incorrecto, ya fue usado o ha expirado.";
            }

            $data["mensaje"] = $mensaje;
            return [$codigo, $data];
        }

        private function guardarContrasena($params = null) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $celular = isset($params["celular"]) ? $this->cleanQuery($params["celular"]) : "";
            $contrasena = isset($params["contrasena"]) ? $this->cleanQuery($params["contrasena"]) : "";
            $contrasena_r = isset($params["contrasena_r"]) ? $this->cleanQuery($params["contrasena_r"]) : "";
            $nuevo = isset($params["nuevo"]) ? $this->cleanQuery($params["nuevo"]) : 0;

            if($contrasena !== $contrasena_r){
                $codigo = "ERR";
                $mensaje = "Las contraseñas no coinciden.";
            }else{
                $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
                $qry_update = "UPDATE master_usuarios SET password = '$hashed_password' WHERE celular = '$celular'";

                if($nuevo == 1){
                    $qry_update = "UPDATE master_usuarios SET password = '$hashed_password' WHERE celular = '$celular'";
                }
                if($this->query($qry_update)){
                    $mensaje = "Contraseña actualizada correctamente.";
                }else{
                    $codigo = "ERR";
                    $mensaje = "Error al actualizar la contraseña.";
                }
            }

            $data["mensaje"] = $mensaje;
            $data["nuevo"] = $nuevo;
            return [$codigo, $data];
        }

    }

?>