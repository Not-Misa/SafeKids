<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../cloudinary.php';
require_once __DIR__ . '/reportes_utilidades.php';

$idEvento = isset($_POST['id_evento']) ? (int) $_POST['id_evento'] : 0;
$idGuarderia = isset($_POST['id_guarderia']) ? (int) $_POST['id_guarderia'] : 0;
$idEmpleado = isset($_POST['id_empleado']) ? (int) $_POST['id_empleado'] : 0;

if ($idEvento <= 0 || $idGuarderia <= 0 || $idEmpleado <= 0) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'Faltan los datos para asociar la evidencia'
  ]);
}

if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'Selecciona una fotografia'
  ]);
}

$archivo = $_FILES['foto'];
if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'No fue posible recibir la fotografia'
  ]);
}

$rutaTemporal = (string) ($archivo['tmp_name'] ?? '');
$tamano = (int) ($archivo['size'] ?? 0);
$nombreOriginal = (string) ($archivo['name'] ?? 'evidencia');

if ($rutaTemporal === '' || !is_uploaded_file($rutaTemporal)) {
  responderReporte(400, [
    'status' => 'error',
    'mensaje' => 'El archivo recibido no es valido'
  ]);
}

if ($tamano <= 0 || $tamano > 5 * 1024 * 1024) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'La fotografia debe pesar como maximo 5 MB'
  ]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $rutaTemporal) : false;
if ($finfo) {
  finfo_close($finfo);
}
if (
  !is_string($mime) ||
  !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'Solo se permiten imagenes JPG, PNG o WEBP'
  ]);
}

$dimensiones = getimagesize($rutaTemporal);
if (
  $dimensiones === false ||
  (int) $dimensiones[0] <= 0 ||
  (int) $dimensiones[1] <= 0 ||
  (int) $dimensiones[0] > 12000 ||
  (int) $dimensiones[1] > 12000
) {
  responderReporte(422, [
    'status' => 'error',
    'mensaje' => 'Las dimensiones de la fotografia no son validas'
  ]);
}

$nuevaFoto = null;
$fotoConfirmada = false;
$transaccionActiva = false;

try {
  $personal = obtenerPersonalActivo($conexion, $idEmpleado, $idGuarderia);
  if (!$personal) {
    responderReporte(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para agregar evidencias'
    ]);
  }

  $stmtEvento = mysqli_prepare(
    $conexion,
    "SELECT ev.id_evento, ev.id_empleado, te.permite_imagen
     FROM eventos_nino ev
     INNER JOIN tipos_evento te ON te.id_tipo_evento = ev.id_tipo_evento
     WHERE ev.id_evento = ?
       AND ev.id_guarderia = ?
       AND ev.eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtEvento, 'ii', $idEvento, $idGuarderia);
  mysqli_stmt_execute($stmtEvento);
  $evento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtEvento));
  if (!$evento) {
    throw new DomainException('El reporte no pertenece a la guarderia');
  }
  if (!(bool) $evento['permite_imagen']) {
    throw new DomainException('Este tipo de reporte no permite fotografias');
  }
  if ((int) $evento['id_empleado'] !== $idEmpleado && $personal['rol'] !== 'ADMIN') {
    throw new DomainException('Solo el autor o un administrador puede cambiar la evidencia');
  }

  $nuevaFoto = cloudinary_subir_imagen(
    $rutaTemporal,
    $nombreOriginal,
    $mime,
    'safekids/uploads/reportes',
    'c_limit,h_1200,w_1600'
  );

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $stmtAnterior = mysqli_prepare(
    $conexion,
    "SELECT id_imagen, url, public_id
     FROM imagenes_evento
     WHERE id_evento = ? AND orden = 1
     LIMIT 1
     FOR UPDATE"
  );
  mysqli_stmt_bind_param($stmtAnterior, 'i', $idEvento);
  mysqli_stmt_execute($stmtAnterior);
  $fotoAnterior = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAnterior));

  $fotoUrl = $nuevaFoto['url'];
  $fotoPublicId = $nuevaFoto['public_id'];
  if ($fotoAnterior) {
    $idImagen = (int) $fotoAnterior['id_imagen'];
    $stmtGuardar = mysqli_prepare(
      $conexion,
      "UPDATE imagenes_evento
       SET url = ?, public_id = ?
       WHERE id_imagen = ?"
    );
    mysqli_stmt_bind_param($stmtGuardar, 'ssi', $fotoUrl, $fotoPublicId, $idImagen);
  } else {
    $orden = 1;
    $stmtGuardar = mysqli_prepare(
      $conexion,
      "INSERT INTO imagenes_evento (id_evento, url, public_id, orden)
       VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
      $stmtGuardar,
      'issi',
      $idEvento,
      $fotoUrl,
      $fotoPublicId,
      $orden
    );
  }
  mysqli_stmt_execute($stmtGuardar);

  mysqli_commit($conexion);
  $transaccionActiva = false;
  $fotoConfirmada = true;

  $advertencia = null;
  if ($fotoAnterior && $fotoAnterior['public_id'] !== $fotoPublicId) {
    try {
      cloudinary_eliminar_imagen($fotoAnterior['public_id']);
    } catch (RuntimeException $e) {
      error_log('SafeKids: no se pudo eliminar una evidencia anterior');
      $advertencia = 'La evidencia se actualizo, pero la version anterior no pudo eliminarse';
    }
  }

  echo json_encode([
    'status' => 'success',
    'mensaje' => 'La evidencia fue guardada correctamente',
    'foto_url' => $fotoUrl,
    'foto_public_id' => $fotoPublicId,
    'ancho' => $nuevaFoto['width'],
    'alto' => $nuevaFoto['height'],
    'advertencia' => $advertencia
  ], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una evidencia nueva');
    }
  }
  responderReporte(422, ['status' => 'error', 'mensaje' => $e->getMessage()]);
} catch (mysqli_sql_exception $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una evidencia nueva');
    }
  }
  responderReporte(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible guardar la evidencia'
  ]);
} catch (RuntimeException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una evidencia nueva');
    }
  }
  error_log('SafeKids: error de Cloudinary en reportes: ' . $e->getMessage());
  responderReporte(502, [
    'status' => 'error',
    'mensaje' => 'No fue posible procesar la evidencia en este momento'
  ]);
}
