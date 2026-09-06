<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/padres_utilidades.php';

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'El contenido de la solicitud no es valido'
  ]);
}

$idGuarderia = isset($datos['id_guarderia']) ? (int) $datos['id_guarderia'] : 0;
$idUsuario = isset($datos['id_usuario']) ? (int) $datos['id_usuario'] : 0;
$tokenFcm = trim((string) ($datos['token_fcm'] ?? ''));
$nombreDispositivo = textoOpcionalPadre($datos['nombre_dispositivo'] ?? null);

if ($idGuarderia <= 0 || $idUsuario <= 0 || strlen($tokenFcm) < 20 || strlen($tokenFcm) > 512) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'Los datos del dispositivo no son validos'
  ]);
}

if ($nombreDispositivo !== null && strlen($nombreDispositivo) > 120) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'El nombre del dispositivo no puede superar 120 caracteres'
  ]);
}

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para registrar este dispositivo'
    ]);
  }

  $stmt = mysqli_prepare(
    $conexion,
    "INSERT INTO dispositivos_fcm (
      id_usuario,
      token_fcm,
      plataforma,
      nombre_dispositivo,
      activo,
      ultimo_uso
    ) VALUES (?, ?, 'ANDROID', ?, TRUE, NOW())
    ON DUPLICATE KEY UPDATE
      id_dispositivo = LAST_INSERT_ID(id_dispositivo),
      id_usuario = VALUES(id_usuario),
      plataforma = 'ANDROID',
      nombre_dispositivo = VALUES(nombre_dispositivo),
      activo = TRUE,
      ultimo_uso = NOW()"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'iss',
    $idUsuario,
    $tokenFcm,
    $nombreDispositivo
  );
  mysqli_stmt_execute($stmt);

  responderPadre(200, [
    'status' => 'success',
    'mensaje' => 'Dispositivo registrado para recibir notificaciones',
    'dispositivo' => [
      'id_dispositivo' => (int) mysqli_insert_id($conexion),
      'plataforma' => 'ANDROID',
      'activo' => true
    ]
  ]);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible registrar el dispositivo'
  ]);
}
