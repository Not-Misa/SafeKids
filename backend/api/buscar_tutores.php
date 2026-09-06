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
$idEmpleado = isset($_GET['id_empleado']) ? (int) $_GET['id_empleado'] : 0;
$busqueda = trim((string) ($_GET['q'] ?? ''));

if ($idGuarderia <= 0 || $idEmpleado <= 0 || mb_strlen($busqueda) > 120) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Los datos de busqueda no son validos'
  ]);
  exit();
}

try {
  $busquedaLike = '%' . $busqueda . '%';
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      t.id_tutor,
      t.nombres,
      t.apellidos,
      t.telefono,
      t.telefono_alterno,
      t.direccion,
      u.email,
      COUNT(DISTINCT n.id_nino) AS cantidad_hijos
    FROM tutores t
    INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    LEFT JOIN nino_tutor nt ON nt.id_tutor = t.id_tutor
    LEFT JOIN ninos n
      ON n.id_nino = nt.id_nino
      AND n.eliminado_en IS NULL
    WHERE u.id_guarderia = ?
      AND r.nombre = 'TUTOR'
      AND u.estado = 'ACTIVO'
      AND u.eliminado_en IS NULL
      AND t.eliminado_en IS NULL
      AND (
        ? = ''
        OR t.nombres LIKE ?
        OR t.apellidos LIKE ?
        OR CONCAT_WS(' ', t.nombres, t.apellidos) LIKE ?
        OR u.email LIKE ?
        OR t.telefono LIKE ?
      )
    GROUP BY
      t.id_tutor,
      t.nombres,
      t.apellidos,
      t.telefono,
      t.telefono_alterno,
      t.direccion,
      u.email
    ORDER BY t.apellidos, t.nombres
    LIMIT 20"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'issssss',
    $idGuarderia,
    $busqueda,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike,
    $busquedaLike
  );
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $tutores = [];
  while ($tutor = mysqli_fetch_assoc($resultado)) {
    $tutores[] = [
      'id_tutor' => (int) $tutor['id_tutor'],
      'nombres' => $tutor['nombres'],
      'apellidos' => $tutor['apellidos'],
      'email' => $tutor['email'],
      'telefono' => $tutor['telefono'],
      'telefono_alterno' => $tutor['telefono_alterno'],
      'direccion' => $tutor['direccion'],
      'cantidad_hijos' => (int) $tutor['cantidad_hijos']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'tutores' => $tutores
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible consultar los tutores'
  ]);
}
