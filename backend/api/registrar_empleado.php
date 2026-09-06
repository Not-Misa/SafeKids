<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$idGuarderia = isset($cuerpo['id_guarderia']) ? (int) $cuerpo['id_guarderia'] : 0;
$idSolicitante = isset($cuerpo['id_empleado']) ? (int) $cuerpo['id_empleado'] : 0;
$datosRecibidos = is_array($cuerpo['empleado'] ?? null) ? $cuerpo['empleado'] : [];

if ($idGuarderia <= 0 || $idSolicitante <= 0) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'No se recibieron los datos de la guarderia'
  ]);
}

$transaccionActiva = false;

try {
  $datos = datosEmpleadoValidados($datosRecibidos);
  if (!obtenerAdministrador($conexion, $idSolicitante, $idGuarderia)) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede registrar personal'
    ]);
  }

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $stmtCorreo = mysqli_prepare(
    $conexion,
    "SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtCorreo, 's', $datos['email']);
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

  $passwordTemporal = passwordTemporalEmpleado();
  $passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);
  $idRol = (int) $rol['id_rol'];
  $stmtUsuario = mysqli_prepare(
    $conexion,
    "INSERT INTO usuarios (
      id_guarderia,
      id_rol,
      email,
      password_hash,
      requiere_cambio_password,
      estado
    ) VALUES (?, ?, ?, ?, TRUE, 'ACTIVO')"
  );
  mysqli_stmt_bind_param(
    $stmtUsuario,
    'iiss',
    $idGuarderia,
    $idRol,
    $datos['email'],
    $passwordHash
  );
  mysqli_stmt_execute($stmtUsuario);
  $idUsuario = (int) mysqli_insert_id($conexion);

  $stmtEmpleado = mysqli_prepare(
    $conexion,
    "INSERT INTO empleados (
      id_usuario,
      nombres,
      apellidos,
      telefono,
      puesto,
      fecha_ingreso,
      notas
    ) VALUES (?, ?, ?, ?, ?, ?, ?)"
  );
  mysqli_stmt_bind_param(
    $stmtEmpleado,
    'issssss',
    $idUsuario,
    $datos['nombres'],
    $datos['apellidos'],
    $datos['telefono'],
    $datos['puesto'],
    $datos['fecha_ingreso'],
    $datos['notas']
  );
  mysqli_stmt_execute($stmtEmpleado);
  $idEmpleado = (int) mysqli_insert_id($conexion);

  mysqli_commit($conexion);
  $transaccionActiva = false;
  responderEmpleado(201, [
    'status' => 'success',
    'mensaje' => 'El empleado fue registrado correctamente',
    'id_empleado' => $idEmpleado,
    'credencial_temporal' => [
      'email' => $datos['email'],
      'password_temporal' => $passwordTemporal
    ]
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
    'mensaje' => 'No fue posible registrar al empleado'
  ]);
}
