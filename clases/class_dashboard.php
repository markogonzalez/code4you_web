<?php 

    require_once "clases/autenticacion.php";
	include_once 'utilidades.php';

    class dashboard extends utilidades{

        private $id_modulo = 0;
        private $ruta = "./";
        private $permisos = [];
        
        public function __construct() {
            parent::__construct();
            error_log(print_r($this->sesion,true));
            if($this->sesion){
                $permisosArr = $this->setPermisosPerfil([
                    "id_modulo" => 6,
                    "perfil_id" => $this->sesion['perfil_id']
                ]);
                $this->permisos = $permisosArr;
            }
            $this->id_modulo = 6;

            $val =  autenticacion::validar();
            if ($val['code'] != 'OK') {
                return ['code' => 'Token', 'mensaje' => $val['mensaje']];
            }
            
        } //function __construct

        public function getResumen($params = null) {
            $codigo = "OK";
            $mensaje = "";
            $data = [];

            $id_negocio = isset($params["id_negocio"]) ? intval($params["id_negocio"]) : 0;
            if ($id_negocio <= 0) {
                return ["ERR", ["mensaje" => "Negocio no válido"]];
            }

            // 📊 Ventas del mes
            $qryVentas = "
                SELECT IFNULL(SUM(monto),0) as ventas_mes
                FROM negocio_pagos
                WHERE estatus = 'pagado'
                AND id_negocio = $id_negocio
                AND MONTH(fecha_pago) = MONTH(CURDATE())
                AND YEAR(fecha_pago) = YEAR(CURDATE())
            ";
            $res = $this->query($qryVentas);
            $ventas_mes = $res->fetch_assoc()["ventas_mes"] ?? 0;

            // 📅 Citas de hoy
            $qryCitasHoy = "
                SELECT COUNT(*) as total
                FROM negocio_citas
                WHERE id_negocio = $id_negocio
                AND DATE(fecha) = CURDATE()
                AND estatus = 'confirmada'
            ";
            $res = $this->query($qryCitasHoy);
            $citas_hoy = $res->fetch_assoc()["total"] ?? 0;

            // 👥 Clientes nuevos este mes
            $qryClientes = "
                SELECT COUNT(DISTINCT id_usuario) as total
                FROM negocio_citas
                WHERE id_negocio = $id_negocio
                AND MONTH(fecha_creacion) = MONTH(CURDATE())
                AND YEAR(fecha_creacion) = YEAR(CURDATE())
            ";
            $res = $this->query($qryClientes);
            $clientes_nuevos = $res->fetch_assoc()["total"] ?? 0;

            // 📅 Próximas citas (5)
            $citas = [];
            $qryProximas = "
                SELECT c.fecha, c.hora, u.nombre as cliente, ns.servicio
                FROM negocio_citas c
                INNER JOIN master_usuarios u ON u.id_usuario = c.id_usuario
                INNER JOIN negocio_servicios ns ON ns.id_servicio = c.id_servicio
                WHERE c.id_negocio = $id_negocio
                AND c.fecha >= CURDATE()
                ORDER BY c.fecha ASC, c.hora ASC
                LIMIT 5
            ";
            $res = $this->query($qryProximas);
            while($row = $res->fetch_assoc()) {
                $citas[] = [
                    "cliente" => $row["cliente"],
                    "servicio" => $row["servicio"],
                    "fecha" => $row["fecha"],
                    "hora" => $row["hora"]
                ];
            }

            // 💳 Ingresos por método de pago
            $qryMetodos = "
                SELECT mp.metodo_pago, SUM(p.monto) as total
                FROM negocio_pagos p
                INNER JOIN catalogo_metodo_pago mp ON mp.id_metodo = p.id_metodo
                WHERE p.estatus = 'pagado'
                AND p.id_negocio = $id_negocio
                AND MONTH(p.fecha_pago) = MONTH(CURDATE())
                AND YEAR(p.fecha_pago) = YEAR(CURDATE())
                GROUP BY mp.metodo_pago
            ";
            $metodos_pago = [];
            $res = $this->query($qryMetodos);
            while($row = $res->fetch_assoc()) {
                $metodos_pago[] = [
                    "metodo" => $row["metodo_pago"],
                    "total" => floatval($row["total"])
                ];
            }

            // 👥 Últimos clientes (los que más reciente tuvieron cita o pago)
            $qryUltimos = "
                SELECT DISTINCT u.nombre, DATE(MAX(c.fecha_creacion)) as fecha
                FROM negocio_citas c
                INNER JOIN master_usuarios u ON u.id_usuario = c.id_usuario
                WHERE c.id_negocio = $id_negocio
                GROUP BY u.id_usuario
                ORDER BY fecha DESC
                LIMIT 5
            ";
            $ultimos_clientes = [];
            $res = $this->query($qryUltimos);
            while($row = $res->fetch_assoc()) {
                $ultimos_clientes[] = [
                    "nombre" => $row["nombre"],
                    "fecha" => $row["fecha"]
                ];
            }

            // ⚡ Actividad reciente (últimos 5 pagos o citas)
            $actividad = [];
            $qryActividad = "
                SELECT 'Pago' as tipo, CONCAT('Pago de ', u.nombre, ' $', p.monto) as mensaje, DATE_FORMAT(p.fecha_pago, '%H:%i') as hora
                FROM negocio_pagos p
                INNER JOIN master_usuarios u ON u.id_usuario = p.id_usuario
                WHERE p.id_negocio = $id_negocio
                ORDER BY p.fecha_pago DESC
                LIMIT 3
            ";
            $res = $this->query($qryActividad);
            while($row = $res->fetch_assoc()) {
                $actividad[] = [
                    "hora" => $row["hora"],
                    "mensaje" => $row["mensaje"]
                ];
            }

            $qryActividad2 = "
                SELECT 'Cita' as tipo, CONCAT('Cita con ', u.nombre, ' - ', ns.servicio) as mensaje, DATE_FORMAT(c.fecha_creacion, '%H:%i') as hora
                FROM negocio_citas c
                INNER JOIN master_usuarios u ON u.id_usuario = c.id_usuario
                INNER JOIN negocio_servicios ns ON ns.id_servicio = c.id_servicio
                WHERE c.id_negocio = $id_negocio
                ORDER BY c.fecha_creacion DESC
                LIMIT 2
            ";
            $res = $this->query($qryActividad2);
            while($row = $res->fetch_assoc()) {
                $actividad[] = [
                    "hora" => $row["hora"],
                    "mensaje" => $row["mensaje"]
                ];
            }

            // 📦 Respuesta final
            $data = [
                "ventas_mes" => floatval($ventas_mes),
                "citas_hoy" => intval($citas_hoy),
                "clientes_nuevos" => intval($clientes_nuevos),
                "citas" => $citas,
                "metodos_pago" => $metodos_pago,
                "ultimos_clientes" => $ultimos_clientes,
                "actividad" => $actividad
            ];

            return [$codigo, $data];
        }

    }

?>