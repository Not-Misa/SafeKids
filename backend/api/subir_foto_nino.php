<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Metodo no permitido'
  ]);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../cloudinary.php';

function responderErrorFoto(int $codigo, string $mensaje): void
{
  http_response_code($codigo);
  echo json_encode([
    'status' => 'error',
    'mensaje' => $mensaje
  ], JSON_UNESCAPED_UNICODE);
  exit();
}

$idNino = isset($_POST['id_nino']) ? (int) $_POST['id_nino'] : 0;
$idGuarderia = isset($_POST['id_guarderia']) ? (int) $_POST['id_guarderia'] : 0;
$idEmpleado = isset($_POST['id_empleado']) ? (int) $_POST['id_empleado'] : 0;

if ($idNino <= 0 || $idGuarderia <= 0 || $idEmpleado <= 0) {
  responderErrorFoto(400, 'Faltan los datos necesarios para asociar la fotografia');
}

if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
  responderErrorFoto(400, 'Selecciona una fotografia');
}

$archivo = $_FILES['foto'];
if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  responderErrorFoto(400, 'No fue posible recibir la fotografia');
}

$rutaTemporal = (string) ($archivo['tmp_name'] ?? '');
$tamano = (int) ($archivo['size'] ?? 0);
$nombreOriginal = (string) ($archivo['name'] ?? 'foto');
$tamanoMaximo = 5 * 1024 * 1024;

if ($rutaTemporal === '' || !is_uploaded_file($rutaTemporal)) {
  responderErrorFoto(400, 'El archivo recibido no es valido');
}

if ($tamano <= 0 || $tamano > $tamanoMaximo) {
  responderErrorFoto(422, 'La fotografia debe pesar como maximo 5 MB');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $rutaTemporal) : false;
if ($finfo) {
  finfo_close($finfo);
}

$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
if (!is_string($mime) || !in_array($mime, $mimesPermitidos, true)) {
  responderErrorFoto(422, 'Solo se permiten imagenes JPG, PNG o WEBP');
}

$dimensiones = getimagesize($rutaTemporal);
if (
  $dimensiones === false ||
  (int) $dimensiones[0] <= 0 ||
  (int) $dimensiones[1] <= 0 ||
  (int) $dimensiones[0] > 12000 ||
  (int) $dimensiones[1] > 12000
) {
  responderErrorFoto(422, 'Las dimensiones de la fotografia no son validas');
}

$nuevaFoto = null;
$fotoConfirmada = false;
$transaccionActiva = false;

try {
  $stmtAdministrador = mysqli_prepare(
    $conexion,
    "SELECT e.id_empleado
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND r.nombre = 'ADMIN'
       AND u.estado = 'ACTIVO'
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtAdministrador, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmtAdministrador);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtAdministrador))) {
    throw new DomainException('Solo un administrador puede cambiar la fotografia');
  }

  $stmtNino = mysqli_prepare(
    $conexion,
    "SELECT id_nino
     FROM ninos
     WHERE id_nino = ?
       AND id_guarderia = ?
       AND eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtNino, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtNino);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtNino))) {
    throw new DomainException('El nino no pertenece a la guarderia');
  }

  $nuevaFoto = cloudinary_subir_imagen(
    $rutaTemporal,
    $nombreOriginal,
    $mime
  );

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $stmtFotoAnterior = mysqli_prepare(
    $conexion,
    "SELECT foto_url, foto_public_id
     FROM ninos
     WHERE id_nino = ?
       AND id_guarderia = ?
       AND eliminado_en IS NULL
     LIMIT 1
     FOR UPDATE"
  );
  mysqli_stmt_bind_param($stmtFotoAnterior, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtFotoAnterior);
  $fotoAnterior = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtFotoAnterior));
  if (!$fotoAnterior) {
    throw new DomainException('El nino ya no esta disponible');
  }

  $fotoUrl = $nuevaFoto['url'];
  $fotoPublicId = $nuevaFoto['public_id'];
  $stmtActualizarFoto = mysqli_prepare(
    $conexion,
    "UPDATE ninos
     SET foto_url = ?, foto_public_id = ?
     WHERE id_nino = ? AND id_guarderia = ?"
  );
  mysqli_stmt_bind_param(
    $stmtActualizarFoto,
    'ssii',
    $fotoUrl,
    $fotoPublicId,
    $idNino,
    $idGuarderia
  );
  mysqli_stmt_execute($stmtActualizarFoto);

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
      error_log('SafeKids: no se pudo eliminar la foto anterior: ' . $e->getMessage());
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
      error_log('SafeKids: no se pudo limpiar una foto nueva: ' . $errorLimpieza->getMessage());
    }
  }
  responderErrorFoto(422, $e->getMessage());
} catch (mysqli_sql_exception $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una foto nueva: ' . $errorLimpieza->getMessage());
    }
  }
  error_log('SafeKids: error al guardar la foto del nino: ' . $e->getMessage());
  responderErrorFoto(500, 'No fue posible guardar la fotografia');
} catch (RuntimeException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaFoto !== null && !$fotoConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaFoto['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una foto nueva: ' . $errorLimpieza->getMessage());
    }
  }
  error_log('SafeKids: error de Cloudinary: ' . $e->getMessage());
  responderErrorFoto(502, 'No fue posible procesar la fotografia en este momento');
}
