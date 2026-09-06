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
require_once __DIR__ . '/../fcm.php';
require_once __DIR__ . '/../cloudinary.php';

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$esMultipart = stripos($contentType, 'multipart/form-data') !== false;
$datos = $esMultipart
  ? $_POST
  : json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'El contenido de la solicitud no es valido'
  ]);
  exit();
}

$idGuarderia = isset($datos['id_guarderia']) ? (int) $datos['id_guarderia'] : 0;
$idEmpleado = isset($datos['id_empleado']) ? (int) $datos['id_empleado'] : 0;
$idNino = isset($datos['id_nino']) ? (int) $datos['id_nino'] : 0;
$alcance = strtoupper(trim($datos['alcance'] ?? ''));
$tipoCodigo = strtoupper(trim($datos['tipo_codigo'] ?? ''));
$titulo = trim($datos['titulo'] ?? '');
$mensaje = trim($datos['mensaje'] ?? '');
$prioridad = strtoupper(trim($datos['prioridad'] ?? 'NORMAL'));
$valorConfirmacion = $datos['requiere_confirmacion'] ?? false;
$requiereConfirmacion = (
  $valorConfirmacion === true ||
  in_array(
    strtolower(trim((string) $valorConfirmacion)),
    ['1', 'true', 'si', 'on'],
    true
  )
) ? 1 : 0;

$archivoImagen = isset($_FILES['foto']) && is_array($_FILES['foto'])
  ? $_FILES['foto']
  : null;
$hayImagen = $archivoImagen !== null
  && ($archivoImagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$rutaTemporal = '';
$nombreOriginal = '';
$mimeImagen = '';

$alcancesValidos = ['GLOBAL', 'PERSONAL'];
$prioridadesValidas = ['NORMAL', 'IMPORTANTE', 'URGENTE'];

if (
  $idGuarderia <= 0 ||
  $idEmpleado <= 0 ||
  !in_array($alcance, $alcancesValidos, true) ||
  $tipoCodigo === '' ||
  $titulo === '' ||
  $mensaje === ''
) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Completa todos los campos obligatorios'
  ]);
  exit();
}

if (!in_array($prioridad, $prioridadesValidas, true)) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'La prioridad seleccionada no es valida'
  ]);
  exit();
}

if (strlen($titulo) > 150) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'El asunto no puede superar 150 caracteres'
  ]);
  exit();
}

if ($alcance === 'PERSONAL' && $idNino <= 0) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'La notificacion personal requiere un nino'
  ]);
  exit();
}

if ($alcance === 'GLOBAL') {
  $idNino = 0;
}

$nuevaImagen = null;
$imagenConfirmada = false;
$transaccionActiva = false;

if ($hayImagen) {
  if (($archivoImagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'No fue posible recibir la imagen adjunta'
    ]);
    exit();
  }

  $rutaTemporal = (string) ($archivoImagen['tmp_name'] ?? '');
  $nombreOriginal = (string) ($archivoImagen['name'] ?? 'notificacion');
  $tamanoImagen = (int) ($archivoImagen['size'] ?? 0);

  if ($rutaTemporal === '' || !is_uploaded_file($rutaTemporal)) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'El archivo adjunto no es valido'
    ]);
    exit();
  }

  if ($tamanoImagen <= 0 || $tamanoImagen > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'La imagen debe pesar como maximo 5 MB'
    ]);
    exit();
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeDetectado = $finfo ? finfo_file($finfo, $rutaTemporal) : false;
  if ($finfo) {
    finfo_close($finfo);
  }

  if (
    !is_string($mimeDetectado) ||
    !in_array($mimeDetectado, ['image/jpeg', 'image/png', 'image/webp'], true)
  ) {
    http_response_code(422);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Solo se permiten imagenes JPG, PNG o WEBP'
    ]);
    exit();
  }
  $mimeImagen = $mimeDetectado;

  $dimensiones = getimagesize($rutaTemporal);
  if (
    $dimensiones === false ||
    (int) $dimensiones[0] <= 0 ||
    (int) $dimensiones[1] <= 0 ||
    (int) $dimensiones[0] > 12000 ||
    (int) $dimensiones[1] > 12000
  ) {
    http_response_code(422);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Las dimensiones de la imagen no son validas'
    ]);
    exit();
  }
}

