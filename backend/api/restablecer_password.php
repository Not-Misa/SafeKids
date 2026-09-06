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

$idGuarderia = (int) ($datos['id_guarderia'] ?? 0);
$idAdministrador = (int) ($datos['id_empleado'] ?? 0);
$idObjetivo = (int) ($datos['id_objetivo'] ?? 0);
$tipoObjetivo = strtoupper(trim((string) ($datos['tipo_objetivo'] ?? '')));

if (
  $idGuarderia <= 0 ||
  $idAdministrador <= 0 ||
  $idObjetivo <= 0 ||
  !in_array($tipoObjetivo, ['EMPLEADO', 'TUTOR'], true)
) {
  responderAutenticacion(400, 'Los datos para restablecer la contrasena no son validos');
}

if ($tipoObjetivo === 'EMPLEADO') {
  $sqlObjetivo = "SELECT u.id_usuario, u.email, e.nombres, e.apellidos
    FROM empleados e
    INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    WHERE e.id_empleado = ?
      AND u.id_guarderia = ?
      AND r.nombre IN ('ADMIN', 'EMPLEADO')
      AND e.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
    LIMIT 1";
} else {
  $sqlObjetivo = "SELECT u.id_usuario, u.email, t.nombres, t.apellidos
    FROM tutores t
    INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    WHERE t.id_tutor = ?
      AND u.id_guarderia = ?
      AND r.nombre = 'TUTOR'
      AND t.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
    LIMIT 1";
}

$stmtObjetivo = mysqli_prepare($conexion, $sqlObjetivo);
mysqli_stmt_bind_param($stmtObjetivo, 'ii', $idObjetivo, $idGuarderia);
mysqli_stmt_execute($stmtObjetivo);
$objetivo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtObjetivo));

if (!$objetivo) {
  responderAutenticacion(404, 'La cuenta indicada no existe en esta guarderia');
}

$idUsuarioObjetivo = (int) $objetivo['id_usuario'];
if ($idUsuarioObjetivo === (int) $contextoAutenticacion['id_usuario']) {
  responderAutenticacion(422, 'Usa la opcion Cambiar contrasena para actualizar tu propia cuenta');
}

try {
  mysqli_begin_transaction($conexion);
  $passwordTemporal = generarPasswordTemporalSegura();
  $passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);
  $stmtActualizar = mysqli_prepare(
    $conexion,
    "UPDATE usuarios
     SET password_hash = ?,
         requiere_cambio_password = TRUE,
         intentos_fallidos = 0,
         bloqueado_hasta = NULL
     WHERE id_usuario = ?"
  );
  mysqli_stmt_bind_param($stmtActualizar, 'si', $passwordHash, $idUsuarioObjetivo);
  mysqli_stmt_execute($stmtActualizar);
  revocarSesionesUsuario($conexion, $idUsuarioObjetivo);
  mysqli_commit($conexion);

  echo json_encode([
    'status' => 'success',
    'mensaje' => 'La contrasena temporal fue generada correctamente',
    'credencial_temporal' => [
      'email' => $objetivo['email'],
      'password_temporal' => $passwordTemporal,
      'nombre' => trim($objetivo['nombres'] . ' ' . $objetivo['apellidos'])
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  mysqli_rollback($conexion);
  responderAutenticacion(500, 'No fue posible restablecer la contrasena');
}
