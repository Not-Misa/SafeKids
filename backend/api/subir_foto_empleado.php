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
require_once __DIR__ . '/empleados_utilidades.php';

$idObjetivo = isset($_POST['id_objetivo']) ? (int) $_POST['id_objetivo'] : 0;
$idGuarderia = isset($_POST['id_guarderia']) ? (int) $_POST['id_guarderia'] : 0;
$idSolicitante = isset($_POST['id_empleado']) ? (int) $_POST['id_empleado'] : 0;

if ($idObjetivo <= 0 || $idGuarderia <= 0 || $idSolicitante <= 0) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Faltan los datos para asociar la fotografia'
  ]);
}

if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'Selecciona una fotografia'
  ]);
}

$archivo = $_FILES['foto'];
if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'No fue posible recibir la fotografia'
  ]);
}

$rutaTemporal = (string) ($archivo['tmp_name'] ?? '');
$tamano = (int) ($archivo['size'] ?? 0);
$nombreOriginal = (string) ($archivo['name'] ?? 'foto');

if ($rutaTemporal === '' || !is_uploaded_file($rutaTemporal)) {
  responderEmpleado(400, [
    'status' => 'error',
    'mensaje' => 'El archivo recibido no es valido'
  ]);
}

if ($tamano <= 0 || $tamano > 5 * 1024 * 1024) {
  responderEmpleado(422, [
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
  responderEmpleado(422, [
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
  responderEmpleado(422, [
    'status' => 'error',
    'mensaje' => 'Las dimensiones de la fotografia no son validas'
  ]);
}

$nuevaFoto = null;
$fotoConfirmada = false;
$transaccionActiva = false;

try {
  if (!obtenerAdministrador($conexion, $idSolicitante, $idGuarderia)) {
    responderEmpleado(403, [
      'status' => 'error',
      'mensaje' => 'Solo un administrador puede cambiar la fotografia'
    ]);
  }

  $stmtExiste = mysqli_prepare(
    $conexion,
    "SELECT e.id_empleado
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtExiste, 'ii', $idObjetivo, $idGuarderia);
  mysqli_stmt_execute($stmtExiste);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtExiste))) {
    throw new DomainException('El empleado no pertenece a la guarderia');
  }

  $nuevaFoto = cloudinary_subir_imagen(
    $rutaTemporal,
    $nombreOriginal,
    $mime,
    'safekids/uploads/empleados'
  );

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $stmtAnterior = mysqli_prepare(
    $conexion,
    "SELECT e.foto_url, e.foto_public_id
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
     LIMIT 1
     FOR UPDATE"
  );
  mysqli_stmt_bind_param($stmtAnterior, 'ii', $idObjetivo, $idGuarderia);
  mysqli_stmt_execute($stmtAnterior);
  $fotoAnterior = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAnterior));
  if (!$fotoAnterior) {
    throw new DomainException('El empleado ya no esta disponible');
  }

  $fotoUrl = $nuevaFoto['url'];
  $fotoPublicId = $nuevaFoto['public_id'];
  $stmtActualizar = mysqli_prepare(
    $conexion,
    "UPDATE empleados
     SET foto_url = ?, foto_public_id = ?
     WHERE id_empleado = ?"
  );
  mysqli_stmt_bind_param(
    $stmtActualizar,
    'ssi',
    $fotoUrl,
    $fotoPublicId,
    $idObjetivo
  );
  mysqli_stmt_execute($stmtActualizar);

  mysqli_commit($conexion);
  $transaccionActiva = false;
  $fotoConfirmada = true;

  $publicIdAnterior = trim((string) ($fotoAnterior['foto_public_id'] ?? ''));
  if ($publicIdAnterior === '') {
    $publicIdAnterior = cloudinary_public_id_desde_url(
      $fotoAnterior['foto_url'] ?? null
    ) ?? '';
  }

  $advertencia = null;
  if ($publicIdAnterior !== '' && $publicIdAnterior !== $fotoPublicId) {
    try {
      cloudinary_eliminar_imagen($publicIdAnterior);
    } catch (RuntimeException $e) {
      error_log('SafeKids: no se pudo eliminar la foto anterior del empleado');
      $advertencia = 'La foto se actualizo, pero la version anterior no pudo eliminarse';
    }
  }

  echo json_encode([
    'status' => 'success',
    'mensaje' => 'La fotografia fue actualizada correctamente',
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
      error_log('SafeKids: no se pudo limpiar una foto nueva de empleado');
    }
  }
  responderEmpleado(422, ['status' => 'error', 'mensaje' => $e->getMessage()]);
} catch (mysqli_sql_exception $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una foto nueva de empleado');
    }
  }
  responderEmpleado(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible guardar la fotografia'
  ]);
} catch (RuntimeException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una foto nueva de empleado');
    }
  }
  error_log('SafeKids: error de Cloudinary en empleados: ' . $e->getMessage());
  responderEmpleado(502, [
    'status' => 'error',
    'mensaje' => 'No fue posible procesar la fotografia en este momento'
  ]);
}
