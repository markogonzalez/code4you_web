<?php 

    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class salon extends utilidades{

        private $whats = null;
        private $id_modulo = 0;
        private $ruta = ruta_relativa;
        private $permisos = [];

        public function __construct() {
            parent::__construct();
            if($this->sesion){
                $permisosArr = $this->setPermisosPerfil([
                    "id_modulo" => 5,
                    "perfil_id" => $this->sesion['perfil_id']
                ]);
                $this->permisos = $permisosArr;
            }
            @$this->whats = new whats();
            $this->id_modulo = 5;

        } //function __construct

        public function getServicios($params=null){
            
            $codigo = "OK";
            $mensaje = "";
            $data = [];
            $elementos = [];
            $planesPorIntervalo = [];
            $condicion_id_servicio = "";
            $condicion_usuario = "";
            $joinPlanes = "(SELECT MIN(precio) as precio,id_servicio,id_plan,intervalo,nombre_plan,lista_beneficios,descripcion,id_plan_openpay FROM servicios_planes)";

            $id_servicio    = $this->cleanQuery($params["id_servicio"] ?? '');
            
            if($id_servicio!=""){
                $condicion_id_servicio = " AND s.id_servicio =".$id_servicio;
                $joinPlanes = "servicios_planes";
            }
            
            $query ="SELECT s.servicio,
                s.imagen,
                s.id_servicio,
                sp.precio,
                sp.id_plan,
                sp.intervalo,
                sp.nombre_plan,
                sp.lista_beneficios,
                sp.descripcion,
                sp.id_plan_openpay,
                s.id_modulo
                FROM catalogo_servicios s
                LEFT JOIN ".$joinPlanes." as sp ON sp.id_servicio = s.id_servicio
                WHERE s.status = 1".$condicion_id_servicio;

            $result = $this->query($query);
            if($this->sesion!=null){

                if($this->sesion['perfil_id']==2){
                    $modulos_pagados = $this->getModulosPagados($this->sesion['id_usuario']);
                }
            }
            if ($result->num_rows > 0) {
                $html  = '';
                
                while ($servicio = $result->fetch_assoc()) {
                    // Informacion para vista servicios
                    // Prepara variables para inyectar
                    $id_servicio = intval($servicio['id_servicio']);
                    $img     = htmlspecialchars($servicio['imagen'], ENT_QUOTES);
                    $nombre  = htmlspecialchars($servicio['servicio'], ENT_QUOTES);
                    $descripcion = '';
                    $estado = "";

                    
                    $botones ='<button type="button" data-id-servicio="'.$id_servicio.'" class="btn btn-sm btn-primary w-100 btn-subscribirse" ><i class="ki-solid ki-basket-ok fs-1" style="margin-top: -5px;"></i> Subscribirse</button>';
                    $precio  = number_format($servicio['precio'], 2);
                    $descripcion = '<span class="text-gray-600 text-end fw-bold fs-6">Desde: $'.$precio.' MXN</span>';

                    if(isset($modulos_pagados[$servicio['id_modulo']])){
                        $botones ='<button type="button" data-id-servicio="'.$id_servicio.'" data-id_perfil="'.$this->sesion['perfil_id'].'" class="btn btn-sm btn-primary w-100 btn-dashboard" ><i class="ki-solid ki-setting-3 fs-1" style="margin-top: -5px;"></i> Dashboard</button>';
                        $fecha_proximo_cobro = date('d/m/Y', strtotime($modulos_pagados[$servicio['id_modulo']]['fecha_proximo_cobro']));
                        if($modulos_pagados[$servicio['id_modulo']]['estatus']=="activa"){
                            $estado = '<span class="badge badge-success badge_activa">Activa</span>';
                            $descripcion = '<span class="text-gray-600 text-end fw-bold fs-6">Valida hasta: '.$fecha_proximo_cobro.'</span>';
                        }

                    }

                    $html .= <<<HTML
                        <div class="row g-5 g-xl-8 mb-5">
                            <div class="card card-flush h-xl-100">
                                <div class="card-body text-center pb-5">
                                    {$estado}
                                    <img src="{$this->ruta}assets/img/servicios/{$img}" class="w-100 rounded mb-5" alt="{$img}">
                                    <div class="d-flex align-items-end flex-stack mb-1">
                                        <div class="text-start">
                                            <span class="fw-bold text-gray-800 cursor-pointer text-hover-primary fs-4 d-block">{$nombre}</span>
                                            {$descripcion}
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex flex-stack pt-0" data-id-servicio="{$id_servicio}">
                                    {$botones}
                                </div>
                            </div>
                        </div>
                    HTML;

                    // Información vista servicios_detalles
                    $planesPorIntervalo[$servicio['intervalo']][] = [
                        'id_plan'   => intval($servicio['id_plan']),
                        'precio'    => floatval($servicio['precio']),
                        'intervalo'     => $servicio['intervalo'],
                        'lista_beneficios'     => $servicio['lista_beneficios'],
                        'nombre_plan' => $servicio['nombre_plan'],
                        'descripcion' => $servicio['descripcion'],
                        'id_plan_openpay' =>$servicio['id_plan_openpay']
                    ];
                    $elementos[] = $servicio;
                }
            } else {
                $codigo  = "ERR";
                $mensaje = "Sin resultados que mostrar";
            }

            $data = [
                "mensaje" => $mensaje,
                "html" => $html,
                "elementos" => $elementos,
                "planes" => $planesPorIntervalo,
            ];

            return [$codigo, $data];

        }

    }

?>