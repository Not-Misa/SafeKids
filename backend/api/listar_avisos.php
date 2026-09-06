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
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'TODOS')));
$prioridad = strtoupper(trim((string) ($_GET['prioridad'] ?? 'TODAS')));
$fechaDesde = trim((string) ($_GET['desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['hasta'] ?? ''));

$prioridadesValidas = ['TODAS', 'NORMAL', 'IMPORTANTE', 'URGENTE'];
$filtrosValidos = (
  $idGuarderia > 0 &&
  $idEmpleado > 0 &&
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

try {
  if (!obtenerPersonalActivo($conexion, $idEmpleado, $idGuarderia)) {
    responderReporte(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar los avisos'
    ]);
  }

  $stmtTipos = mysqli_prepare(
    $conexion,
    "SELECT DISTINCT tn.codigo, tn.nombre
     FROM tipos_notificacion tn
     INNER JOIN notificaciones no
       ON no.id_tipo_notificacion = tn.id_tipo_notificacion
     WHERE no.id_guarderia = ?
       AND no.alcance = 'GLOBAL'
       AND no.estado = 'PUBLICADA'
       AND no.eliminado_en IS NULL
     ORDER BY tn.nombre"
  );
  mysqli_stmt_bind_param($stmtTipos, 'i', $idGuarderia);
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
      no.requiere_confirmacion,
      no.publicada_en,
      tn.codigo AS tipo_codigo,
      tn.nombre AS tipo_nombre,
      e.id_empleado,
      e.nombres AS empleado_nombres,
      e.apellidos AS empleado_apellidos,
      e.foto_url AS empleado_foto_url,
      (
        SELECT COUNT(*)
        FROM notificacion_destinatarios nd
        WHERE nd.id_notificacion = no.id_notificacion
      ) AS destinatarios,
      (
        SELECT COUNT(*)
        FROM notificacion_destinatarios nd
        WHERE nd.id_notificacion = no.id_notificacion
          AND nd.enviada_push = TRUE
      ) AS push_enviados,
      (
        SELECT COUNT(*)
        FROM notificacion_destinatarios nd
        WHERE nd.id_notificacion = no.id_notificacion
          AND nd.vista = TRUE
      ) AS vistos,
      (
        SELECT COUNT(*)
        FROM notificacion_destinatarios nd
        WHERE nd.id_notificacion = no.id_notificacion
          AND nd.confirmada = TRUE
      ) AS confirmados
    FROM notificaciones no
    INNER JOIN tipos_notificacion tn
      ON tn.id_tipo_notificacion = no.id_tipo_notificacion
    INNER JOIN empleados e
      ON e.id_empleado = no.id_empleado
    WHERE no.id_guarderia = ?
      AND no.alcance = 'GLOBAL'
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
    ORDER BY no.publicada_en DESC, no.id_notificacion DESC
    LIMIT 200"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'issssssssssss',
    $idGuarderia,
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

  $avisos = [];
  while ($fila = mysqli_fetch_assoc($resultado)) {
    $avisos[] = [
      'id_notificacion' => (int) $fila['id_notificacion'],
      'titulo' => $fila['titulo'],
      'mensaje' => $fila['mensaje'],
      'prioridad' => $fila['prioridad'],
      'imagen_url' => $fila['imagen_url'],
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
        'destinatarios' => (int) $fila['destinatarios'],
        'push_enviados' => (int) $fila['push_enviados'],
        'vistos' => (int) $fila['vistos'],
        'confirmados' => (int) $fila['confirmados']
      ]
    ];
  }

  echo json_encode([
    'status' => 'success',
    'avisos' => $avisos,
    'tipos' => $tipos
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderReporte(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar el historial de avisos'
  ]);
}
