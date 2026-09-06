<?php

function responderAutenticacion(int $codigo, string $mensaje, array $adicional = []): never
{
  http_response_code($codigo);
  echo json_encode(array_merge([
    'status' => 'error',
    'mensaje' => $mensaje
  ], $adicional), JSON_UNESCAPED_UNICODE);
  exit();
}

function rolesPermitidosPorEndpoint(string $endpoint): ?array
{
  $politicas = [
    'logout.php' => ['ADMIN', 'EMPLEADO', 'TUTOR'],
    'cambiar_password.php' => ['ADMIN', 'EMPLEADO', 'TUTOR'],

    'mis_hijos.php' => ['TUTOR'],
    'perfil_hijo.php' => ['TUTOR'],
    'notificaciones_de_mi_hijo.php' => ['TUTOR'],
    'reportes_de_mi_hijo.php' => ['TUTOR'],
    'marcar_notificacion_vista.php' => ['TUTOR'],
    'registrar_dispositivo.php' => ['TUTOR'],
    'desregistrar_dispositivo.php' => ['TUTOR'],

    'buscar_ninos.php' => ['ADMIN', 'EMPLEADO'],
    'listar_ninos.php' => ['ADMIN', 'EMPLEADO'],
    'nino_detalle.php' => ['ADMIN', 'EMPLEADO'],
    'listar_reportes.php' => ['ADMIN', 'EMPLEADO'],
    'listar_notificaciones_nino.php' => ['ADMIN', 'EMPLEADO'],
    'tipos_evento.php' => ['ADMIN', 'EMPLEADO'],
    'listar_avisos.php' => ['ADMIN', 'EMPLEADO'],
    'crear_notificacion.php' => ['ADMIN', 'EMPLEADO'],
    'crear_reporte.php' => ['ADMIN', 'EMPLEADO'],
    'subir_foto_evento.php' => ['ADMIN', 'EMPLEADO'],
    'subir_foto_nino.php' => ['ADMIN'],
    'cambiar_estado_nino.php' => ['ADMIN'],
    'buscar_tutores.php' => ['ADMIN'],
    'registrar_nino.php' => ['ADMIN'],
    'actualizar_nino.php' => ['ADMIN'],
    'empleado_detalle.php' => ['ADMIN', 'EMPLEADO'],

    'listar_empleados.php' => ['ADMIN'],
    'actualizar_empleado.php' => ['ADMIN'],
    'registrar_empleado.php' => ['ADMIN'],
    'cambiar_estado_empleado.php' => ['ADMIN'],
    'subir_foto_empleado.php' => ['ADMIN'],
    'restablecer_password.php' => ['ADMIN']
  ];

  return $politicas[$endpoint] ?? null;
}

function obtenerTokenBearer(): ?string
{
  $encabezado = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

  if ($encabezado === '' && function_exists('getallheaders')) {
    $encabezados = getallheaders();
    foreach ($encabezados as $nombre => $valor) {
      if (strcasecmp($nombre, 'Authorization') === 0) {
        $encabezado = $valor;
        break;
      }
    }
  }

  if (!preg_match('/^Bearer\s+([A-Za-z0-9_-]+)$/i', trim($encabezado), $coincidencias)) {
    return null;
  }

  return $coincidencias[1];
}

function crearSesionApi(
  mysqli $conexion,
  int $idUsuario,
  string $cliente
): array {
  $token = 'sk_' . bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $token);
  $duracion = $cliente === 'ANDROID' ? '+30 days' : '+12 hours';
  $expiraEn = (new DateTimeImmutable($duracion))->format('Y-m-d H:i:s');

  $stmt = mysqli_prepare(
    $conexion,
    "INSERT INTO refresh_tokens (id_usuario, token_hash, expira_en)
     VALUES (?, ?, ?)"
  );
  mysqli_stmt_bind_param($stmt, 'iss', $idUsuario, $tokenHash, $expiraEn);
  mysqli_stmt_execute($stmt);

  return [
    'token' => $token,
    'expira_en' => $expiraEn
  ];
}

function revocarSesionApi(mysqli $conexion, int $idSesion): void
{
  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE refresh_tokens
     SET revocado_en = COALESCE(revocado_en, NOW())
     WHERE id_refresh_token = ?"
  );
  mysqli_stmt_bind_param($stmt, 'i', $idSesion);
  mysqli_stmt_execute($stmt);
}

function revocarSesionesUsuario(mysqli $conexion, int $idUsuario): void
{
  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE refresh_tokens
     SET revocado_en = COALESCE(revocado_en, NOW())
     WHERE id_usuario = ?"
  );
  mysqli_stmt_bind_param($stmt, 'i', $idUsuario);
  mysqli_stmt_execute($stmt);
}

