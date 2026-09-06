<?php
require_once __DIR__ . '/../cors.php';
configurarCors('PUT');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/empleados_utilidades.php';

$cuerpo = json_decode(file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'El contenido de la solicitud no es valido'
  ]);
}

$idObjetivo = isset($cuerpo['id_objetivo']) ? (int) $cuerpo['id_objetivo'] : 0;
$idGuarderia = isset($cuerpo['id_guarderia']) ? (int) $cuerpo['id_guarderia'] : 0;
$idSolicitante = isset($cuerpo['id_empleado']) ? (int) $cuerpo['id_empleado'] : 0;
$datosRecibidos = is_array($cuerpo['empleado'] ?? null) ? $cuerpo['empleado'] : [];

if ($idObjetivo <= 0 || $idGuarderia <= 0 || $idSolicitante <= 0) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Los datos del empleado no son validos'
  ]);
}

$transaccionActiva = false;

try {
  $datos = datosEmpleadoValidados($datosRecibidos);
  if (!obtenerAdministrador($conexion, $idSolicitante, $idGuarderia)) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede editar personal'
    ]);
  }

  if ($idObjetivo === $idSolicitante && $datos['rol'] !== 'ADMIN') {
    throw new DomainException('No puedes quitarte tu propio rol de administrador');
  }

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $stmtActual = mysqli_prepare(
    $conexion,
    "SELECT e.id_usuario
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
       AND r.nombre IN ('ADMIN', 'EMPLEADO')
     LIMIT 1
     FOR UPDATE"
  );
  mysqli_stmt_bind_param($stmtActual, 'ii', $idObjetivo, $idGuarderia);
  mysqli_stmt_execute($stmtActual);
  $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));
  if (!$actual) {
    throw new DomainException('El empleado no pertenece a la guarderia');
  }
  $idUsuario = (int) $actual['id_usuario'];

  $stmtCorreo = mysqli_prepare(
    $conexion,
    "SELECT id_usuario
     FROM usuarios
     WHERE email = ? AND id_usuario <> ?
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtCorreo, 'si', $datos['email'], $idUsuario);
  mysqli_stmt_execute($stmtCorreo);
  if (mysqli_fetch_row(mysqli_stmt_get_result($stmtCorreo))) {
    throw new DomainException('El correo ya esta registrado');
  }

  $stmtRol = mysqli_prepare(
    $conexion,
    "SELECT id_rol FROM roles WHERE nombre = ? LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtRol, 's', $datos['rol']);
  mysqli_stmt_execute($stmtRol);
  $rol = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtRol));
  if (!$rol) {
    throw new DomainException('El rol seleccionado no existe');
  }
  $idRol = (int) $rol['id_rol'];

  $stmtUsuario = mysqli_prepare(
    $conexion,
    "UPDATE usuarios SET email = ?, id_rol = ? WHERE id_usuario = ?"
  );
  mysqli_stmt_bind_param($stmtUsuario, 'sii', $datos['email'], $idRol, $idUsuario);
  mysqli_stmt_execute($stmtUsuario);

  $stmtEmpleado = mysqli_prepare(
    $conexion,
    "UPDATE empleados
     SET nombres = ?,
         apellidos = ?,
         telefono = ?,
         puesto = ?,
         fecha_ingreso = ?,
         notas = ?
     WHERE id_empleado = ?"
  );
  mysqli_stmt_bind_param(
    $stmtEmpleado,
    'ssssssi',
    $datos['nombres'],
    $datos['apellidos'],
    $datos['telefono'],
    $datos['puesto'],
    $datos['fecha_ingreso'],
    $datos['notas'],
    $idObjetivo
  );
  mysqli_stmt_execute($stmtEmpleado);

  mysqli_commit($conexion);
  $transaccionActiva = false;
  responderEmpleado(200, [
    'status' => 'success',
    'mensaje' => 'El perfil fue actualizado correctamente',
    'id_empleado' => $idObjetivo
  ]);
} catch (DomainException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  responderEmpleado(422, [
    'status' => 'error',
    'mensaje' => $e->getMessage()
  ]);
} catch (mysqli_sql_exception $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  responderEmpleado(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible actualizar el perfil'
  ]);
}
