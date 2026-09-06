<?php
require_once __DIR__ . '/configuracion.php';

function obtenerCredencialesFirebase(): ?array
{
  static $credenciales = false;

  if ($credenciales !== false) {
    return $credenciales;
  }

  $ruta = rutaConfiguracionSafeKids(
    'SAFEKIDS_FIREBASE_CREDENTIALS',
    'firebase-service-account.json'
  );

  if (!is_file($ruta) || !is_readable($ruta)) {
    $credenciales = null;
    return null;
  }

  $contenido = file_get_contents($ruta);
  $datos = json_decode($contenido ?: '', true);
  $campos = ['project_id', 'client_email', 'private_key', 'token_uri'];

  if (!is_array($datos)) {
    $credenciales = null;
    return null;
  }

  foreach ($campos as $campo) {
    if (!isset($datos[$campo]) || !is_string($datos[$campo]) || trim($datos[$campo]) === '') {
      $credenciales = null;
      return null;
    }
  }

  $credenciales = $datos;
  return $credenciales;
}

function base64UrlFirebase(string $valor): string
{
  return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
}

function solicitarHttpFirebase(
  string $url,
  string $metodo,
  array $encabezados,
  ?string $contenido = null
): array {
  $curl = curl_init($url);
  curl_setopt_array($curl, [
    CURLOPT_CUSTOMREQUEST => $metodo,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => $encabezados
  ]);

  if ($contenido !== null) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, $contenido);
  }

  $respuesta = curl_exec($curl);
  $codigo = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $error = curl_error($curl);
  curl_close($curl);

  if ($respuesta === false) {
    throw new RuntimeException(
      $error !== '' ? $error : 'No fue posible conectar con Firebase'
    );
  }

  return [
    'codigo' => $codigo,
    'contenido' => $respuesta,
    'json' => json_decode($respuesta, true)
  ];
}

function obtenerAccessTokenFirebase(array $credenciales): string
{
  static $tokenCache = null;
  static $tokenExpira = 0;

  if (is_string($tokenCache) && $tokenCache !== '' && time() < $tokenExpira - 60) {
    return $tokenCache;
  }

  $ahora = time();
  $encabezado = base64UrlFirebase(json_encode([
    'alg' => 'RS256',
    'typ' => 'JWT'
  ], JSON_UNESCAPED_SLASHES));
  $carga = base64UrlFirebase(json_encode([
    'iss' => $credenciales['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud' => $credenciales['token_uri'],
    'iat' => $ahora - 30,
    'exp' => $ahora + 3600
  ], JSON_UNESCAPED_SLASHES));
  $contenidoFirmar = $encabezado . '.' . $carga;

  $firma = '';
  $firmado = openssl_sign(
    $contenidoFirmar,
    $firma,
    $credenciales['private_key'],
    OPENSSL_ALGO_SHA256
  );

  if (!$firmado) {
    throw new RuntimeException('No fue posible firmar la solicitud de Firebase');
  }

  $jwt = $contenidoFirmar . '.' . base64UrlFirebase($firma);
  $respuesta = solicitarHttpFirebase(
    $credenciales['token_uri'],
    'POST',
    ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $jwt
    ])
  );

  $datos = $respuesta['json'];
  if (
    $respuesta['codigo'] < 200 ||
    $respuesta['codigo'] >= 300 ||
    !is_array($datos) ||
    empty($datos['access_token'])
  ) {
    throw new RuntimeException('Firebase rechazo las credenciales del servidor');
  }

  $tokenCache = (string) $datos['access_token'];
  $tokenExpira = $ahora + (int) ($datos['expires_in'] ?? 3600);
  return $tokenCache;
}

function codigoErrorFcm(array $respuesta): ?string
{
  $detalles = $respuesta['json']['error']['details'] ?? [];
  if (!is_array($detalles)) {
    return null;
  }

  foreach ($detalles as $detalle) {
    if (is_array($detalle) && isset($detalle['errorCode'])) {
      return (string) $detalle['errorCode'];
    }
  }

  return null;
}

