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
$idNotificacion = isset($datos['id_notificacion']) ? (int) $datos['id_notificacion'] : 0;
$idNino = isset($datos['id_nino']) ? (int) $datos['id_nino'] : 0;
$confirmar = !empty($datos['confirmar']);
$respuesta = textoOpcionalPadre($datos['respuesta'] ?? null);

if ($idGuarderia <= 0 || $idUsuario <= 0 || $idNotificacion <= 0) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'No se recibieron los datos de la notificacion'
  ]);
}

if ($respuesta !== null && strlen($respuesta) > 255) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'La respuesta no puede superar 255 caracteres'
  ]);
}

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para actualizar notificaciones'
    ]);
  }

  if ($idNino > 0 && !obtenerHijoDeTutor($conexion, $idNino, (int) $tutor['id_tutor'], $idGuarderia)) {
    responderPadre(404, [
      'status' => 'error',
      'mensaje' => 'El hijo no existe o no esta vinculado a tu cuenta'
    ]);
  }

  $stmtNotificacion = mysqli_prepare(
    $conexion,
    "SELECT
      no.id_notificacion,
      no.alcance,
      no.id_nino,
      no.requiere_confirmacion,
      nd.id_destinatario,
      nd.vista,
      nd.vista_en,
      nd.confirmada,
      nd.confirmada_en,
      nd.respuesta
    FROM notificacion_destinatarios nd
    INNER JOIN notificaciones no ON no.id_notificacion = nd.id_notificacion
    WHERE no.id_notificacion = ?
      AND nd.id_usuario = ?
      AND no.id_guarderia = ?
      AND no.estado = 'PUBLICADA'
      AND no.eliminado_en IS NULL
      AND (? = 0 OR no.alcance = 'GLOBAL' OR no.id_nino = ?)
    LIMIT 1"
  );
  mysqli_stmt_bind_param(
    $stmtNotificacion,
    'iiiii',
    $idNotificacion,
    $idUsuario,
    $idGuarderia,
    $idNino,
    $idNino
  );
  mysqli_stmt_execute($stmtNotificacion);
  $notificacion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNotificacion));

  if (!$notificacion) {
    responderPadre(404, [
      'status' => 'error',
      'mensaje' => 'La notificacion no existe o no esta dirigida a tu cuenta'
    ]);
  }

  $idDestinatario = (int) $notificacion['id_destinatario'];
  $confirmada = (int) $notificacion['confirmada'];
  $confirmadaEn = $notificacion['confirmada_en'];
  $respuestaFinal = $notificacion['respuesta'];

  if ($confirmar) {
    $confirmada = 1;
    $confirmadaEn = date('Y-m-d H:i:s');
    $respuestaFinal = $respuesta;
  }

  $stmtActualizar = mysqli_prepare(
    $conexion,
    "UPDATE notificacion_destinatarios
     SET
      vista = TRUE,
      vista_en = COALESCE(vista_en, NOW()),
      confirmada = ?,
      confirmada_en = CASE
        WHEN ? = 1 THEN COALESCE(confirmada_en, NOW())
        ELSE confirmada_en
      END,
      respuesta = CASE
        WHEN ? = 1 THEN ?
        ELSE respuesta
      END
     WHERE id_destinatario = ?"
  );
  mysqli_stmt_bind_param(
    $stmtActualizar,
    'iiisi',
    $confirmada,
    $confirmada,
    $confirmada,
    $respuestaFinal,
    $idDestinatario
  );
  mysqli_stmt_execute($stmtActualizar);

  echo json_encode([
    'status' => 'success',
    'mensaje' => $confirmar
      ? 'Notificacion confirmada correctamente'
      : 'Notificacion marcada como vista',
    'notificacion' => [
      'id_notificacion' => $idNotificacion,
      'id_destinatario' => $idDestinatario,
      'vista' => true,
      'confirmada' => (bool) $confirmada,
      'confirmada_en' => $confirmadaEn,
      'respuesta' => $respuestaFinal
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible actualizar la notificacion'
  ]);
}
