<?php
require_once __DIR__ . '/configuracion.php';

function configurarCors(string $metodoPermitido): void
{
  $rutaConfiguracion = rutaConfiguracionSafeKids(
    'SAFEKIDS_APP_CONFIG',
    'app.ini'
  );
  $configuracion = [];

  if (is_file($rutaConfiguracion)) {
    $configuracionLeida = parse_ini_file(
      $rutaConfiguracion,
      false,
      INI_SCANNER_RAW
    );
    if (is_array($configuracionLeida)) {
      $configuracion = $configuracionLeida;
    }
  }

  $origenesConfigurados = getenv('SAFEKIDS_CORS_ORIGINS');
  if ($origenesConfigurados === false || trim($origenesConfigurados) === '') {
    $origenesConfigurados = (string) (
      $configuracion['cors_origins'] ?? 'http://localhost:4200'
    );
  }

  $origenesPermitidos = array_values(array_filter(array_map(
    static fn(string $origen): string => rtrim(trim($origen), '/'),
    explode(',', $origenesConfigurados)
  )));
  $origenSolicitud = rtrim(trim($_SERVER['HTTP_ORIGIN'] ?? ''), '/');

  if (
    $origenSolicitud !== '' &&
    in_array($origenSolicitud, $origenesPermitidos, true)
  ) {
    header('Access-Control-Allow-Origin: ' . $origenSolicitud);
    header('Vary: Origin');
  }

  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  header('Access-Control-Allow-Methods: ' . $metodoPermitido . ', OPTIONS');
  header('Content-Type: application/json; charset=UTF-8');

  if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    return;
  }

  if (
    $origenSolicitud !== '' &&
    !in_array($origenSolicitud, $origenesPermitidos, true)
  ) {
    http_response_code(403);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Origen no permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit();
  }

  http_response_code(204);
  exit();
}