function enviarMensajeFirebase(
  array $credenciales,
  string $tokenFcm,
  array $notificacion
): array {
  $accessToken = obtenerAccessTokenFirebase($credenciales);
  $prioridad = in_array(
    $notificacion['prioridad'],
    ['IMPORTANTE', 'URGENTE'],
    true
  ) ? 'HIGH' : 'NORMAL';

  $datos = [
    'id_notificacion' => (string) $notificacion['id_notificacion'],
    'id_nino' => $notificacion['id_nino'] !== null
      ? (string) $notificacion['id_nino']
      : '',
    'alcance' => (string) $notificacion['alcance'],
    'prioridad' => (string) $notificacion['prioridad'],
    'tipo_codigo' => (string) $notificacion['tipo_codigo']
  ];

  $mensaje = [
    'message' => [
      'token' => $tokenFcm,
      'notification' => [
        'title' => (string) $notificacion['titulo'],
        'body' => (string) $notificacion['mensaje']
      ],
      'data' => $datos,
      'android' => [
        'priority' => $prioridad,
        'notification' => [
          'channel_id' => 'safekids_alertas',
          'sound' => 'default',
          'color' => '#3282E6'
        ]
      ]
    ]
  ];

  if (!empty($notificacion['imagen_url'])) {
    $mensaje['message']['notification']['image'] = (string) $notificacion['imagen_url'];
  }

  $url = sprintf(
    'https://fcm.googleapis.com/v1/projects/%s/messages:send',
    rawurlencode($credenciales['project_id'])
  );

  return solicitarHttpFirebase(
    $url,
    'POST',
    [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json; charset=UTF-8'
    ],
    json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
  );
}

function desactivarTokenFcm(mysqli $conexion, int $idDispositivo): void
{
  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE dispositivos_fcm
     SET activo = FALSE,
         ultimo_uso = NOW()
     WHERE id_dispositivo = ?"
  );
  mysqli_stmt_bind_param($stmt, 'i', $idDispositivo);
  mysqli_stmt_execute($stmt);
}

function marcarPushEnviado(
  mysqli $conexion,
  int $idNotificacion,
  int $idUsuario
): void {
  $stmt = mysqli_prepare(
    $conexion,
    "UPDATE notificacion_destinatarios
     SET enviada_push = TRUE,
         enviada_push_en = COALESCE(enviada_push_en, NOW())
     WHERE id_notificacion = ?
       AND id_usuario = ?"
  );
  mysqli_stmt_bind_param($stmt, 'ii', $idNotificacion, $idUsuario);
  mysqli_stmt_execute($stmt);
}

function enviarPushDeNotificacion(mysqli $conexion, int $idNotificacion): array
{
  $credenciales = obtenerCredencialesFirebase();
  if ($credenciales === null) {
    return [
      'configurado' => false,
      'enviados' => 0,
      'fallidos' => 0,
      'sin_dispositivo' => 0
    ];
  }

  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      no.id_notificacion,
      no.id_nino,
      no.alcance,
      no.titulo,
      no.mensaje,
      no.prioridad,
      no.imagen_url,
      tn.codigo AS tipo_codigo,
      nd.id_usuario,
      df.id_dispositivo,
      df.token_fcm
    FROM notificaciones no
    INNER JOIN tipos_notificacion tn
      ON tn.id_tipo_notificacion = no.id_tipo_notificacion
    INNER JOIN notificacion_destinatarios nd
      ON nd.id_notificacion = no.id_notificacion
    LEFT JOIN dispositivos_fcm df
      ON df.id_usuario = nd.id_usuario
      AND df.activo = TRUE
      AND df.plataforma = 'ANDROID'
    WHERE no.id_notificacion = ?
      AND no.estado = 'PUBLICADA'
      AND no.eliminado_en IS NULL
    ORDER BY nd.id_usuario, df.id_dispositivo"
  );
  mysqli_stmt_bind_param($stmt, 'i', $idNotificacion);
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);

  $enviados = 0;
  $fallidos = 0;
  $sinDispositivo = 0;
  $usuariosSinDispositivo = [];
  $usuariosEnviados = [];

  while ($fila = mysqli_fetch_assoc($resultado)) {
    $idUsuario = (int) $fila['id_usuario'];
    if ($fila['id_dispositivo'] === null || $fila['token_fcm'] === null) {
      $usuariosSinDispositivo[$idUsuario] = true;
      continue;
    }

    $respuesta = enviarMensajeFirebase(
      $credenciales,
      (string) $fila['token_fcm'],
      $fila
    );

    if ($respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300) {
      $enviados++;
      $usuariosEnviados[$idUsuario] = true;
      continue;
    }

    $fallidos++;
    $codigoFcm = codigoErrorFcm($respuesta);
    if (in_array($codigoFcm, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
      desactivarTokenFcm($conexion, (int) $fila['id_dispositivo']);
    }
  }

  foreach (array_keys($usuariosEnviados) as $idUsuario) {
    marcarPushEnviado($conexion, $idNotificacion, (int) $idUsuario);
  }

  foreach (array_keys($usuariosSinDispositivo) as $idUsuario) {
    if (!isset($usuariosEnviados[$idUsuario])) {
      $sinDispositivo++;
    }
  }

  return [
    'configurado' => true,
    'enviados' => $enviados,
    'fallidos' => $fallidos,
    'sin_dispositivo' => $sinDispositivo
  ];
}
