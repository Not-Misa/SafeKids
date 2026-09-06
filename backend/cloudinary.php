<?php
require_once __DIR__ . '/configuracion.php';

function cloudinary_configuracion(): array
{
  static $configuracion = null;

  if ($configuracion !== null) {
    return $configuracion;
  }

  $ruta = rutaConfiguracionSafeKids(
    'SAFEKIDS_CLOUDINARY_CONFIG',
    'cloudinary.ini'
  );

  if (!is_file($ruta) || !is_readable($ruta)) {
    throw new RuntimeException('No se encontro la configuracion segura de Cloudinary');
  }

  $valores = parse_ini_file($ruta, false, INI_SCANNER_RAW);
  if (!is_array($valores)) {
    throw new RuntimeException('La configuracion de Cloudinary no es valida');
  }

  foreach (
    ['CLOUDINARY_CLOUD_NAME', 'CLOUDINARY_API_KEY', 'CLOUDINARY_API_SECRET']
    as $clave
  ) {
    if (!isset($valores[$clave]) || trim((string) $valores[$clave]) === '') {
      throw new RuntimeException('La configuracion de Cloudinary esta incompleta');
    }
  }

  $configuracion = [
    'cloud_name' => trim((string) $valores['CLOUDINARY_CLOUD_NAME']),
    'api_key' => trim((string) $valores['CLOUDINARY_API_KEY']),
    'api_secret' => trim((string) $valores['CLOUDINARY_API_SECRET'])
  ];

  return $configuracion;
}

function cloudinary_valor_firma($valor): string
{
  if (is_bool($valor)) {
    return $valor ? 'true' : 'false';
  }

  if (is_array($valor)) {
    return implode(',', array_map('cloudinary_valor_firma', $valor));
  }

  return (string) $valor;
}

function cloudinary_firma(array $parametros, string $apiSecret): string
{
  unset(
    $parametros['file'],
    $parametros['api_key'],
    $parametros['cloud_name'],
    $parametros['resource_type'],
    $parametros['signature']
  );

  $parametros = array_filter(
    $parametros,
    static fn($valor) => $valor !== null && $valor !== ''
  );
  ksort($parametros, SORT_STRING);

  $partes = [];
  foreach ($parametros as $clave => $valor) {
    $partes[] = $clave . '=' . cloudinary_valor_firma($valor);
  }

  return sha1(implode('&', $partes) . $apiSecret);
}

function cloudinary_solicitud(string $url, array $campos): array
{
  $curl = curl_init($url);
  if ($curl === false) {
    throw new RuntimeException('No fue posible iniciar la conexion con Cloudinary');
  }

  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $campos,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 35,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
  ]);

  $respuestaCruda = curl_exec($curl);
  $errorCurl = curl_error($curl);
  $codigoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  if ($respuestaCruda === false) {
    throw new RuntimeException('No fue posible comunicarse con Cloudinary: ' . $errorCurl);
  }

  $respuesta = json_decode($respuestaCruda, true);
  if (!is_array($respuesta)) {
    throw new RuntimeException('Cloudinary devolvio una respuesta no valida');
  }

  if ($codigoHttp < 200 || $codigoHttp >= 300) {
    $detalle = trim((string) ($respuesta['error']['message'] ?? 'Solicitud rechazada'));
    throw new RuntimeException('Cloudinary rechazo la imagen: ' . $detalle);
  }

  return $respuesta;
}

function cloudinary_url_optimizada(
  string $cloudName,
  string $publicId,
  int $version
): string {
  $segmentos = array_map('rawurlencode', explode('/', $publicId));
  $rutaPublica = implode('/', $segmentos);
  $versionUrl = $version > 0 ? '/v' . $version : '';

  return 'https://res.cloudinary.com/'
    . rawurlencode($cloudName)
    . '/image/upload/f_auto/q_auto'
    . $versionUrl
    . '/'
    . $rutaPublica;
}

