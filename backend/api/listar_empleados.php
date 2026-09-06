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

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idSolicitante = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;
$busqueda = trim((string) ($_GET['q'] ?? ''));
$estado = strtoupper(trim((string) ($_GET['estado'] ?? 'TODOS')));

if (
  $idGuarderia <= 0 ||
  $idSolicitante <= 0 ||
  !in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO', 'BLOQUEADO'], true)
) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Los filtros del listado no son validos'
  ]);
}

try {
  if (!obtenerAdministrador($conexion, $idSolicitante, $idGuarderia)) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede consultar al personal'
    ]);
  }

  $busquedaLike = '%' . $busqueda . '%';
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
      u.email,
      u.estado,
      u.requiere_cambio_password,
      u.ultimo_acceso,
      r.nombre AS rol
    FROM empleados e
    INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    WHERE u.id_guarderia = ?
      AND e.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
      AND r.nombre IN ('ADMIN', 'EMPLEADO')
      AND (? = 'TODOS' OR u.estado = ?)
      AND (
        ? = ''
        OR e.nombres LIKE ?
        OR e.apellidos LIKE ?
        OR CONCAT(e.nombres, ' ', e.apellidos) LIKE ?
        OR u.email LIKE ?
        OR e.puesto LIKE ?
      )
    ORDER BY
      CASE u.estado WHEN 'ACTIVO' THEN 0 WHEN 'BLOQUEADO' THEN 1 ELSE 2 END,
      e.apellidos,
      e.nombres
    LIMIT 150"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'issssssss',
    $idGuarderia,
    $estado,
    $estado,
    $busqueda,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike
  );
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $empleados = [];
  while ($empleado = mysqli_fetch_assoc($resultado)) {
    $empleados[] = [
      'id_empleado' => (int) $empleado['id_empleado'],
      'nombres' => $empleado['nombres'],
      'apellidos' => $empleado['apellidos'],
      'telefono' => $empleado['telefono'],
      'puesto' => $empleado['puesto'],
      'fecha_ingreso' => $empleado['fecha_ingreso'],
      'foto_url' => $empleado['foto_url'],
      'email' => $empleado['email'],
      'estado' => $empleado['estado'],
      'rol' => $empleado['rol'],
      'requiere_cambio_password' => (bool) $empleado['requiere_cambio_password'],
      'ultimo_acceso' => $empleado['ultimo_acceso'],
      'es_solicitante' => (int) $empleado['id_empleado'] === $idSolicitante
    ];
  }

  echo json_encode([
    'status' => 'success',
    'empleados' => $empleados
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderEmpleado(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar al personal'
  ]);
}
