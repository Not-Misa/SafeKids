<?php
require_once __DIR__ . '/../cors.php';
configurarCors('POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../autenticacion.php';

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
  responderAutenticacion(400, 'El contenido de la solicitud no es valido');
}

$email = trim((string) ($datos['usuario_login'] ?? ''));
$password = (string) ($datos['password_login'] ?? '');
$cliente = strtoupper(trim((string) ($datos['cliente'] ?? '')));

if ($email === '' || $password === '') {
  responderAutenticacion(400, 'No se recibieron credenciales');
}

if (!in_array($cliente, ['WEB', 'ANDROID'], true)) {
  responderAutenticacion(400, 'El cliente de acceso no es valido');
}

$sql = "
  SELECT
    u.id_usuario,
    u.id_guarderia,
    u.email,
    u.password_hash,
    u.requiere_cambio_password,
    u.estado,
    u.intentos_fallidos,
    u.bloqueado_hasta,
    r.nombre AS rol,
    e.id_empleado,
    e.nombres AS empleado_nombres,
    e.apellidos AS empleado_apellidos,
    e.puesto,
    e.foto_url AS empleado_foto_url,
    t.id_tutor,
    t.nombres AS tutor_nombres,
    t.apellidos AS tutor_apellidos,
    t.telefono AS tutor_telefono,
    t.telefono_alterno AS tutor_telefono_alterno,
    t.direccion AS tutor_direccion,
    t.foto_url AS tutor_foto_url
  FROM usuarios u
  INNER JOIN roles r ON r.id_rol = u.id_rol
  LEFT JOIN empleados e
    ON e.id_usuario = u.id_usuario
    AND e.eliminado_en IS NULL
  LEFT JOIN tutores t
    ON t.id_usuario = u.id_usuario
    AND t.eliminado_en IS NULL
  WHERE u.email = ?
    AND u.eliminado_en IS NULL
  LIMIT 1
";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (
  $usuario &&
  $usuario['bloqueado_hasta'] !== null &&
  strtotime($usuario['bloqueado_hasta']) > time()
) {
  responderAutenticacion(
    429,
    'La cuenta esta bloqueada temporalmente. Intenta de nuevo mas tarde',
    ['bloqueado_hasta' => $usuario['bloqueado_hasta']]
  );
}

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
  if ($usuario) {
    $bloqueoAnteriorVencio = (
      $usuario['bloqueado_hasta'] !== null &&
      strtotime($usuario['bloqueado_hasta']) <= time()
    );
    $intentos = ($bloqueoAnteriorVencio ? 0 : (int) $usuario['intentos_fallidos']) + 1;
    $idUsuario = (int) $usuario['id_usuario'];

    if ($intentos >= 5) {
      $stmtFallo = mysqli_prepare(
        $conexion,
        "UPDATE usuarios
         SET intentos_fallidos = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE id_usuario = ?"
      );
      mysqli_stmt_bind_param($stmtFallo, 'ii', $intentos, $idUsuario);
    } else {
      $stmtFallo = mysqli_prepare(
        $conexion,
        "UPDATE usuarios
         SET intentos_fallidos = ?, bloqueado_hasta = NULL
         WHERE id_usuario = ?"
      );
      mysqli_stmt_bind_param($stmtFallo, 'ii', $intentos, $idUsuario);
    }
    mysqli_stmt_execute($stmtFallo);
  }

  responderAutenticacion(401, 'Correo o contrasena invalidos');
}

if ($usuario['estado'] !== 'ACTIVO') {
  responderAutenticacion(403, 'La cuenta no esta activa');
}

$rolesPermitidos = $cliente === 'WEB'
  ? ['ADMIN', 'EMPLEADO']
  : ['TUTOR'];

if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
  $mensaje = $cliente === 'WEB'
    ? 'La cuenta de tutor solo puede acceder desde la app movil'
    : 'La app movil es exclusiva para padres y tutores';
  responderAutenticacion(403, $mensaje);
}

$idUsuario = (int) $usuario['id_usuario'];
$stmtAcceso = mysqli_prepare(
  $conexion,
  "UPDATE usuarios
   SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
   WHERE id_usuario = ?"
);
mysqli_stmt_bind_param($stmtAcceso, 'i', $idUsuario);
mysqli_stmt_execute($stmtAcceso);

$sesion = crearSesionApi($conexion, $idUsuario, $cliente);

echo json_encode([
  'status' => 'success',
  'mensaje' => 'Bienvenido a SafeKids',
  'token' => $sesion['token'],
  'token_expira_en' => $sesion['expira_en'],
  'usuario' => [
    'id_usuario' => $idUsuario,
    'id_guarderia' => $usuario['id_guarderia'] !== null
      ? (int) $usuario['id_guarderia']
      : null,
    'email' => $usuario['email'],
    'rol' => $usuario['rol'],
    'requiere_cambio_password' => (bool) $usuario['requiere_cambio_password'],
    'empleado' => $usuario['id_empleado'] !== null ? [
      'id_empleado' => (int) $usuario['id_empleado'],
      'nombres' => $usuario['empleado_nombres'],
      'apellidos' => $usuario['empleado_apellidos'],
      'puesto' => $usuario['puesto'],
      'foto_url' => $usuario['empleado_foto_url']
    ] : null,
    'tutor' => $usuario['id_tutor'] !== null ? [
      'id_tutor' => (int) $usuario['id_tutor'],
      'nombres' => $usuario['tutor_nombres'],
      'apellidos' => $usuario['tutor_apellidos'],
      'telefono' => $usuario['tutor_telefono'],
      'telefono_alterno' => $usuario['tutor_telefono_alterno'],
      'direccion' => $usuario['tutor_direccion'],
      'foto_url' => $usuario['tutor_foto_url']
    ] : null
  ]
], JSON_UNESCAPED_UNICODE);
