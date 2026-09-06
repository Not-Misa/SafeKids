<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/reportes_utilidades.php';

$cuerpo = json_decode(file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'El contenido de la solicitud no es valido'
  ]);
}

$idGuarderia = isset($cuerpo['id_guarderia']) ? (int) $cuerpo['id_guarderia'] : 0;
$idEmpleado = isset($cuerpo['id_empleado']) ? (int) $cuerpo['id_empleado'] : 0;
$idNino = isset($cuerpo['id_nino']) ? (int) $cuerpo['id_nino'] : 0;
$idTipo = isset($cuerpo['id_tipo_evento']) ? (int) $cuerpo['id_tipo_evento'] : 0;
$fechaRecibida = trim((string) ($cuerpo['fecha_hora_evento'] ?? ''));
$titulo = textoOpcionalReporte($cuerpo['titulo'] ?? null);
$descripcion = textoOpcionalReporte($cuerpo['descripcion'] ?? null);
$nivel = strtoupper(trim((string) ($cuerpo['nivel'] ?? 'INFORMATIVO')));

if (
  $idGuarderia <= 0 ||
  $idEmpleado <= 0 ||
  $idNino <= 0 ||
  $idTipo <= 0 ||
  $fechaRecibida === '' ||
  !in_array($nivel, ['INFORMATIVO', 'IMPORTANTE', 'URGENTE'], true)
) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'Completa correctamente los datos obligatorios del reporte'
  ]);
}

if ($titulo !== null && longitudReporte($titulo) > 150) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'El titulo no puede superar 150 caracteres'
  ]);
}

if ($descripcion !== null && longitudReporte($descripcion) > 3000) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'La descripcion no puede superar 3000 caracteres'
  ]);
}

try {
  if (!obtenerPersonalActivo($conexion, $idEmpleado, $idGuarderia)) {
    responderReporte(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para registrar reportes'
    ]);
  }

  $nino = obtenerNinoReporte($conexion, $idNino, $idGuarderia);
  if (!$nino) {
    throw new DomainException('El nino no pertenece a la guarderia');
  }
  if ($nino['estado'] !== 'ACTIVO') {
    throw new DomainException('No se pueden agregar reportes a un expediente inactivo');
  }

  $stmtTipo = mysqli_prepare(
    $conexion,
    "SELECT id_tipo_evento, nombre, requiere_detalle
     FROM tipos_evento
     WHERE id_tipo_evento = ? AND activo = TRUE
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtTipo, 'i', $idTipo);
  mysqli_stmt_execute($stmtTipo);
  $tipo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTipo));
  if (!$tipo) {
    throw new DomainException('El tipo de reporte seleccionado no existe');
  }

  if ((bool) $tipo['requiere_detalle'] && $descripcion === null) {
    throw new DomainException('Este tipo de reporte requiere una descripcion');
  }

  $fechaHora = fechaHoraReporte($fechaRecibida);
  $detalleJson = detalleReporteValidado($cuerpo['detalle_json'] ?? null);

  $stmt = mysqli_prepare(
    $conexion,
    "INSERT INTO eventos_nino (
      id_guarderia,
      id_nino,
      id_tipo_evento,
      id_empleado,
      fecha_hora_evento,
      titulo,
      descripcion,
      detalle_json,
      nivel
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );
  mysqli_stmt_bind_param(
    $stmt,
    'iiiisssss',
    $idGuarderia,
    $idNino,
    $idTipo,
    $idEmpleado,
    $fechaHora,
    $titulo,
    $descripcion,
    $detalleJson,
    $nivel
  );
  mysqli_stmt_execute($stmt);
  $idEvento = (int) mysqli_insert_id($conexion);

  responderReporte(201, [
    'status' => 'success',
    'mensaje' => 'El reporte diario fue registrado correctamente',
    'id_evento' => $idEvento
  ]);
} catch (DomainException $e) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => $e->getMessage()
  ]);
} catch (JsonException $e) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'El detalle adicional no es valido'
  ]);
} catch (mysqli_sql_exception $e) {
  responderReporte(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible registrar el reporte'
  ]);
}