function errorPoliticaPassword(string $password): ?string
{
  if (strlen($password) < 10 || strlen($password) > 72) {
    return 'La contrasena debe tener entre 10 y 72 caracteres';
  }

  if (!preg_match('/[A-Z]/', $password)) {
    return 'La contrasena debe incluir una letra mayuscula';
  }

  if (!preg_match('/[a-z]/', $password)) {
    return 'La contrasena debe incluir una letra minuscula';
  }

  if (!preg_match('/[0-9]/', $password)) {
    return 'La contrasena debe incluir un numero';
  }

  if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    return 'La contrasena debe incluir un caracter especial';
  }

  return null;
}

function generarPasswordTemporalSegura(): string
{
  return 'Sk!' . bin2hex(random_bytes(5)) . random_int(10, 99);
}

function datosIdentidadSolicitud(): array
{
  $datos = array_merge($_GET, $_POST);
  $tipoContenido = $_SERVER['CONTENT_TYPE'] ?? '';

  if (stripos($tipoContenido, 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $decodificado = json_decode($json, true);
    if (is_array($decodificado)) {
      $datos = array_merge($datos, $decodificado);
    }
  }

  return $datos;
}

function validarIdentidadSolicitud(array $contexto): void
{
  $datos = datosIdentidadSolicitud();
  $comparaciones = [
    'id_guarderia' => $contexto['id_guarderia'],
    'id_usuario' => $contexto['id_usuario'],
    'id_empleado' => $contexto['id_empleado']
  ];

  foreach ($comparaciones as $campo => $esperado) {
    if (!array_key_exists($campo, $datos)) {
      continue;
    }

    $recibido = (int) $datos[$campo];
    if ($esperado === null || $recibido !== (int) $esperado) {
      responderAutenticacion(
        403,
        'La identidad de la solicitud no coincide con la sesion activa'
      );
    }
  }
}

function requerirAutenticacion(mysqli $conexion): array
{
  $endpoint = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
  $rolesPermitidos = rolesPermitidosPorEndpoint($endpoint);

  if ($rolesPermitidos === null) {
    responderAutenticacion(403, 'El endpoint no tiene una politica de acceso configurada');
  }

  $token = obtenerTokenBearer();
  if ($token === null) {
    responderAutenticacion(401, 'Debes iniciar sesion para continuar');
  }

  $tokenHash = hash('sha256', $token);
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      s.id_refresh_token,
      s.expira_en,
      u.id_usuario,
      u.id_guarderia,
      u.requiere_cambio_password,
      r.nombre AS rol,
      e.id_empleado,
      t.id_tutor
    FROM refresh_tokens s
    INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
    INNER JOIN roles r ON r.id_rol = u.id_rol
    LEFT JOIN empleados e
      ON e.id_usuario = u.id_usuario
      AND e.eliminado_en IS NULL
    LEFT JOIN tutores t
      ON t.id_usuario = u.id_usuario
      AND t.eliminado_en IS NULL
    WHERE s.token_hash = ?
      AND s.revocado_en IS NULL
      AND s.expira_en > NOW()
      AND u.estado = 'ACTIVO'
      AND u.eliminado_en IS NULL
    LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 's', $tokenHash);
  mysqli_stmt_execute($stmt);
  $sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  if (!$sesion) {
    responderAutenticacion(401, 'La sesion no es valida o ha expirado');
  }

  if (!in_array($sesion['rol'], $rolesPermitidos, true)) {
    responderAutenticacion(403, 'No tienes permisos para realizar esta accion');
  }

  $contexto = [
    'id_sesion' => (int) $sesion['id_refresh_token'],
    'id_usuario' => (int) $sesion['id_usuario'],
    'id_guarderia' => $sesion['id_guarderia'] !== null
      ? (int) $sesion['id_guarderia']
      : null,
    'requiere_cambio_password' => (bool) $sesion['requiere_cambio_password'],
    'rol' => $sesion['rol'],
    'id_empleado' => $sesion['id_empleado'] !== null
      ? (int) $sesion['id_empleado']
      : null,
    'id_tutor' => $sesion['id_tutor'] !== null
      ? (int) $sesion['id_tutor']
      : null,
    'expira_en' => $sesion['expira_en']
  ];

  if (
    $contexto['requiere_cambio_password'] &&
    !in_array($endpoint, ['cambiar_password.php', 'logout.php'], true)
  ) {
    responderAutenticacion(403, 'Debes actualizar tu contrasena para continuar', [
      'codigo' => 'CAMBIO_PASSWORD_REQUERIDO'
    ]);
  }

  validarIdentidadSolicitud($contexto);

  return $contexto;
}
