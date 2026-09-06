<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/reportes_utilidades.php';

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idEmpleado = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;

if ($idGuarderia <= 0 || $idEmpleado <= 0) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'No se recibieron los datos del usuario'
  ]);
}

try {
  if (!obtenerPersonalActivo($conexion, $idEmpleado, $idGuarderia)) {
    responderReporte(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar los tipos de reporte'
    ]);
  }

  $resultado = mysqli_query(
    $conexion,
    "SELECT
      id_tipo_evento,
      codigo,
      nombre,
      descripcion,
      requiere_detalle,
      permite_imagen
    FROM tipos_evento
    WHERE activo = TRUE
    ORDER BY id_tipo_evento"
  );

  $tipos = [];
  while ($tipo = mysqli_fetch_assoc($resultado)) {
    $tipos[] = [
      'id_tipo_evento' => (int) $tipo['id_tipo_evento'],
      'codigo' => $tipo['codigo'],
      'nombre' => $tipo['nombre'],
      'descripcion' => $tipo['descripcion'],
      'requiere_detalle' => (bool) $tipo['requiere_detalle'],
      'permite_imagen' => (bool) $tipo['permite_imagen']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'tipos' => $tipos
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderReporte(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar los tipos de reporte'
  ]);
}
