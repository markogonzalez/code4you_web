<?php
$chargeId = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Redirigiendo… | Code4You</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Metronic CSS (usa tu bundle ya existente) -->
  <link href="https://preview.keenthemes.com/metronic8/demo1/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
  <link href="https://preview.keenthemes.com/metronic8/demo1/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />

  <style>
    body {
      background: #f5f8fa;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      font-family: "Inter", sans-serif;
    }
    .card {
      max-width: 500px;
      margin: auto;
    }
  </style>
</head>
<body>
  <div class="card shadow-sm">
    <div class="card-body p-10 text-center">

      <div class="alert alert-primary d-flex align-items-center p-5 mb-6">
        <i class="ki-duotone ki-loading fs-2hx text-primary me-4"></i>
        <div class="d-flex flex-column">
          <h4 class="mb-1 text-primary">Procesando tu autenticación</h4>
          <span>Estamos validando la operación con tu banco…</span>
        </div>
      </div>

      <p class="mb-6">En unos segundos te regresaremos automáticamente a la aplicación Code4You.</p>

      <!-- Botón de fallback -->
      <a href="code4you://callback?charge_id=<?=urlencode($chargeId)?>" 
         class="btn btn-lg btn-primary">
         Abrir la aplicación
      </a>

    </div>
  </div>

  <script>
    // 1. Intentar cerrar el navegador incrustado
    try { window.close(); } catch(e) {}

    // 2. Redirigir al deep link automáticamente
    setTimeout(function() {
      window.location.href = "code4you://callback?charge_id=<?=urlencode($chargeId)?>";
    }, 800);
  </script>
</body>
</html>