function cloudinary_public_id_desde_url(?string $url): ?string
{
  if ($url === null || trim($url) === '') {
    return null;
  }

  $ruta = parse_url($url, PHP_URL_PATH);
  if (!is_string($ruta)) {
    return null;
  }

  $marcador = '/image/upload/';
  $posicion = strpos($ruta, $marcador);
  if ($posicion === false) {
    return null;
  }

  $segmentos = explode('/', substr($ruta, $posicion + strlen($marcador)));
  $indiceVersion = null;
  foreach ($segmentos as $indice => $segmento) {
    if (preg_match('/^v\d+$/', $segmento) === 1) {
      $indiceVersion = $indice;
      break;
    }
  }

  if ($indiceVersion === null || $indiceVersion >= count($segmentos) - 1) {
    return null;
  }

  $rutaPublica = urldecode(implode('/', array_slice($segmentos, $indiceVersion + 1)));
  $extension = pathinfo($rutaPublica, PATHINFO_EXTENSION);
  if ($extension !== '') {
    $rutaPublica = substr($rutaPublica, 0, -(strlen($extension) + 1));
  }

  return $rutaPublica === '' ? null : $rutaPublica;
}

function cloudinary_subir_imagen(
  string $rutaTemporal,
  string $nombreOriginal,
  string $mime,
  string $carpeta = 'safekids/uploads/ninos',
  string $transformacion = 'c_fill,g_face,h_600,w_600'
): array {
  $carpetasPermitidas = [
    'safekids/uploads/ninos',
    'safekids/uploads/empleados',
    'safekids/uploads/tutores',
    'safekids/uploads/reportes',
    'safekids/uploads/notificaciones'
  ];
  if (!in_array($carpeta, $carpetasPermitidas, true)) {
    throw new RuntimeException('La carpeta de imagenes no esta permitida');
  }

  $configuracion = cloudinary_configuracion();
  $parametros = [
    'folder' => $carpeta,
    'timestamp' => time(),
    'transformation' => $transformacion
  ];
  $firma = cloudinary_firma($parametros, $configuracion['api_secret']);

  $campos = $parametros;
  $campos['api_key'] = $configuracion['api_key'];
  $campos['signature'] = $firma;
  $campos['file'] = new CURLFile($rutaTemporal, $mime, basename($nombreOriginal));

  $url = 'https://api.cloudinary.com/v1_1/'
    . rawurlencode($configuracion['cloud_name'])
    . '/image/upload';
  $respuesta = cloudinary_solicitud($url, $campos);

  $publicId = trim((string) ($respuesta['public_id'] ?? ''));
  $version = (int) ($respuesta['version'] ?? 0);
  if ($publicId === '' || $version <= 0) {
    throw new RuntimeException('Cloudinary no devolvio los datos de la imagen');
  }

  return [
    'public_id' => $publicId,
    'url' => cloudinary_url_optimizada(
      $configuracion['cloud_name'],
      $publicId,
      $version
    ),
    'width' => (int) ($respuesta['width'] ?? 0),
    'height' => (int) ($respuesta['height'] ?? 0),
    'format' => (string) ($respuesta['format'] ?? '')
  ];
}

function cloudinary_eliminar_imagen(string $publicId): string
{
  $configuracion = cloudinary_configuracion();
  $parametros = [
    'invalidate' => 'true',
    'public_id' => $publicId,
    'timestamp' => time()
  ];
  $firma = cloudinary_firma($parametros, $configuracion['api_secret']);

  $campos = $parametros;
  $campos['api_key'] = $configuracion['api_key'];
  $campos['signature'] = $firma;

  $url = 'https://api.cloudinary.com/v1_1/'
    . rawurlencode($configuracion['cloud_name'])
    . '/image/destroy';
  $respuesta = cloudinary_solicitud($url, $campos);

  return (string) ($respuesta['result'] ?? '');
}
