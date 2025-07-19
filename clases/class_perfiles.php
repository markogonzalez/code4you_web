<?php 
	include_once 'utilidades.php';

    class perfiles extends utilidades{
        
        private $id_modulo = 0;
        private $permisos = [];

        public function __construct() {
            parent::__construct();
            if($this->sesion){
                $permisosArr = $this->setPermisosPerfil([
                    "id_modulo" => 3,
                    "perfil_id" => $this->sesion['perfil_id']
                ]);
                $this->permisos = $permisosArr;
            }
            $this->id_modulo = 3;

        } //function __construct

        public function getperfiles($params=null){

            $codigo = "OK";
            $mensaje = "";
            $condicion = "";
            $elementos = [];

            $id_perfil = 0;
            if(isset($params["id_perfil"]) && $params["id_perfil"] >0) 
            $id_perfil = $this->cleanQuery($params["id_perfil"]);

            if($id_perfil>0){
                $condicion = "WHERE id_perfil = ".$id_perfil;
            }
            if($this->sesion!=null){

                $query_rol = "SELECT * FROM catalogo_perfiles ".$condicion;
                $result = $this->query($query_rol);
                if($result->num_rows > 0){
                    if($id_perfil>0){
                        $elementos = $result->fetch_assoc();
                    }else{
                        while($perfiles = $result->fetch_assoc()){

                            $btnPermisos = '';
                            $btnEdit = '';
                            
                            if($this->sesion['perfil_id']==1){
                                $btnPermisos = '<button class="btn btn-icon btn-primary btn-sm btn_permisos" data-id_perfil="'.$perfiles['id_perfil'].'" ><i class="ki-duotone ki-lock fs-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i></button>';
                            }
                            if($this->permisos['permisos'][$this->id_modulo]['u']){
                                $btnEdit = '<button class="btn btn-icon btn-primary btn-sm btn_editar" data-id_perfil="'.$perfiles['id_perfil'].'" ><i class="ki-duotone ki-notepad-edit fs-1"><span class="path1"></span><span class="path2"></span></i></button>';
                            }
                            if ($perfiles['status']==1) {
                                $perfiles['status_txt']='<span class="badge badge-success">Activo</span>';
                            }elseif ($perfiles['status']==0) {
                                $perfiles['status_txt']='<span class="badge badge-danger">Inactivo</span>';
                            }

                            $perfiles['acciones']='<div class="text-center">'.$btnPermisos.' '.$btnEdit.'</div>';
                            $elementos[] = $perfiles;
                        }
                    }
                }else{
                    $codigo = "ERR";
                    $mensaje = "Sin resultados que mostrar";
                }
            }else{
                $codigo = "SESION";
                
            }
            
            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje,"perfiles"=>$elementos));
            
        }

        public function guardarPerfil($params = null){

            $codigo = "OK";
            $mensaje = "";

            $nombre = "";
            if(isset($params["nombre"]) && $params["nombre"] != "") 
            $nombre = $this->cleanQuery($params["nombre"]);

            $descripcion = "";
            if(isset($params["descripcion"]) && $params["descripcion"] != "") 
            $descripcion = $this->cleanQuery($params["descripcion"]);

            $status = "";
            if(isset($params["status"]) && $params["status"] != "") 
            $status = $this->cleanQuery($params["status"]);

            $id_perfil = 0;
            if(isset($params["id_perfil"]) && $params["id_perfil"] > 0) 
            $id_perfil = $this->cleanQuery($params["id_perfil"]);


            $rol_repeat = "SELECT * FROM catalogo_perfiles WHERE perfil ='".$nombre."'";
            $res_repeat = $this->query($rol_repeat);
            if($res_repeat->num_rows == 0){

                if($id_perfil==0){

                    $query = "INSERT INTO catalogo_perfiles (
                        perfil, 
                        descripcion,
                        status) VALUES (
                        '".$nombre."',
                        '".$descripcion."',
                        ".$status.")";

                }else{

                    $query = "UPDATE catalogo_perfiles SET 
                    perfil = '".$nombre."',
                    descripcion = '".$descripcion."',
                    status = '$status'
                    WHERE id_perfil = ".$id_perfil;
                }

                try {
                    $this->query($query);
                    $mensaje = "Perfil registrado exitosamente";
                } catch (Exception $e) {
                    $codigo = "ERR";
                    $mensaje = "Error al insertar perfil, intenta de nuevo";
                    error_log("Error al insertar perfil, intenta de nuevo - ".$e);
                }


            }else{
                $codigo = "ERR";
                $mensaje = "Ya existe un perfil con el mismo nombre.";
            }

            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje));

        }

        public function getPermisosPerfil($params = null){

            $codigo = "OK";
            $mensaje = "";

			$id_perfil = 0;
            if(isset($params["id_perfil"]) && $params["id_perfil"] > 0) 
            $id_perfil = $this->cleanQuery($params["id_perfil"]);
            
            $qry_modulos = "SELECT * FROM catalogo_modulos WHERE status = 1 AND vista is not null";
            $res_modulos = $this->query($qry_modulos);

            $query_permisos = "SELECT * FROM permisos WHERE perfil_id =".$id_perfil;
            $res_permisos = $this->query($query_permisos);

            // Procesamos los permisos asociados al rol
            $arrPermisosRol = [];
            if ($res_permisos->num_rows > 0) {
                while ($row = $res_permisos->fetch_assoc()) {
                    $arrPermisosRol[$row['modulo_id']] = $row;
                }
            }

            // Procesamos los módulos y asignamos permisos
            $arrModulos = [];
            while ($row = $res_modulos->fetch_assoc()) {
                $permisosModulo = array("r" => 0, "w" => 0, "u" => 0, "d" => 0);
                if(isset($arrPermisosRol[$row['id_modulo']])){
                    $permisosModulo = [
                        "r" => $arrPermisosRol[$row['id_modulo']]["r"],
                        "w" => $arrPermisosRol[$row['id_modulo']]["w"],
                        "u" => $arrPermisosRol[$row['id_modulo']]["u"],
                        "d" => $arrPermisosRol[$row['id_modulo']]["d"]
                    ];
                }

                $row['permisos'] = $permisosModulo;
                $arrModulos[$row['id_modulo']] = $row;
            }

            $resultado = [
                "id_perfil" => $id_perfil,
                "modulos" => array_values($arrModulos)
            ];

            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje,"permisos"=>$resultado));
			
		}

        public function guardarPermisos($params = null){

            $codigo = "OK";
            $mensaje = "";

            $id_perfil = 0;
            if(isset($params["id_perfil_permisos"]) && $params["id_perfil_permisos"] > 0) 
            $id_perfil = $this->cleanQuery($params["id_perfil_permisos"]);

            $query_borrar = "DELETE FROM permisos WHERE perfil_id = $id_perfil";
            try {
                $this->query($query_borrar);
                $modulos = $_POST['modulos'];

				foreach ($modulos as $modulo) {
					$id_modulo = $modulo['id_modulo'];
					$r = empty($modulo['r']) ? 0 : 1;
					$w = empty($modulo['w']) ? 0 : 1;
					$u = empty($modulo['u']) ? 0 : 1;
					$d = empty($modulo['d']) ? 0 : 1;

                    $query = "INSERT INTO permisos (
                        perfil_id, 
                        modulo_id,
                        r,
                        w,
                        u,
                        d) VALUES (
                        ".$id_perfil.",
                        ".$id_modulo.",
                        ".$r.",
                        ".$w.",
                        ".$u.",
                        ".$d.")";
                    $this->query($query);
				}
                $mensaje = "Permisos actualizados exitosamente";

            } catch(Exception $e){
                $codigo = "ERR";
                $mensaje = "Error al limpiar permisos";
            }

            return array(0 => $codigo, 1 => array("mensaje"=>$mensaje));
        }

    }

?>