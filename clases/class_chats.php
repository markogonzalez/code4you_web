<?php 
    require_once "clases/autenticacion.php";
    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class chats extends utilidades{

        private $whats = null;
        
        public function __construct() {
            parent::__construct();
            @$this->whats = new whats();
            $val =  autenticacion::validar();
            if ($val['code'] != 'OK') {
                return ['code' => 'Token', 'mensaje' => $val['mensaje']];
            }
            
        } //function __construct

        public function getChats($params = null){

            $negocioConfiguracion = null;
            $codigo = "OK";
            $id_negocio    = $this->cleanQuery($params["id_negocio"] ?? 0);
            // 1. Lista de chats
            $queryChats = "SELECT c.*,ch.fecha_envio,ch.mensaje,ch.id_mensaje
                FROM negocio_clientes c 
                LEFT JOIN (
                    SELECT nc1.id_cliente, nc1.fecha_envio, nc1.mensaje,nc1.id_mensaje
                    FROM negocio_chats nc1
                    INNER JOIN (
                        SELECT id_cliente, MAX(id_mensaje) AS max_id
                        FROM negocio_chats
                        GROUP BY id_cliente
                    ) nc2 ON nc1.id_cliente = nc2.id_cliente AND nc1.id_mensaje = nc2.max_id
                ) ch ON c.id_cliente = ch.id_cliente
                WHERE id_negocio = $id_negocio";

            $resChats = $this->query($queryChats);
            if (!$resChats || $resChats->num_rows == 0) return ["ERR",["mensaje"=>"Sin chats"]];
            
            $queryNoLeidos = "SELECT id_cliente, COUNT(*) AS no_leidos 
                FROM negocio_chats 
                WHERE tipo = 'cliente' AND leido = 0 
                GROUP BY id_cliente";

            $resNoLeidos = $this->query($queryNoLeidos);
            $noLeidosPorCliente = [];

            if ($resNoLeidos && $resNoLeidos->num_rows > 0) {
                while ($row = $resNoLeidos->fetch_assoc()) {
                    $noLeidosPorCliente[$row["id_cliente"]] = $row["no_leidos"];
                }
            }
            while ($row = $resChats->fetch_assoc()) {
                $row["fecha_iso"] = date("c", strtotime($row['fecha_envio']));
                $row["no_leidos"] = $noLeidosPorCliente[$row["id_cliente"]] ?? 0;
                $chats[] = $row;
            }

            return [$codigo,$chats];
        }

        public function getChatsCliente($params = null){

            $negocioConfiguracion = null;
            $codigo = "OK";
            $id_mensaje    = $this->cleanQuery($params["id_mensaje"] ?? 0);
            $id_cliente    = $this->cleanQuery($params["id_cliente"] ?? 0);
            $condicion = "";
            $chats = null;

            // 1. Lista de chats
            if($id_mensaje > 0){
                $condicion = " AND nc.id_mensaje < ".$id_mensaje;
            }
            $queryChats = "SELECT nc.id_mensaje,nc.mensaje,nc.tipo,nc.fecha_envio,
                cl.nombre_whats,numero_whats,nc.estado_salida
                FROM negocio_chats nc INNER JOIN negocio_clientes cl ON nc.id_cliente = cl.id_cliente
                WHERE nc.id_cliente = ".$id_cliente.$condicion." ORDER BY nc.id_mensaje DESC LIMIT 10";

            $resChats = $this->query($queryChats);

            while ($row = $resChats->fetch_assoc()) {
                $row["fecha_iso"] = date("c", strtotime($row['fecha_envio']));
                $dt = new DateTime($row["fecha_iso"]);
                $row["fecha_mostrar"] = $dt->format('H:i');
                $chats[] = $row;
            }

            return [$codigo,$chats];
        }

        public function guardarChats($params = null){

            $negocioConfiguracion = null;
            $codigo = "OK";
            $destinatario = isset($params["destinatario"]) ? $params["destinatario"] : "";
            $id_whats = isset($params["id_whats"]) ? $params["id_whats"] : "";
            $mensaje = isset($params["mensaje"]) ? $params["mensaje"] : "";
            $id_cliente = isset($params["id_cliente"]) ? $params["id_cliente"] : 0;
            $id_negocio = isset($params["id_negocio"]) ? $params["id_negocio"] : 0;
            
            // $this->guardarRespuestaWhats($id_cliente,$mensaje);
            list($codigoMensaje,$response) = $this->whats->enviarRespuesta([
                "destinatario" => $destinatario,
                "tipo" => "text",
                "mensaje" => $mensaje,
                "id_whats" => $id_whats
            ]);

            if($codigoMensaje=="OK"){
                list($codigoGuardar,$id_mensaje) =$this->guardarRespuestaWhats([
                    "id_cliente" => $id_cliente,
                    "id_negocio" => $id_negocio,
                    "mensaje" => $mensaje,
                    "tipo" => "usuario",
                    "modulo_origen" => "usuario",
                    "tipo_whats" => "texto",
                    "mensaje_id_externo" => $response['messages'][0]['id'],0,
                    "metadata" => $response
                ]);
                if($codigoGuardar!="OK"){
                    $codigo = "ERR";
                    $response = "No se pudo guardar el mensaje";
                }
            }else{
                $codigo = "ERR";
                $response = "No se pudo enviar el mensaje";
            }
            

            return [$codigo,["id_mensaje"=>$id_mensaje,"mensaje"=>$response]];
        }

        public function marcarLeidos($params){
            $codigo = "OK";
            $data = "OK";
            $id_cliente = $this->cleanQuery($params["id_cliente"]);
            $sql = "UPDATE negocio_chats 
                    SET leido = 1 
                    WHERE id_cliente = $id_cliente AND tipo = 'cliente' AND leido = 0";
            if(!$this->query($sql)){
                $codigo = "ERR";
                $data = "Ocurrio un error al actualizar status de leido";
            }
            return [$codigo,$data];
        }

        public function intervenirChat($params){
            $codigo = "OK";
            $data = "OK";
            $id_cliente = $this->cleanQuery($params["id_cliente"]);
            $atencion = $this->cleanQuery($params["atencion"]);
            $sql = "UPDATE negocio_clientes 
                SET atencion = '$atencion' 
                WHERE id_cliente = $id_cliente";
            if(!$this->query($sql)){
                $codigo = "ERR";
                $data = "Ocurrio un error al actualizar status de atencion";
            }
            return [$codigo,$data];
        }

    }

?>