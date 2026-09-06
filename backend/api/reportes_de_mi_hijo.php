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
$fechaDesde = trim((string) ($_GET['desde'] ?? date('Y-m-d')));
$fechaHasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));
$tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'TODOS')));

if ($idGuarderia <= 0 || $idUsuario <= 0 || $idNino <= 0) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'Los filtros del historial no son validos'
  ]);
}

validarFiltrosFechaPadre($fechaDesde, $fechaHasta);

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar reportes'
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
      ev.id_evento,
      ev.fecha_hora_evento,
      ev.titulo,
      ev.descripcion,
      ev.detalle_json,
      ev.nivel,
      te.codigo AS tipo_codigo,
      te.nombre AS tipo_nombre,
      emp.id_empleado,
      emp.nombres AS empleado_nombres,
      emp.apellidos AS empleado_apellidos,
      img.id_imagen,
      img.url AS imagen_url,
      img.public_id AS imagen_public_id,
      img.descripcion AS imagen_descripcion,
      img.orden AS imagen_orden
    FROM eventos_nino ev
    INNER JOIN tipos_evento te ON te.id_tipo_evento = ev.id_tipo_evento
    INNER JOIN empleados emp ON emp.id_empleado = ev.id_empleado
    LEFT JOIN imagenes_evento img ON img.id_evento = ev.id_evento
    WHERE ev.id_guarderia = ?
      AND ev.id_nino = ?
      AND ev.eliminado_en IS NULL
      AND DATE(ev.fecha_hora_evento) BETWEEN ? AND ?
      AND (? = 'TODOS' OR te.codigo = ?)
    ORDER BY ev.fecha_hora_evento DESC, ev.id_evento DESC, img.orden
    LIMIT 300"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'iissss',
    $idGuarderia,
    $idNino,
    $fechaDesde,
    $fechaHasta,
    $tipo,
    $tipo
  );
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $reportes = [];
  $indices = [];
  while ($fila = mysqli_fetch_assoc($resultado)) {
    $idEvento = (int) $fila['id_evento'];
    if (!isset($indices[$idEvento])) {
      $detalle = null;
      if (is_string($fila['detalle_json']) && $fila['detalle_json'] !== '') {
        $decodificado = json_decode($fila['detalle_json'], true);
        $detalle = is_array($decodificado) ? $decodificado : null;
      }

      $indices[$idEvento] = count($reportes);
      $reportes[] = [
        'id_evento' => $idEvento,
        'fecha_hora_evento' => $fila['fecha_hora_evento'],
        'titulo' => $fila['titulo'],
        'descripcion' => $fila['descripcion'],
        'detalle_json' => $detalle,
        'nivel' => $fila['nivel'],
        'tipo' => [
          'codigo' => $fila['tipo_codigo'],
          'nombre' => $fila['tipo_nombre']
        ],
        'empleado' => [
          'id_empleado' => (int) $fila['id_empleado'],
          'nombres' => $fila['empleado_nombres'],
          'apellidos' => $fila['empleado_apellidos']
        ],
        'imagenes' => []
      ];
    }

    if ($fila['id_imagen'] !== null) {
      $indice = $indices[$idEvento];
      $reportes[$indice]['imagenes'][] = [
        'id_imagen' => (int) $fila['id_imagen'],
        'url' => $fila['imagen_url'],
        'public_id' => $fila['imagen_public_id'],
        'descripcion' => $fila['imagen_descripcion'],
        'orden' => (int) $fila['imagen_orden']
      ];
    }
  }

  echo json_encode([
    'status' => 'success',
    'tutor' => respuestaTutor($tutor),
    'nino' => respuestaHijoResumen($nino),
    'reportes' => $reportes
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar los reportes'
  ]);
}
