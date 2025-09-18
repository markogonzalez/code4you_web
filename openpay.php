<?php
// callback.php
// Este archivo se abre cuando OpenPay termina la autenticación 3DS.
// No necesitas lógica pesada aquí, solo mostrar un mensaje claro.
// Tu app en Capacitor detectará la carga de esta URL y cerrará el Browser.

$status  = isset($_GET['status']) ? $_GET['status'] : 'success'; // opcional
$mensaje = $status === 'success'
    ? '✅ Tu validación 3D Secure ha sido completada.'
    : '❌ Hubo un problema en la validación 3D Secure.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <title>Validación 3D Secure | Code4You</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://preview.keenthemes.com/metronic8/demo1/assets/plugins/global/plugins.bundle.css" rel="stylesheet" />
    <link href="https://preview.keenthemes.com/metronic8/demo1/assets/css/style.bundle.css" rel="stylesheet" />
</head>
<body class="d-flex flex-center bgi-size-cover bgi-position-center bgi-no-repeat" style="background-color:#f9fafb;">
    <div class="card shadow-sm w-100 w-md-500px p-10">
        <div class="text-center mb-5">
            <i class="ki-duotone ki-credit-cart fs-3hx text-primary mb-3">
                <span class="path1"></span><span class="path2"></span>
            </i>
            <h2 class="fw-bold text-gray-800">Validación de pago</h2>
            <p class="text-muted fs-6"><?= $mensaje ?></p>
        </div>
        <div class="text-center">
            <button onclick="cerrarVentana()" class="btn btn-primary">
                <i class="ki-solid ki-check-circle fs-2 me-2"></i> Regresar a la aplicación
            </button>
        </div>
    </div>

    <script>
        function cerrarVentana(){
            window.close();
        }
    </script>
</body>
</html>