<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
  responderAutenticacion(400, 'El contenido de la solicitud no es valido');
}

$passwordActual = (string) ($datos['password_actual'] ?? '');
$passwordNueva = (string) ($datos['password_nueva'] ?? '');
$confirmacion = (string) ($datos['confirmacion_password'] ?? '');

if ($passwordActual === '' || $passwordNueva === '' || $confirmacion === '') {
  responderAutenticacion(400, 'Completa todos los campos de contrasena');
}

if (!hash_equals($passwordNueva, $confirmacion)) {
  responderAutenticacion(422, 'La confirmacion no coincide con la nueva contrasena');
}

$errorPolitica = errorPoliticaPassword($passwordNueva);
if ($errorPolitica !== null) {
  responderAutenticacion(422, $errorPolitica);
}

$idUsuario = (int) $contextoAutenticacion['id_usuario'];
$stmtUsuario = mysqli_prepare(
  $conexion,
  "SELECT password_hash FROM usuarios
   WHERE id_usuario = ? AND estado = 'ACTIVO' AND eliminado_en IS NULL
   LIMIT 1"
);
mysqli_stmt_bind_param($stmtUsuario, 'i', $idUsuario);
mysqli_stmt_execute($stmtUsuario);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

if (!$usuario || !password_verify($passwordActual, $usuario['password_hash'])) {
  responderAutenticacion(401, 'La contrasena actual no es correcta');
}

if (password_verify($passwordNueva, $usuario['password_hash'])) {
  responderAutenticacion(422, 'La nueva contrasena debe ser diferente a la actual');
}

try {
  mysqli_begin_transaction($conexion);
  $passwordHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
  $stmtActualizar = mysqli_prepare(
    $conexion,
    "UPDATE usuarios
     SET password_hash = ?,
         requiere_cambio_password = FALSE,
         intentos_fallidos = 0,
         bloqueado_hasta = NULL
     WHERE id_usuario = ?"
  );
  mysqli_stmt_bind_param($stmtActualizar, 'si', $passwordHash, $idUsuario);
  mysqli_stmt_execute($stmtActualizar);
  revocarSesionesUsuario($conexion, $idUsuario);
  mysqli_commit($conexion);

  echo json_encode([
    'status' => 'success',
    'mensaje' => 'La contrasena se actualizo correctamente. Inicia sesion de nuevo',
    'sesiones_revocadas' => true
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  mysqli_rollback($conexion);
  responderAutenticacion(500, 'No fue posible actualizar la contrasena');
}
