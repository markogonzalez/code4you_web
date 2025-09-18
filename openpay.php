<?php
// Aquí puedes loguear para debug
// file_put_contents(__DIR__.'/cb_log.txt', date('c').' '.json_encode($_GET).PHP_EOL, FILE_APPEND);

// Redirigir al esquema de tu app
header("Location: code4you://callback");
exit;