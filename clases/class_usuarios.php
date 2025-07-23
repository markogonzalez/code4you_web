<?php 

    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class usuarios extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];
        private $whats = null;

        public function __construct() {
            parent::__construct();
            if($this->sesion){
                $this->id_modulo = 2;
                if($this->sesion['perfil_id']==2){
                    $this->id_modulo = 7;
                }
                $permisosArr = $this->setPermisosPerfil([
                    "id_modulo" => $this->id_modulo,
                    "perfil_id" => $this->sesion['perfil_id']
                ]);
                $this->permisos = $permisosArr;
            }
            error_log($this->id_modulo);
            @$this->whats = new whats();

        } //function __construct

        public function guardarUsuario($params = null){

            $codigo = "OK";
            $mensaje = "";

            $nombre = "";
            if(isset($params["nombre"]) && $params["nombre"] != "") 
            $nombre = $this->cleanQuery($params["nombre"]);

            $apellidos = "";
            if(isset($params["apellidos"]) && $params["apellidos"] != "") 
            $apellidos = $this->cleanQuery($params["apellidos"]);

            $celular = "";
            if(isset($params["celular"]) && $params["celular"] != "") 
            $celular = $this->cleanQuery($params["celular"]);

            $correo = "";
            if(isset($params["correo"]) && $params["correo"] != "") 
            $correo = $this->cleanQuery($params["correo"]);

            $contrasena = $this->generarContrasenaAleatoria();
            $contrasena_insert = password_hash($contrasena, PASSWORD_DEFAULT);
            
            $perfil_id = 0;
            if(isset($params["perfil_id"]) && $params["perfil_id"] > 0) 
            $perfil_id = $this->cleanQuery($params["perfil_id"]);

            $id_usuario = 0;
            if(isset($params["id_usuario"]) && $params["id_usuario"] > 0) 
            $id_usuario = $this->cleanQuery($params["id_usuario"]);

            $id_servicio = 0;
            if(isset($params["id_servicio"]) && $params["id_servicio"] > 0) 
            $id_servicio = $this->cleanQuery($params["id_servicio"]);

            $user_repeat = "SELECT * FROM master_usuarios WHERE celular ='".$celular."' AND perfil_id =".$perfil_id." AND status = 1";
            $res_repeat = $this->query($user_repeat);

            $nombre_completo = $nombre." ".$apellidos;
            
            if($id_usuario==0){

                $query = "INSERT INTO master_usuarios (
                    nombre, 
                    apellidos,
                    celular,
                    correo,
                    password,
                    perfil_id) VALUES (
                    '".$nombre."',
                    '".$apellidos."',
                    '".$celular."',
                    '".$correo."',
                    '".$contrasena_insert."',
                    ".$perfil_id.")";

            }else{

                $query = "UPDATE master_usuarios SET 
                nombre = '".$nombre."',
                apellidos = '".$apellidos."',
                celular = '".$celular."',
                correo = '".$correo."',
                perfil_id = ".$perfil_id."
                WHERE id_usuario = ".$id_usuario;
            }

            try {
                if($res_repeat->num_rows == 0 && $id_usuario==0){
                    
                    $this->query($query);
                    $id_insert = $this->conexMySQL->insert_id;
                    if(isset($this->sesion['perfil_id']) && $this->sesion['perfil_id']==2){
                        $qry_insert = " INSERT INTO cliente_trabajador (
                            id_usuario_cliente,
                            id_usuario_trabajador
                        ) VALUES (
                            ".$this->sesion['id_usuario'].",
                            ".$id_insert."
                        ) ";
                        if($this->query($qry_insert)){
                            list($codigo,$negocio) = $this->getNegocioUsuario(["id_usuario"=>$this->sesion['id_usuario'],"id_servicio"=>$id_servicio]);
                            if($codigo=="OK"){
                                list($codigoBienvenida,$response) = $this->whats->enviarRespuesta([
                                    "id_whats" => $negocio['id_whats'],
                                    "destinatario" => $celular,
                                    "tipo" => "template",
                                    "template" => "app_bienvenida2",
                                    "variables" => [$nombre_completo,$negocio['nombre_negocio']],
                                    "botones" => [
                                        ["sub_type" => "url",],
                                        ["sub_type" => "url",]
                                    ]
                                ]);
                                list($codigoContra,$responseContra) = $this->whats->enviarRespuesta([
                                    "id_whats" => $negocio['id_whats'],
                                    "destinatario" => $celular,
                                    "tipo" => "template",
                                    "template" => "app_contrasena",
                                    "variables" => [$contrasena],
                                    "botones" => [
                                        [
                                            "sub_type" => "url",
                                            "param" => $contrasena
                                        ]
                                        ],
                                    "idioma_plantilla" => "es",
    
                                ]);
    
                                if ($codigoBienvenida != "OK" || $codigoContra != "OK") {
                                    $codigo = "ERR";
    
                                    $errorBienvenida = isset($response['mensaje_error']) ? $response['mensaje_error'] : "";
                                    $errorContra = isset($responseContra['mensaje_error']) ? $responseContra['mensaje_error'] : "";
    
                                    $mensaje = trim($errorBienvenida . " " . $errorContra);
                                }
                            }else{
                                $codigo = "ERR";
                                $mensaje = "Error al obtener información del negocio";
                            }
                            
                        }
                    }
                }else{
                    if($id_usuario > 0){
                        $this->query($query);
                        $mensaje = "Usuario actualizado exitosamente";
                    }else{
                        $codigo = "ERR";
                        $mensaje = "El celular ya se encuentra registrado en sistema";
                    }
                }
                
            } catch (Exception $e) {
                $codigo = "ERR";
                $mensaje = "Error al insertar usuario, intenta de nuevo";
                error_log("Error al insertar usuario, intenta de nuevo - ".$e);
            }

            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje));

        }

        public function getUsuarios($params=null){

            $codigo = "OK";
            $mensaje = "";
            $elementos = [];
            $condicion = "";
            $inner = "";

            $id_usuario = 0;
            if(isset($params["id_usuario"]) && $params["id_usuario"] >0) 
            $id_usuario = $this->cleanQuery($params["id_usuario"]);

            if($id_usuario==0){
                $condicion = "AND u.id_usuario >1";
            }else{
                $condicion = "AND u.id_usuario =".$id_usuario;
            }

            if($this->sesion!=null){
                if($this->sesion['perfil_id']==2){
                    $inner = "INNER JOIN cliente_trabajador ct ON ct.id_usuario_trabajador = u.id_usuario AND ct.id_usuario_cliente =".$this->sesion['id_usuario'];
                }
            }

            $query = "SELECT 
            u.id_usuario,
            CONCAT(u.nombre, ' ', u.apellidos) as nombre_completo,
            u.nombre,
            u.apellidos,
            u.celular,
            u.perfil_id,
            p.perfil
            FROM master_usuarios u
            INNER JOIN catalogo_perfiles p ON u.perfil_id = p.id_perfil
            ".$inner."
            WHERE u.status = 1 ".$condicion;

            $result = $this->query($query);
            if($result->num_rows > 0){
                if($id_usuario>0){
                    $elementos = $result->fetch_assoc();
                }else{

                    while ($usuarios = $result->fetch_assoc()) {

                        $boton_editar = "";
                        $boton_elimar = "";

                        if($this->permisos['permisos'][$this->id_modulo]['u']){
                            $boton_editar = '<button data-id_usuario="'.$usuarios["id_usuario"].'" class="btn btn-icon btn-primary btn-sm btn-editar"><i class="ki-duotone ki-notepad-edit fs-1"><span class="path1"></span><span class="path2"></span></i></button>';
                        }
                        if($this->permisos['permisos'][$this->id_modulo]['d']){
                            $boton_elimar = '<button data-id_usuario="'.$usuarios["id_usuario"].'" class="btn btn-icon btn-primary btn-sm btn-eliminar"><i class="ki-duotone ki-trash fs-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i></button>';
                        }
    
                        $usuarios['acciones'] = $boton_editar." ".$boton_elimar;
                        $elementos[] = $usuarios;
                    }

                }
                
            }else{
                $codigo = "ERR";
                $mensaje = "Sin resultados que mostrar";
            }
            
            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje,"usuarios"=>$elementos));

        }

        public function eliminarUsuario($params = null){
            $codigo  = "OK";
            $mensaje = "";
            if ($this->permisos['permisos'][$this->id_modulo]['d']) {
                $id_usuario = intval($params['id_usuario'] ?? 0);
                if ($id_usuario > 0) {
                    $query_borrar = "UPDATE master_usuarios SET status = '0' WHERE id_usuario = $id_usuario";
                    try {
                        $this->query($query_borrar);
                        if($this->sesion['perfil_id']==2){
                            $query_borrar = "DELETE FROM cliente_trabajador WHERE id_usuario_trabajador = ".$id_usuario;
                            $this->query($query_borrar);
                        }
                    } catch (Exception $e) {
                        $codigo  = "ERR";
                        $mensaje = "Error al eliminar usuario";
                    }
                } else {
                    $codigo  = "ERR";
                    $mensaje = "ID de usuario no válido";
                }
            } else {
                $codigo  = "ERR";
                $mensaje = "Sin permisos para realizar la acción";
            }

            return [$codigo,["mensaje"=>$mensaje]];
        }
        
        public function getTrabajadoresCliente($params = null){

            $ids = "";


            
            return $ids;
        }

        public function getNegocio($params = null){
            $negocio = $this->getNegocioUsuario([
                'id_usuario' => $params['id_usuario'], 
                'id_servicio' => $params['id_servicio']
            ]);
            // error_log(print_r($negocio,true));
            return $negocio;
        }
        

    }

?>