<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/padres_utilidades.php';

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idUsuario = isset($_GET['id_usuario']) ? (int) $_GET['id_usuario'] : 0;

if ($idGuarderia <= 0 || $idUsuario <= 0) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'No se recibieron los datos del tutor'
  ]);
}

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar hijos'
    ]);
  }

  $idTutor = (int) $tutor['id_tutor'];
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      n.id_nino,
      n.nombres,
      n.apellidos,
      n.fecha_nacimiento,
      TIMESTAMPDIFF(YEAR, n.fecha_nacimiento, CURDATE()) AS edad,
      n.genero,
      n.foto_url,
      n.codigo_qr,
      n.estado,
      n.fecha_ingreso,
      nt.parentesco,
      nt.es_principal,
      nt.recibe_notificaciones,
      nt.autorizado_recoger,
      nt.prioridad_contacto,
      em.alergias,
      em.padecimientos,
      em.tipo_sangre,
      (
        SELECT COUNT(*)
        FROM eventos_nino ev
        WHERE ev.id_guarderia = n.id_guarderia
          AND ev.id_nino = n.id_nino
          AND DATE(ev.fecha_hora_evento) = CURDATE()
          AND ev.eliminado_en IS NULL
      ) AS reportes_hoy,
      (
        SELECT COUNT(*)
        FROM notificacion_destinatarios nd
        INNER JOIN notificaciones no ON no.id_notificacion = nd.id_notificacion
        WHERE nd.id_usuario = ?
          AND nd.vista = FALSE
          AND no.id_guarderia = n.id_guarderia
          AND no.estado = 'PUBLICADA'
          AND no.eliminado_en IS NULL
          AND (no.alcance = 'GLOBAL' OR no.id_nino = n.id_nino)
      ) AS notificaciones_pendientes
    FROM nino_tutor nt
    INNER JOIN ninos n ON n.id_nino = nt.id_nino
    LEFT JOIN expedientes_medicos em ON em.id_nino = n.id_nino
    WHERE nt.id_tutor = ?
      AND n.id_guarderia = ?
      AND n.eliminado_en IS NULL
    ORDER BY
      CASE n.estado WHEN 'ACTIVO' THEN 0 WHEN 'INACTIVO' THEN 1 ELSE 2 END,
      n.apellidos,
      n.nombres"
  );
  mysqli_stmt_bind_param($stmt, 'iii', $idUsuario, $idTutor, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $hijos = [];
  while ($nino = mysqli_fetch_assoc($resultado)) {
    $resumen = respuestaHijoResumen($nino);
    $resumen['expediente_resumen'] = [
      'alergias' => $nino['alergias'],
      'padecimientos' => $nino['padecimientos'],
      'tipo_sangre' => $nino['tipo_sangre']
    ];
    $resumen['actividad'] = [
      'reportes_hoy' => (int) $nino['reportes_hoy'],
      'notificaciones_pendientes' => (int) $nino['notificaciones_pendientes']
    ];
    $hijos[] = $resumen;
  }

  echo json_encode([
    'status' => 'success',
    'tutor' => respuestaTutor($tutor),
    'hijos' => $hijos
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar los hijos'
  ]);
}
