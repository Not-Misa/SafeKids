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

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($idGuarderia <= 0) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No se recibio la guarderia'
  ]);
  exit();
}

if ($busqueda === '') {
  echo json_encode([
    'status' => 'success',
    'ninos' => []
  ]);
  exit();
}

$busquedaLike = '%' . $busqueda . '%';

$sql = "
  SELECT
    n.id_nino,
    n.nombres,
    n.apellidos,
    n.fecha_nacimiento,
    TIMESTAMPDIFF(YEAR, n.fecha_nacimiento, CURDATE()) AS edad,
    n.genero,
    n.foto_url,
    n.codigo_qr,
    n.estado,
    em.alergias
  FROM ninos n
  LEFT JOIN expedientes_medicos em ON em.id_nino = n.id_nino
  WHERE n.id_guarderia = ?
    AND n.estado = 'ACTIVO'
    AND n.eliminado_en IS NULL
    AND (
      n.nombres LIKE ?
      OR n.apellidos LIKE ?
      OR CONCAT(n.nombres, ' ', n.apellidos) LIKE ?
    )
  ORDER BY n.apellidos ASC, n.nombres ASC
  LIMIT 8
";

try {
  $stmt = mysqli_prepare($conexion, $sql);
  mysqli_stmt_bind_param($stmt, 'isss', $idGuarderia, $busquedaLike, $busquedaLike, $busquedaLike);
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $ninos = [];
  while ($nino = mysqli_fetch_assoc($resultado)) {
    $ninos[] = [
      'id_nino' => (int) $nino['id_nino'],
      'nombres' => $nino['nombres'],
      'apellidos' => $nino['apellidos'],
      'fecha_nacimiento' => $nino['fecha_nacimiento'],
      'edad' => (int) $nino['edad'],
      'genero' => $nino['genero'],
      'foto_url' => $nino['foto_url'],
      'codigo_qr' => $nino['codigo_qr'],
      'estado' => $nino['estado'],
      'alergias' => $nino['alergias']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'ninos' => $ninos
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible buscar ninos'
  ]);
}
