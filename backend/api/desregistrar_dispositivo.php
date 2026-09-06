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

if ($idGuarderia <= 0 || $idUsuario <= 0 || strlen($tokenFcm) < 20 || strlen($tokenFcm) > 512) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'Los datos del dispositivo no son validos'
  ]);
}

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para desactivar este dispositivo'
    ]);
  }

  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE dispositivos_fcm
     SET activo = FALSE,
         ultimo_uso = NOW()
     WHERE id_usuario = ?
       AND token_fcm = ?"
  );
  mysqli_stmt_bind_param($stmt, 'is', $idUsuario, $tokenFcm);
  mysqli_stmt_execute($stmt);

  responderPadre(200, [
    'status' => 'success',
    'mensaje' => 'Dispositivo desactivado correctamente'
  ]);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible desactivar el dispositivo'
  ]);
}
