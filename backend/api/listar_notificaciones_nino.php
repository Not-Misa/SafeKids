<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Metodo no permitido'
  ]);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/reportes_utilidades.php';

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idEmpleado = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;
$idNino = isset($_GET['id_nino']) ? (int) $_GET['id_nino'] : 0;
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'TODOS')));
$prioridad = strtoupper(trim((string) ($_GET['prioridad'] ?? 'TODAS')));
$fechaDesde = trim((string) ($_GET['desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['hasta'] ?? ''));

$prioridadesValidas = ['TODAS', 'NORMAL', 'IMPORTANTE', 'URGENTE'];
$filtrosValidos = (
  $idGuarderia > 0 &&
  $idEmpleado > 0 &&
  $idNino > 0 &&
  longitudReporte($buscar) <= 100 &&
  preg_match('/^(TODOS|[A-Z_]{1,40})$/', $tipo) === 1 &&
  in_array($prioridad, $prioridadesValidas, true) &&
  ($fechaDesde === '' || fechaSimpleValida($fechaDesde)) &&
  ($fechaHasta === '' || fechaSimpleValida($fechaHasta)) &&
  ($fechaDesde === '' || $fechaHasta === '' || $fechaDesde <= $fechaHasta)
);

if (!$filtrosValidos) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'Los filtros del historial no son validos'
  ]);
}

if ($fechaDesde !== '' && $fechaHasta !== '') {
  $inicio = new DateTime($fechaDesde);
  $fin = new DateTime($fechaHasta);
  if ($inicio->diff($fin)->days > 92) {
    responderReporte(422, [
      'status' => 'error',
      'mensaje' => 'El rango de consulta no puede superar 93 dias'
    ]);
  }
}

