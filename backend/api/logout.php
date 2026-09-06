<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';

revocarSesionApi($conexion, $contextoAutenticacion['id_sesion']);

echo json_encode([
  'status' => 'success',
  'mensaje' => 'La sesion se cerro correctamente'
], JSON_UNESCAPED_UNICODE);
