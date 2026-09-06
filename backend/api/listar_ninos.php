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
$busqueda = trim($_GET['q'] ?? '');

if ($idGuarderia <= 0) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No se recibio la guarderia'
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
    n.estado,
    n.fecha_ingreso,
    em.alergias,
    COUNT(DISTINCT nt.id_tutor) AS cantidad_tutores
  FROM ninos n
  LEFT JOIN expedientes_medicos em ON em.id_nino = n.id_nino
  LEFT JOIN nino_tutor nt ON nt.id_nino = n.id_nino
  WHERE n.id_guarderia = ?
    AND n.eliminado_en IS NULL
    AND (
      ? = ''
      OR n.nombres LIKE ?
      OR n.apellidos LIKE ?
      OR CONCAT(n.nombres, ' ', n.apellidos) LIKE ?
    )
  GROUP BY
    n.id_nino,
    n.nombres,
    n.apellidos,
    n.fecha_nacimiento,
    n.genero,
    n.foto_url,
    n.estado,
    n.fecha_ingreso,
    em.alergias
  ORDER BY
    CASE n.estado WHEN 'ACTIVO' THEN 0 WHEN 'INACTIVO' THEN 1 ELSE 2 END,
    n.apellidos,
    n.nombres
  LIMIT 150
";

try {
  $stmt = mysqli_prepare($conexion, $sql);
  mysqli_stmt_bind_param(
    $stmt,
    'issss',
    $idGuarderia,
    $busqueda,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike
  );
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
      'estado' => $nino['estado'],
      'fecha_ingreso' => $nino['fecha_ingreso'],
      'alergias' => $nino['alergias'],
      'cantidad_tutores' => (int) $nino['cantidad_tutores']
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
    'mensaje' => 'No fue posible consultar los ninos'
  ]);
}