try {
  if (!obtenerPersonalActivo($conexion, $idEmpleado, $idGuarderia)) {
    responderReporte(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar las notificaciones'
    ]);
  }

  $nino = obtenerNinoReporte($conexion, $idNino, $idGuarderia);
  if (!$nino) {
    responderReporte(404, [
      'status' => 'error',
      'mensaje' => 'El expediente no existe o no pertenece a la guarderia'
    ]);
  }

  $stmtTipos = mysqli_prepare(
    $conexion,
    "SELECT DISTINCT tn.codigo, tn.nombre
     FROM tipos_notificacion tn
     INNER JOIN notificaciones no
       ON no.id_tipo_notificacion = tn.id_tipo_notificacion
     WHERE no.id_guarderia = ?
       AND no.id_nino = ?
       AND no.alcance = 'PERSONAL'
       AND no.estado = 'PUBLICADA'
       AND no.eliminado_en IS NULL
     ORDER BY tn.nombre"
  );
  mysqli_stmt_bind_param($stmtTipos, 'ii', $idGuarderia, $idNino);
  mysqli_stmt_execute($stmtTipos);
  $resultadoTipos = mysqli_stmt_get_result($stmtTipos);
  $tipos = [];
  while ($filaTipo = mysqli_fetch_assoc($resultadoTipos)) {
    $tipos[] = [
      'codigo' => $filaTipo['codigo'],
      'nombre' => $filaTipo['nombre']
    ];
  }

  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      no.id_notificacion,
      no.titulo,
      no.mensaje,
      no.prioridad,
      no.imagen_url,
      no.imagen_public_id,
      no.requiere_confirmacion,
      no.publicada_en,
      tn.codigo AS tipo_codigo,
      tn.nombre AS tipo_nombre,
      e.id_empleado,
      e.nombres AS empleado_nombres,
      e.apellidos AS empleado_apellidos,
      e.foto_url AS empleado_foto_url,
      nd.id_destinatario,
      nd.id_usuario AS destinatario_id_usuario,
      nd.enviada_push,
      nd.enviada_push_en,
      nd.vista,
      nd.vista_en,
      nd.confirmada,
      nd.confirmada_en,
      nd.respuesta,
      t.id_tutor,
      t.nombres AS tutor_nombres,
      t.apellidos AS tutor_apellidos,
      t.foto_url AS tutor_foto_url
    FROM notificaciones no
    INNER JOIN tipos_notificacion tn
      ON tn.id_tipo_notificacion = no.id_tipo_notificacion
    INNER JOIN empleados e
      ON e.id_empleado = no.id_empleado
    LEFT JOIN notificacion_destinatarios nd
      ON nd.id_notificacion = no.id_notificacion
    LEFT JOIN tutores t
      ON t.id_usuario = nd.id_usuario
    WHERE no.id_guarderia = ?
      AND no.id_nino = ?
      AND no.alcance = 'PERSONAL'
      AND no.estado = 'PUBLICADA'
      AND no.eliminado_en IS NULL
      AND (? = '' OR DATE(no.publicada_en) >= ?)
      AND (? = '' OR DATE(no.publicada_en) <= ?)
      AND (? = 'TODOS' OR tn.codigo = ?)
      AND (? = 'TODAS' OR no.prioridad = ?)
      AND (
        ? = ''
        OR no.titulo LIKE CONCAT('%', ?, '%')
        OR no.mensaje LIKE CONCAT('%', ?, '%')
        OR CONCAT_WS(' ', e.nombres, e.apellidos) LIKE CONCAT('%', ?, '%')
      )
    ORDER BY no.publicada_en DESC, no.id_notificacion DESC, nd.id_destinatario
    LIMIT 400"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'iissssssssssss',
    $idGuarderia,
    $idNino,
    $fechaDesde,
    $fechaDesde,
    $fechaHasta,
    $fechaHasta,
    $tipo,
    $tipo,
    $prioridad,
    $prioridad,
    $buscar,
    $buscar,
    $buscar,
    $buscar
  );
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $notificaciones = [];
  $indices = [];
  while ($fila = mysqli_fetch_assoc($resultado)) {
    $idNotificacion = (int) $fila['id_notificacion'];
    if (!isset($indices[$idNotificacion])) {
      $indices[$idNotificacion] = count($notificaciones);
      $notificaciones[] = [
        'id_notificacion' => $idNotificacion,
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
          'apellidos' => $fila['empleado_apellidos'],
          'foto_url' => $fila['empleado_foto_url']
        ],
        'entrega' => [
          'destinatarios' => 0,
          'push_enviados' => 0,
          'vistos' => 0,
          'confirmados' => 0
        ],
        'destinatarios' => []
      ];
    }

    if ($fila['id_destinatario'] === null) {
      continue;
    }

    $indice = $indices[$idNotificacion];
    $pushEnviado = (bool) $fila['enviada_push'];
    $vista = (bool) $fila['vista'];
    $confirmada = (bool) $fila['confirmada'];
    $notificaciones[$indice]['entrega']['destinatarios']++;
    $notificaciones[$indice]['entrega']['push_enviados'] += $pushEnviado ? 1 : 0;
    $notificaciones[$indice]['entrega']['vistos'] += $vista ? 1 : 0;
    $notificaciones[$indice]['entrega']['confirmados'] += $confirmada ? 1 : 0;
    $notificaciones[$indice]['destinatarios'][] = [
      'id_destinatario' => (int) $fila['id_destinatario'],
      'id_usuario' => (int) $fila['destinatario_id_usuario'],
      'id_tutor' => $fila['id_tutor'] !== null ? (int) $fila['id_tutor'] : null,
      'nombres' => $fila['tutor_nombres'],
      'apellidos' => $fila['tutor_apellidos'],
      'foto_url' => $fila['tutor_foto_url'],
      'enviada_push' => $pushEnviado,
      'enviada_push_en' => $fila['enviada_push_en'],
      'vista' => $vista,
      'vista_en' => $fila['vista_en'],
      'confirmada' => $confirmada,
      'confirmada_en' => $fila['confirmada_en'],
      'respuesta' => $fila['respuesta']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'nino' => [
      'id_nino' => (int) $nino['id_nino'],
      'nombres' => $nino['nombres'],
      'apellidos' => $nino['apellidos'],
      'foto_url' => $nino['foto_url'],
      'estado' => $nino['estado']
    ],
    'notificaciones' => $notificaciones,
    'tipos' => $tipos
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderReporte(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar el historial de notificaciones'
  ]);
}
