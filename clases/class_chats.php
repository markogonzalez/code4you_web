<?php 

    include_once 'clases/class_whats.php';
	include_once 'utilidades.php';

    class chats extends utilidades{

        private $whats = null;
        
        public function __construct() {
            parent::__construct();
            @$this->whats = new whats();
            
        } //function __construct

        public function getChats($params = null){

            $negocioConfiguracion = null;
            $codigo = "OK";
            $id_negocio    = $this->cleanQuery($params["id_negocio"] ?? 0);
            // 1. Lista de chats
            $queryChats = "SELECT c.*,ch.fecha_envio 
                        FROM negocio_clientes c 
                        LEFT JOIN (
                            SELECT nc1.id_cliente, nc1.fecha_envio
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

            while ($row = $resChats->fetch_assoc()) {
                $row["fecha_iso"] = date("c", strtotime($row['fecha_envio']));
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
                cl.nombre_whats,numero_whats
                FROM negocio_chats nc INNER JOIN negocio_clientes cl ON nc.id_cliente = cl.id_cliente
                WHERE nc.id_cliente = ".$id_cliente.$condicion." ORDER BY nc.id_mensaje ASC LIMIT 10";

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
            $destinatario    = $this->cleanQuery($params["destinatario"] ?? 0);
            $id_whats    = $this->cleanQuery($params["id_whats"] ?? 0);
            $mensaje    = $this->cleanQuery($params["mensaje"] ?? 0);
            
            // $this->guardarRespuestaWhats($id_cliente,$mensaje);
            list($codigo,$response) = $this->whats->enviarRespuesta([
                "destinatario" => "521".$destinatario,
                "tipo" => "text",
                "mensaje" => $mensaje,
                "id_whats" => $id_whats
            ]);
            

            return [$codigo,$response];
        }

    }

?>