<?php
require_once __DIR__ . '/../cors.php';
configurarCors('PATCH');

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/empleados_utilidades.php';

$cuerpo = json_decode(file_get_contents('php://input'), true);
$idObjetivo = isset($cuerpo['id_objetivo']) ? (int) $cuerpo['id_objetivo'] : 0;
$idGuarderia = isset($cuerpo['id_guarderia']) ? (int) $cuerpo['id_guarderia'] : 0;
$idSolicitante = isset($cuerpo['id_empleado']) ? (int) $cuerpo['id_empleado'] : 0;
$estado = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));

if (
  $idObjetivo <= 0 ||
  $idGuarderia <= 0 ||
  $idSolicitante <= 0 ||
  !in_array($estado, ['ACTIVO', 'INACTIVO'], true)
) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Los datos para cambiar el estado no son validos'
  ]);
}

try {
  if (!obtenerAdministrador($conexion, $idSolicitante, $idGuarderia)) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede cambiar el estado'
    ]);
  }

  if ($idObjetivo === $idSolicitante) {
    throw new DomainException('No puedes cambiar el estado de tu propia cuenta');
  }

  $stmtObjetivo = mysqli_prepare(
    $conexion,
    "SELECT u.id_usuario, u.estado
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
  mysqli_stmt_bind_param($stmtObjetivo, 'ii', $idObjetivo, $idGuarderia);
  mysqli_stmt_execute($stmtObjetivo);
  $objetivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtObjetivo));
  if (!$objetivo) {
    throw new DomainException('El empleado no pertenece a la guarderia');
  }

  $idUsuario = (int) $objetivo['id_usuario'];
  if ($estado === 'ACTIVO') {
    $stmtEstado = mysqli_prepare(
      $conexion,
      "UPDATE usuarios
       SET estado = 'ACTIVO', intentos_fallidos = 0, bloqueado_hasta = NULL
       WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtEstado, 'i', $idUsuario);
  } else {
    $stmtEstado = mysqli_prepare(
      $conexion,
      "UPDATE usuarios SET estado = 'INACTIVO' WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtEstado, 'i', $idUsuario);
  }
  mysqli_stmt_execute($stmtEstado);

  if ($estado === 'INACTIVO') {
    revocarSesionesUsuario($conexion, $idUsuario);
  }

  responderEmpleado(200, [
    'status' => 'success',
    'mensaje' => $estado === 'ACTIVO'
      ? 'El empleado fue reactivado correctamente'
      : 'El empleado fue dado de baja correctamente',
    'estado' => $estado
  ]);
} catch (DomainException $e) {
  responderEmpleado(422, [
    'status' => 'error',
    'mensaje' => $e->getMessage()
  ]);
} catch (mysqli_sql_exception $e) {
  responderEmpleado(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible cambiar el estado'
  ]);
}
