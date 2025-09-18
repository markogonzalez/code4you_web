<?php 
    require_once "clases/autenticacion.php";
    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class servicios extends utilidades{
        private $whats = null;
        private $id_modulo = 0;
        private $ruta = "./";
        private $permisos = [];
        
        public function __construct() {
            parent::__construct();
            if($this->sesion){
                $permisosArr = $this->setPermisosPerfil([
                    "id_modulo" => 4,
                    "perfil_id" => $this->sesion['perfil_id']
                ]);
                $this->permisos = $permisosArr;
            }
            @$this->whats = new whats();
            $this->id_modulo = 4;

            $val =  autenticacion::validar();
            if ($val['code'] != 'OK') {
                return ['code' => 'Token', 'mensaje' => $val['mensaje']];
            }
            
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
                                    <img src="assets/img/servicios/{$img}" class="w-100 rounded mb-5" alt="{$img}">
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

        public function getMetodoPago($params=null){
            
            $codigo = "OK";
            $mensaje = "";
            $data = [];
            $elementos = [];
            
            $query ="SELECT *
                FROM catalogo_metodo_pago
                WHERE status = 1";

            $result = $this->query($query);
          
            if ($result->num_rows > 0) {
                while ($metodo = $result->fetch_assoc()) {
                    $elementos[] = $metodo;
                }
            } else {
                $codigo  = "ERR";
                $mensaje = "Sin resultados que mostrar";
            }

            $data = [
                "mensaje" => $mensaje,
                "elementos" => $elementos,
            ];

            return [$codigo, $data];

        }

        public function getPlan($params=null){
            
            $codigo = "OK";
            $mensaje = "";
            $metodos = [];
            $elementos = [];
            $html = "";

            $id_plan    = $this->cleanQuery($params["id_plan"] ?? 0);
            
            $query ="SELECT 
            sp.id_servicio,
            sp.nombre_plan,
            sp.descripcion,
            sp.intervalo,
            sp.precio,
            sp.id_plan
                FROM servicios_planes sp
                WHERE sp.id_plan = ".$id_plan;
            
            $qry_metodo = "SELECT * FROM catalogo_metodo_pago WHERE status =1";

            $result = $this->query($query);
            $result_metodo = $this->query($qry_metodo);
            if ($result->num_rows > 0 && $result_metodo->num_rows > 0) {
                $plan = $result->fetch_assoc();
                while ($row = $result_metodo->fetch_assoc()) {
                    $metodos[] = $row;
                }
                
                $metodos_html = "";
                foreach ($metodos as $metodo) {
                    $formTarjeta = "";
                    $activo = "";
                    if($metodo['id_metodo']==1){
                        // $activo = "border-primary";
                        $year = intval(date("Y"));
                        $opciones_year = "<option value=''>Año</option>";
                        for ($i=0; $i <=10; $i++) {
                            $year_n = $year+$i;
                            substr($year_n,1);
                            $opciones_year .= "<option value='".substr($year_n,2)."'>".$year_n."</option>";
                        }
                        $formTarjeta = '
                        <div class="mt-3" id="formTarjeta">
                            <input type="hidden" name="token_id" id="token_id">
                            <input type="hidden" name="id_plan" id="id_plan">
                            <input type="hidden" name="id_plan_openpay" id="id_plan_openpay">

                            <div class="d-flex flex-column mb-7 fv-row">
                                <input type="text" name
                                ="titular" class="form-control form-control-solid titular" placeholder="Nombre en tarjeta" data-openpay-card="holder_name" autocomplete="off" />
                            </div>

                            <div class="d-flex flex-column mb-7 fv-row">
                            <input type="text" name
                            ="card_number" class="form-control form-control-solid" placeholder="0000 0000 0000 0000" data-openpay-card="card_number" autocomplete="off" pattern="\d{16}" inputmode="numeric"/>
                            </div>

                            <div class="row mb-7">
                                <div class="col-md-8 fv-row">
                                    <div class="row fv-row">
                                        <div class="col-6">
                                            <select name
                                            ="card_expiry_month" class="form-select form-select-solid"
                                                data-openpay-card="expiration_month" required>
                                                <option value="">Mes</option>
                                                <option value="01">01</option>
                                                <option value="02">02</option>
                                                <option value="03">03</option>
                                                <option value="04">04</option>
                                                <option value="05">05</option>
                                                <option value="06">06</option>
                                                <option value="07">07</option>
                                                <option value="08">08</option>
                                                <option value="09">09</option>
                                                <option value="10">10</option>
                                                <option value="11">11</option>
                                                <option value="12">12</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <select name
                                            ="card_expiry_year" class="form-select form-select-solid"
                                                data-openpay-card="expiration_year" data-control="select2"
                                                data-hide-search="true" data-placeholder="Año" required>
                                                '.$opciones_year.'
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-column mb-7 fv-row">
                                <div class="col-md-4 fv-row">
                                    <div class="position-relative">
                                        <input type="text" class="form-control form-control-solid"
                                            placeholder="CVV" autocomplete="off"
                                            name
                                            ="card_cvv"
                                            data-openpay-card="cvv2" minlength="3" maxlength="4"
                                            pattern="\d{3,4}" inputmode="numeric" required />
                                        <div class="position-absolute translate-middle-y top-50 end-0 me-3">
                                            <i class="ki-duotone ki-credit-cart fs-2hx">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-column mb-7 fv-row">
                                <div>
                                    <h5>Transacciones realizadas vía:</h5>
                                    <img src="assets/img/metodos_pago/openpay.png">
                                </div>
                                <div class="mt-3">
                                    <p class="text-bold">Tus pagos se realizan de forma segura con encriptación de 256 bits</p>
                                    <img src="assets/img/metodos_pago/security.png">
                                </div>
                            </div>
                        </div>';
                    }
                    $metodos_html .= '<div class="col-md-4">
                                <div class="card h-100 cursor-pointer metodo-pago '.$activo.'" data-metodo="'.$metodo['id_metodo'].'">
                                    <div class="card-body text-center">
                                    <img src="'.$this->ruta.'/assets/img/metodos_pago/'.$metodo['imagen'].'" class="w-100 mb-3" >
                                    <h5 class="fw-bold">'.$metodo['metodo_pago'].'</h5>
                                    <div class="text-muted">'.$metodo['descripcion'].'</div>
                                    
                                    </div>
                                </div>
                            </div>';
                }

                $html .= <<<HTML
                        <form class="card card-flush py-4 flex-row-fluid" id="form_pago">
                            <input type="hidden" name="id_servicio" id="id_servicio" value="{$plan['id_servicio']}">
                            <div class="card-body pt-0">
                            <div class="mb-5">
                                <h3 class="fw-bold mb-2">{$plan['nombre_plan']}</h3>
                                <div class="text-gray-600 mb-1" >{$plan['descripcion']}</div>
                                <div class="fs-2 fw-bold text-primary" >\${$plan['precio']} MXN</div>
                            </div>

                            <h4 class="mb-4">Selecciona tu método de pago:</h4>
                            <input type="hidden" id="metodo_pago" value="1" name="metodo_pago">
                            <div class="row g-5">
                                {$metodos_html}
                                <div class="mb-5">
                                    <h4 class="mb-2">¿Requieres factura?</h4>
                                    <select name="requiere_factura" class="form-control" id="requiere_factura" required>
                                        <option value="">Selecciona una opción</option>
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </select>
                                </div>

                                <!-- Datos fiscales (ocultos hasta que diga que sí) -->
                                <div id="datos_fiscales" class="mb-5" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label">RFC</label>
                                        <input type="text" name="rfc" class="form-control" />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Razón Social</label>
                                        <input type="text" name="razon_social" class="form-control" />
                                    </div>
                                    <div class="mb-3">
                                        <!-- <label class="form-label">Constancia fiscal (PDF)</label>
                                        <input type="file" name="constancia" class="form-control" accept=".pdf" /> -->
                                        <button type="button" id="btnSeleccionarConstancia">Subir constancia fiscal</button>
                                        <input type="hidden" id="ruta_constancia" name="ruta_constancia">
                                        <span id="nombre_constancia"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-0 ">
                                <button type="button" class="btn btn-sm btn-primary w-100 btn_crear" ><i class="ki-solid ki-handcart fs-1" style="margin-top: -5px;"></i> Crear suscripción</button>
                            </div>
                            </div>
                        </form> 
                    HTML;
            } else {
                $codigo  = "ERR";
                $mensaje = "Sin resultados que mostrar";
            }

            $data = [
                "mensaje" => $mensaje,
                "html" => $html,
            ];

            return [$codigo, $data];

        }

        public function crearSuscripcionCon3DS($params = null){
            $codigo    = "OK";
            $mensaje   = "";
            $id_openpay= null;
            $url_3ds   = null;

            $token_openpay = isset($params["token_id"]) ? $this->cleanQuery($params["token_id"]) : "";
            $device_session_id = isset($params["deviceIdHiddenFieldName"]) ? $this->cleanQuery($params["deviceIdHiddenFieldName"]) : "";
            $id_plan = isset($params["id_plan"]) ? $this->cleanQuery($params["id_plan"]) : 0;
            $id_servicio = isset($params["id_servicio"]) ? $this->cleanQuery($params["id_servicio"]) : 0;
            $id_plan_openpay = isset($params["id_plan_openpay"]) ? $this->cleanQuery($params["id_plan_openpay"]) : "";
            $precio = isset($params["precio"]) ? $this->cleanQuery($params["precio"]) : 0;
            $id_metodo_pago = isset($params["id_metodo_pago"]) ? $this->cleanQuery($params["id_metodo_pago"]) : 0;

            if (empty($this->sesion)) {
                $codigo  = "SESION";
                $mensaje = "Inicia sesión para continuar";
            } else {
                try {
                    $id_usuario = $this->sesion['id_usuario'];
                    $usuario    = $this->getUsuario(['id_usuario'=>$id_usuario]);
                    $id_openpay = $usuario['id_openpay'];

                    // 1. Crear cliente si no existe
                    if (empty($id_openpay)) {
                        $add = $this->addOpenpay([
                            "cliente"=>[
                                'external_id' => $usuario['id_usuario'],
                                'name' => $usuario['nombre'],
                                'last_name' => $usuario['apellidos'],
                                'email' => $usuario['correo'],
                                'requires_account' => false,
                                'phone_number' => $usuario['celular'],
                            ],
                            "usuario"=>$usuario
                        ]);
                        if($add[0]!="OK"){
                            $codigo  = "ERR";
                            $mensaje = $add[1]['mensaje'];
                        } else {
                            $id_openpay = $add[1]['id_openpay'];
                        }
                    }

                    if ($codigo == "OK") {
                        $openpay  = $this->openpay();
                        $customer = $openpay->customers->get($id_openpay);

                        // 2. Cargo inicial
                        $chargeRequest = [
                            'method' => 'card',
                            'source_id' => $token_openpay,
                            'amount' => $precio, // puedes poner 1 si solo validas
                            'currency' => 'MXN',
                            'description' => 'Cargo inicial para suscripción',
                            'device_session_id' => $device_session_id,
                            'use_3d_secure' => true,
                            'redirect_url' => 'https://codeforyou.com.mx/dev/app_code4you/callback.php'
                        ];

                        $charge = $customer->charges->create($chargeRequest);

                        // 3. Manejo de estados
                        switch ($charge->status) {
                            case "in_progress":
                                $codigo  = "3DS_REQUIRED";
                                $mensaje = "Redirigir al banco para autenticación";
                                $url_3ds = $charge->payment_method->url ?? null;
                                // Inserta como pendiente
                                $this->query("INSERT INTO master_pagos (
                                    id_usuario, id_servicio, id_plan, id_metodo, monto, referencia, estatus, fecha_pago, device_id
                                ) VALUES (
                                    '$id_usuario','$id_servicio','$id_plan','$id_metodo_pago','$precio',
                                    '{$charge->id}','pendiente','".date("Y-m-d H:i:s")."','$device_session_id'
                                )");
                                break;

                            case "charge_pending":
                                if (isset($charge->payment_method->url)) {
                                    // 🔹 Caso con URL → igual que in_progress
                                    $codigo  = "3DS_REQUIRED";
                                    $mensaje = "Redirigir al banco para autenticación";
                                    $url_3ds = $charge->payment_method->url;
                                } else {
                                    // 🔹 Caso sin URL → esperar webhook
                                    $codigo  = "PENDIENTE";
                                    $mensaje = "El cargo está pendiente de validación bancaria.";
                                }
                                // Inserta como pendiente
                                $this->query("INSERT INTO master_pagos (
                                    id_usuario, id_servicio, id_plan, id_metodo, monto, referencia, estatus, fecha_pago, device_id
                                ) VALUES (
                                    '$id_usuario','$id_servicio','$id_plan','$id_metodo_pago','$precio',
                                    '{$charge->id}','pendiente','".date("Y-m-d H:i:s")."','$device_session_id'
                                )");
                                break;

                            case "completed":
                                // Guardar tarjeta
                                $tarjeta = $this->cardOpenpay([
                                    'token_id'          => $token_openpay,
                                    'device_session_id' => $device_session_id,
                                    "id_openpay"        => $id_openpay,
                                    "id_usuario"        => $id_usuario,
                                ]);
                                if ($tarjeta[0]!="OK") {
                                    $codigo  = "ERR";
                                    $mensaje = $tarjeta[1]['mensaje'];
                                    break;
                                }

                                $id_tarjeta = $tarjeta[1]['id_tarjeta'];

                                // Crear suscripción
                                $subscriptionDataRequest = [
                                    "trial_end_date" => date("Y-m-d"),
                                    'plan_id'        => $id_plan_openpay,
                                    'card_id'        => $id_tarjeta
                                ];
                                $subscription  = $customer->subscriptions->add($subscriptionDataRequest);

                                $estatus_sus   = $subscription->status == 'active' ? 'activa' : 'error';
                                $fecha_proximo = $subscription->charge_date ?? null;

                                $this->query("INSERT INTO servicios_suscripciones (
                                    id_usuario, id_plan, id_openpay_suscripcion, id_tarjeta, estatus, fecha_inicio, fecha_proximo_cobro
                                ) VALUES (
                                    '$id_usuario','$id_plan','{$subscription->id}','$id_tarjeta','$estatus_sus',
                                    '{$subscription->trial_end_date}','$fecha_proximo'
                                )");

                                $this->query("INSERT INTO master_pagos (
                                    id_usuario, id_servicio, id_plan, id_metodo, monto, referencia, estatus, fecha_pago, device_id
                                ) VALUES (
                                    '$id_usuario','$id_servicio','$id_plan','$id_metodo_pago','$precio',
                                    '{$subscription->id}','pagado','".date("Y-m-d H:i:s")."','$device_session_id'
                                )");

                                $mensaje = "Suscripción creada exitosamente con 3D Secure.";
                                break;

                            case "failed":
                                $codigo  = "ERR";
                                $mensaje = "El cargo fue rechazado por el banco.";
                                break;

                            case "cancelled":
                                $codigo  = "ERR";
                                $mensaje = "El cargo fue cancelado.";
                                break;

                            case "refunded":
                                $codigo  = "ERR";
                                $mensaje = "El cargo fue reembolsado.";
                                break;

                            default:
                                $codigo  = "ERR";
                                $mensaje = "Estado inesperado: ".$charge->status;
                                break;
                        }
                    }

                } catch (Exception $e) {
                    $codigo  = "ERR";
                    $mensaje = "Tarjeta Rechazada";
                    error_log("Error inesperado: ".$e->getMessage());
                }
            }

            return [$codigo, [
                "mensaje"   => $mensaje,
                "id_openpay"=> $id_openpay,
                "url_3ds"   => $url_3ds
            ]];
        }

        public function validarSubscripcion($params = null){
            $codigo = "OK";
            $mensaje = "";
            $elementos = [];
        }

        public function estadoPago($params = null){
            $codigo  = "OK";
            $mensaje = "";
            $estatus = "pendiente";

            try {
                $referencia = $this->cleanQuery($params['referencia'] ?? "");
                if (empty($referencia)) {
                    $codigo  = "ERR";
                    $mensaje = "Referencia requerida";
                } else {
                    $query_pago = "SELECT estatus FROM master_pagos WHERE referencia = '$referencia' ORDER BY id_pago DESC LIMIT 1";
                    $res = $this->query($query_pago);
                    if ($res->num_rows>0) {
                        $pago = $res->fetch_assoc();
                        $estatus = $pago['estatus'];
                    } else {
                        $codigo  = "ERR";
                        $mensaje = "No se encontró la referencia.";
                    }
                }
            } catch (Exception $e) {
                $codigo  = "ERR";
                $mensaje = "Error: ".$e->getMessage();
            }

            return [$codigo, ["mensaje"=>$mensaje, "estatus"=>$estatus]];
        }

        public function eliminarUsuario($params = null){
            $codigo  = "OK";
            $mensaje = "";

            if (!empty($this->sesion['permisos'][$this->id_modulo]['d'])) {
                $id_usuario = intval($params['id_usuario'] ?? 0);
                if ($id_usuario > 0) {
                    $query_borrar = "UPDATE master_usuarios SET status = '0' WHERE id_usuario = $id_usuario";
                    try {
                        $this->query($query_borrar);
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
        
        function addOpenpay($params = null){
            // error_log(print_r($params,true));
            
            $codigo = "OK";
            $mensaje = "";
            $id_openpay = "";
            $data = [];
            $cliente = $params['cliente'];
            $usuario = $params['usuario'];
            try {
                $openpay = $this->openpay();
                $customer = $openpay->customers->add($cliente);
                $qry_update = "UPDATE master_usuarios SET id_openpay = '".$customer->id."'  WHERE id_usuario = ".$usuario['id_usuario'];
                $res = $this->query($qry_update);

                $id_openpay = $customer->id;


            } catch (OpenpayApiTransactionError $e) {
                error_log("[Openpay - TransactionError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "No se pudo completar la operación. Intenta más tarde.";

            } catch (OpenpayApiRequestError $e) {
                error_log("[Openpay - RequestError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "Hubo un error en la solicitud. Verifica tus datos.";

            } catch (OpenpayApiConnectionError $e) {
                error_log("[Openpay - ConnectionError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "No pudimos conectar con el servicio de pagos. Intenta más tarde.";

            } catch (OpenpayApiAuthError $e) {
                error_log("[Openpay - AuthError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "Error de autenticación con Openpay. Contacta a soporte.";

            } catch (OpenpayApiError $e) {
                error_log("[Openpay - ApiError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "Ocurrió un error inesperado. Intenta nuevamente.";

            } catch (Exception $e) {
                error_log("[Openpay - GeneralError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = $e->getMessage();
            }

            return [$codigo,[
                "mensaje" => $mensaje,
                "id_openpay" =>$id_openpay,
            ]];
        }

        function cardOpenpay($params = null){
            
            $codigo = "OK";
            $mensaje = "";
            $id_tarjeta = "";
            $data = [];
            $cardData = [
                "token_id" => $params['token_id'],
                "device_session_id" => $params['device_session_id'],
            ];
            $id_openpay = $params['id_openpay'];
            $id_usuario = $params['id_usuario'];
            try {
                $openpay = $this->openpay();
                $customer = $openpay->customers->get($id_openpay);
                $card = $customer->cards->add($cardData);
                $id_tarjeta = $card->id;

                $qry_insert = "INSERT INTO usuario_tarjeta (
                        id_usuario, 
                        id_tarjeta) VALUES (
                        '{$id_usuario}',
                        '{$id_tarjeta}')";
                $res = $this->query($qry_insert);


            } catch (Exception $e) {
                error_log("[Openpay - GeneralError] " . $e->getMessage());
                $codigo = "ERROR";
                $mensaje = "Tarjeta Rechazada";
            }

            return [$codigo,[
                "mensaje" => $mensaje,
                "id_tarjeta" =>$id_tarjeta,
            ]];
        }
        

    }

?>