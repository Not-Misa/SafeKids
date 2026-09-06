<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/empleados_utilidades.php';

$idEmpleado = isset($_GET['id_objetivo']) ? (int) $_GET['id_objetivo'] : 0;
$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idSolicitante = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;

if ($idEmpleado <= 0 || $idGuarderia <= 0 || $idSolicitante <= 0) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Los datos del perfil no son validos'
  ]);
}

try {
  $solicitante = obtenerPersonalEmpleado($conexion, $idSolicitante, $idGuarderia);
  $puedeConsultar = (
    $solicitante !== null &&
    (
      $solicitante['rol'] === 'ADMIN' ||
      $idEmpleado === $idSolicitante
    )
  );

  if (!$puedeConsultar) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar este perfil'
    ]);
  }

  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      e.id_empleado,
      e.nombres,
      e.apellidos,
      e.telefono,
      e.puesto,
      e.fecha_ingreso,
      e.foto_url,
      e.notas,
      e.creado_en,
      e.actualizado_en,
      u.email,
      u.estado,
      u.requiere_cambio_password,
      u.ultimo_acceso,
      r.nombre AS rol
    FROM empleados e
    INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    WHERE e.id_empleado = ?
      AND u.id_guarderia = ?
      AND e.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
      AND r.nombre IN ('ADMIN', 'EMPLEADO')
    LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $empleado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  if (!$empleado) {
    responderEmpleado(404, [
      'status' => 'error',
      'mensaje' => 'El perfil no existe o no pertenece a esta guarderia'
    ]);
  }

  echo json_encode([
    'status' => 'success',
    'empleado' => [
      'id_empleado' => (int) $empleado['id_empleado'],
      'nombres' => $empleado['nombres'],
      'apellidos' => $empleado['apellidos'],
      'telefono' => $empleado['telefono'],
      'puesto' => $empleado['puesto'],
      'fecha_ingreso' => $empleado['fecha_ingreso'],
      'foto_url' => $empleado['foto_url'],
      'notas' => $empleado['notas'],
      'email' => $empleado['email'],
      'estado' => $empleado['estado'],
      'rol' => $empleado['rol'],
      'requiere_cambio_password' => (bool) $empleado['requiere_cambio_password'],
      'ultimo_acceso' => $empleado['ultimo_acceso'],
      'creado_en' => $empleado['creado_en'],
      'actualizado_en' => $empleado['actualizado_en'],
      'es_solicitante' => (int) $empleado['id_empleado'] === $idSolicitante
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderEmpleado(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar el perfil'
  ]);
}
