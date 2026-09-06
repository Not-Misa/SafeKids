<?php
require_once __DIR__ . '/configuracion.php';

date_default_timezone_set('America/Mexico_City');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$rutaConfiguracionDB = rutaConfiguracionSafeKids(
  'SAFEKIDS_DB_CONFIG',
  'database.ini'
);
$configuracionDB = [];

if (is_file($rutaConfiguracionDB)) {
  $configuracionLeida = parse_ini_file(
    $rutaConfiguracionDB,
    false,
    INI_SCANNER_RAW
  );
  if (is_array($configuracionLeida)) {
    $configuracionDB = $configuracionLeida;
  }
}

$leerConfiguracionDB = static function (
  string $variableEntorno,
  string $clave,
  string $valorPredeterminado = ''
) use ($configuracionDB): string {
  $valorEntorno = getenv($variableEntorno);
  if ($valorEntorno !== false && $valorEntorno !== '') {
    return $valorEntorno;
  }

  return (string) ($configuracionDB[$clave] ?? $valorPredeterminado);
};

$servidor = $leerConfiguracionDB('SAFEKIDS_DB_HOST', 'host', '127.0.0.1');
$puerto = (int) $leerConfiguracionDB('SAFEKIDS_DB_PORT', 'port', '3306');
$baseDatos = $leerConfiguracionDB('SAFEKIDS_DB_NAME', 'database', 'safekids_v2');
$usuarioDB = $leerConfiguracionDB('SAFEKIDS_DB_USER', 'username', 'safekids_api');
$passwordDB = $leerConfiguracionDB('SAFEKIDS_DB_PASSWORD', 'password');

try {
  if ($passwordDB === '') {
    throw new RuntimeException('No se configuro la contrasena de MySQL.');
  }

  $conexion = mysqli_connect($servidor, $usuarioDB, $passwordDB, $baseDatos, $puerto);
  mysqli_set_charset($conexion, 'utf8mb4');
} catch (Throwable $e) {
  error_log('SafeKids: no fue posible conectar con MySQL.');
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Error de conexion a la base de datos'
  ]);
  exit();
}

if (PHP_SAPI !== 'cli') {
  $scriptActual = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
  $directorioApi = str_replace('\\', '/', realpath(__DIR__ . '/api') ?: '');
  $esEndpointApi = (
    $directorioApi !== '' &&
    str_starts_with($scriptActual, rtrim($directorioApi, '/') . '/')
  );
  $esLogin = basename($scriptActual) === 'login.php';

  if ($esEndpointApi && !$esLogin) {
    require_once __DIR__ . '/autenticacion.php';
    $contextoAutenticacion = requerirAutenticacion($conexion);
  }
}
