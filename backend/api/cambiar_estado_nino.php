<?php
require_once __DIR__ . '/../cors.php';
configurarCors('PATCH');

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Metodo no permitido'
  ]);
  exit();
}

require_once __DIR__ . '/../conexion.php';

$datos = json_decode(file_get_contents('php://input'), true);
$idNino = isset($datos['id_nino']) ? (int) $datos['id_nino'] : 0;
$idGuarderia = isset($datos['id_guarderia']) ? (int) $datos['id_guarderia'] : 0;
$idEmpleado = isset($datos['id_empleado']) ? (int) $datos['id_empleado'] : 0;
$estado = strtoupper(trim($datos['estado'] ?? ''));

if (
  $idNino <= 0 ||
  $idGuarderia <= 0 ||
  $idEmpleado <= 0 ||
  !in_array($estado, ['ACTIVO', 'INACTIVO'], true)
) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Los datos para cambiar el estado no son validos'
  ]);
  exit();
}

try {
  $stmtAdministrador = mysqli_prepare(
    $conexion,
    "SELECT e.id_empleado
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND r.nombre = 'ADMIN'
       AND u.estado = 'ACTIVO'
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtAdministrador, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmtAdministrador);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtAdministrador))) {
    http_response_code(403);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede cambiar el estado'
    ]);
    exit();
  }

  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE ninos
     SET estado = ?
     WHERE id_nino = ?
       AND id_guarderia = ?
       AND eliminado_en IS NULL"
  );
  mysqli_stmt_bind_param($stmt, 'sii', $estado, $idNino, $idGuarderia);
  mysqli_stmt_execute($stmt);

  if (mysqli_stmt_affected_rows($stmt) === 0) {
    $stmtExiste = mysqli_prepare(
      $conexion,
      "SELECT estado
       FROM ninos
       WHERE id_nino = ?
         AND id_guarderia = ?
         AND eliminado_en IS NULL
       LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtExiste, 'ii', $idNino, $idGuarderia);
    mysqli_stmt_execute($stmtExiste);
    $nino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtExiste));

    if (!$nino) {
      http_response_code(404);
      echo json_encode([
        'status' => 'error',
        'mensaje' => 'El nino no pertenece a la guarderia'
      ]);
      exit();
    }

  }

  echo json_encode([
    'status' => 'success',
    'mensaje' => $estado === 'ACTIVO'
      ? 'El nino fue reactivado correctamente'
      : 'El nino fue dado de baja correctamente',
    'estado' => $estado
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible cambiar el estado del nino'
  ]);
}