try {
  $sqlEmpleado = "
    SELECT e.id_empleado
    FROM empleados e
    INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
    WHERE e.id_empleado = ?
      AND u.id_guarderia = ?
      AND u.estado = 'ACTIVO'
      AND e.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
    LIMIT 1
  ";
  $stmtEmpleado = mysqli_prepare($conexion, $sqlEmpleado);
  mysqli_stmt_bind_param($stmtEmpleado, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmtEmpleado);

  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtEmpleado))) {
    throw new DomainException('El empleado no pertenece a la guarderia');
  }

  $sqlTipo = "
    SELECT id_tipo_notificacion
    FROM tipos_notificacion
    WHERE codigo = ?
      AND activo = TRUE
    LIMIT 1
  ";
  $stmtTipo = mysqli_prepare($conexion, $sqlTipo);
  mysqli_stmt_bind_param($stmtTipo, 's', $tipoCodigo);
  mysqli_stmt_execute($stmtTipo);
  $tipo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTipo));

  if (!$tipo) {
    throw new DomainException('El tipo de notificacion no es valido');
  }

  if ($alcance === 'PERSONAL') {
    $sqlNino = "
      SELECT id_nino
      FROM ninos
      WHERE id_nino = ?
        AND id_guarderia = ?
        AND estado = 'ACTIVO'
        AND eliminado_en IS NULL
      LIMIT 1
    ";
    $stmtNino = mysqli_prepare($conexion, $sqlNino);
    mysqli_stmt_bind_param($stmtNino, 'ii', $idNino, $idGuarderia);
    mysqli_stmt_execute($stmtNino);

    if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtNino))) {
      throw new DomainException('El nino no pertenece a la guarderia');
    }
  }

  $idTipo = (int) $tipo['id_tipo_notificacion'];
  $idNinoInsert = $alcance === 'PERSONAL' ? $idNino : null;

  if ($hayImagen) {
    $nuevaImagen = cloudinary_subir_imagen(
      $rutaTemporal,
      $nombreOriginal,
      $mimeImagen,
      'safekids/uploads/notificaciones',
      'c_limit,h_1200,w_1600'
    );
  }

  mysqli_begin_transaction($conexion);
  $transaccionActiva = true;

  $imagenUrl = $nuevaImagen['url'] ?? null;
  $imagenPublicId = $nuevaImagen['public_id'] ?? null;
  $sqlNotificacion = "
    INSERT INTO notificaciones (
      id_guarderia,
      id_empleado,
      id_tipo_notificacion,
      id_nino,
      alcance,
      titulo,
      mensaje,
      prioridad,
      imagen_url,
      imagen_public_id,
      requiere_confirmacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ";
  $stmtNotificacion = mysqli_prepare($conexion, $sqlNotificacion);
  mysqli_stmt_bind_param(
    $stmtNotificacion,
    'iiiissssssi',
    $idGuarderia,
    $idEmpleado,
    $idTipo,
    $idNinoInsert,
    $alcance,
    $titulo,
    $mensaje,
    $prioridad,
    $imagenUrl,
    $imagenPublicId,
    $requiereConfirmacion
  );
  mysqli_stmt_execute($stmtNotificacion);
  $idNotificacion = (int) mysqli_insert_id($conexion);

  if ($alcance === 'PERSONAL') {
    $sqlDestinatarios = "
      INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario)
      SELECT DISTINCT ?, t.id_usuario
      FROM nino_tutor nt
      INNER JOIN tutores t ON t.id_tutor = nt.id_tutor
      INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
      WHERE nt.id_nino = ?
        AND nt.recibe_notificaciones = TRUE
        AND u.id_guarderia = ?
        AND u.estado = 'ACTIVO'
        AND t.eliminado_en IS NULL
        AND u.eliminado_en IS NULL
    ";
    $stmtDestinatarios = mysqli_prepare($conexion, $sqlDestinatarios);
    mysqli_stmt_bind_param(
      $stmtDestinatarios,
      'iii',
      $idNotificacion,
      $idNino,
      $idGuarderia
    );
  } else {
    $sqlDestinatarios = "
      INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario)
      SELECT DISTINCT ?, u.id_usuario
      FROM usuarios u
      INNER JOIN roles r ON r.id_rol = u.id_rol
      INNER JOIN tutores t ON t.id_usuario = u.id_usuario
      WHERE u.id_guarderia = ?
        AND r.nombre = 'TUTOR'
        AND u.estado = 'ACTIVO'
        AND u.eliminado_en IS NULL
        AND t.eliminado_en IS NULL
    ";
    $stmtDestinatarios = mysqli_prepare($conexion, $sqlDestinatarios);
    mysqli_stmt_bind_param($stmtDestinatarios, 'ii', $idNotificacion, $idGuarderia);
  }

  mysqli_stmt_execute($stmtDestinatarios);
  $cantidadDestinatarios = mysqli_stmt_affected_rows($stmtDestinatarios);

  if ($cantidadDestinatarios <= 0) {
    throw new DomainException(
      $alcance === 'PERSONAL'
        ? 'El nino no tiene tutores habilitados para recibir notificaciones'
        : 'La guarderia no tiene tutores habilitados para recibir notificaciones'
    );
  }

  mysqli_commit($conexion);
  $transaccionActiva = false;
  $imagenConfirmada = true;

  try {
    $resultadoPush = enviarPushDeNotificacion($conexion, $idNotificacion);
  } catch (Throwable $e) {
    $resultadoPush = [
      'configurado' => obtenerCredencialesFirebase() !== null,
      'enviados' => 0,
      'fallidos' => $cantidadDestinatarios,
      'sin_dispositivo' => 0
    ];
  }

  http_response_code(201);
  echo json_encode([
    'status' => 'success',
    'mensaje' => $alcance === 'PERSONAL'
      ? 'Notificacion personal enviada correctamente'
      : 'Notificacion global enviada correctamente',
    'id_notificacion' => $idNotificacion,
    'destinatarios' => $cantidadDestinatarios,
    'imagen_url' => $imagenUrl,
    'push' => $resultadoPush
  ], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaImagen !== null && !$imagenConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaImagen['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una imagen nueva de notificacion');
    }
  }
  http_response_code(422);
  echo json_encode([
    'status' => 'error',
    'mensaje' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaImagen !== null && !$imagenConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaImagen['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una imagen nueva de notificacion');
    }
  }
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible guardar la notificacion'
  ]);
} catch (RuntimeException $e) {
  if ($transaccionActiva) {
    mysqli_rollback($conexion);
  }
  if ($nuevaImagen !== null && !$imagenConfirmada) {
    try {
      cloudinary_eliminar_imagen($nuevaImagen['public_id']);
    } catch (RuntimeException $errorLimpieza) {
      error_log('SafeKids: no se pudo limpiar una imagen nueva de notificacion');
    }
  }
  error_log('SafeKids: error de Cloudinary en notificaciones: ' . $e->getMessage());
  http_response_code(502);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible procesar la imagen adjunta en este momento'
  ]);
}
