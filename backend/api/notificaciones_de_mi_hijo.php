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
$idNino = isset($_GET['id_nino']) ? (int) $_GET['id_nino'] : 0;
$fechaDesde = trim((string) ($_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'))));
$fechaHasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'TODOS')));
$soloPendientes = isset($_GET['solo_pendientes']) && (int) $_GET['solo_pendientes'] === 1;

if ($idGuarderia <= 0 || $idUsuario <= 0 || $idNino <= 0) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'Los filtros de notificaciones no son validos'
  ]);
}

validarFiltrosFechaPadre($fechaDesde, $fechaHasta);

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar notificaciones'
    ]);
  }

  $nino = obtenerHijoDeTutor($conexion, $idNino, (int) $tutor['id_tutor'], $idGuarderia);
  if (!$nino) {
    responderPadre(404, [
      'status' => 'error',
      'mensaje' => 'El hijo no existe o no esta vinculado a tu cuenta'
    ]);
  }

  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      no.id_notificacion,
      no.alcance,
      no.id_nino,
      no.titulo,
      no.mensaje,
      no.prioridad,
      no.imagen_url,
      no.imagen_public_id,
      no.requiere_confirmacion,
      no.publicada_en,
      tn.codigo AS tipo_codigo,
      tn.nombre AS tipo_nombre,
      emp.id_empleado,
      emp.nombres AS empleado_nombres,
      emp.apellidos AS empleado_apellidos,
      nd.id_destinatario,
      nd.enviada_push,
      nd.enviada_push_en,
      nd.vista,
      nd.vista_en,
      nd.confirmada,
      nd.confirmada_en,
      nd.respuesta
    FROM notificacion_destinatarios nd
    INNER JOIN notificaciones no ON no.id_notificacion = nd.id_notificacion
    INNER JOIN tipos_notificacion tn ON tn.id_tipo_notificacion = no.id_tipo_notificacion
    INNER JOIN empleados emp ON emp.id_empleado = no.id_empleado
    WHERE nd.id_usuario = ?
      AND no.id_guarderia = ?
      AND no.estado = 'PUBLICADA'
      AND no.eliminado_en IS NULL
      AND DATE(no.publicada_en) BETWEEN ? AND ?
      AND (? = 'TODOS' OR tn.codigo = ?)
      AND (? = 0 OR nd.vista = FALSE)
      AND (no.alcance = 'GLOBAL' OR no.id_nino = ?)
    ORDER BY no.publicada_en DESC, no.id_notificacion DESC
    LIMIT 300"
  );
  $pendienteFiltro = $soloPendientes ? 1 : 0;
  mysqli_stmt_bind_param(
    $stmt,
    'iissssii',
    $idUsuario,
    $idGuarderia,
    $fechaDesde,
    $fechaHasta,
    $tipo,
    $tipo,
    $pendienteFiltro,
    $idNino
  );
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $notificaciones = [];
  while ($fila = mysqli_fetch_assoc($resultado)) {
    $notificaciones[] = [
      'id_notificacion' => (int) $fila['id_notificacion'],
      'alcance' => $fila['alcance'],
      'id_nino' => $fila['id_nino'] !== null ? (int) $fila['id_nino'] : null,
      'titulo' => $fila['titulo'],
      'mensaje' => $fila['mensaje'],
      'prioridad' => $fila['prioridad'],
      'imagen_url' => $fila['imagen_url'],
      'imagen_public_id' => $fila['imagen_public_id'],
      'requiere_confirmacion' => (bool) $fila['requiere_confirmacion'],
      'publicada_en' => $fila['publicada_en'],
      'tipo' => [
        'codigo' => $fila['tipo_codigo'],
        'nombre' => $fila['tipo_nombre']
      ],
      'empleado' => [
        'id_empleado' => (int) $fila['id_empleado'],
        'nombres' => $fila['empleado_nombres'],
        'apellidos' => $fila['empleado_apellidos']
      ],
      'destinatario' => [
        'id_destinatario' => (int) $fila['id_destinatario'],
        'enviada_push' => (bool) $fila['enviada_push'],
        'enviada_push_en' => $fila['enviada_push_en'],
        'vista' => (bool) $fila['vista'],
        'vista_en' => $fila['vista_en'],
        'confirmada' => (bool) $fila['confirmada'],
        'confirmada_en' => $fila['confirmada_en'],
        'respuesta' => $fila['respuesta']
      ]
    ];
  }

  echo json_encode([
    'status' => 'success',
    'tutor' => respuestaTutor($tutor),
    'nino' => respuestaHijoResumen($nino),
    'notificaciones' => $notificaciones
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar las notificaciones'
  ]);
}
