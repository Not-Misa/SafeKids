<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'mensaje' => 'Metodo no permitido']);
  exit();
}

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/padres_utilidades.php';

$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;
$idUsuario = isset($_GET['id_usuario']) ? (int) $_GET['id_usuario'] : 0;
$idNino = isset($_GET['id_nino']) ? (int) $_GET['id_nino'] : 0;

if ($idGuarderia <= 0 || $idUsuario <= 0 || $idNino <= 0) {
  responderPadre(400, [
    'status' => 'error',
    'mensaje' => 'Los datos del perfil no son validos'
  ]);
}

try {
  $tutor = obtenerTutorActivo($conexion, $idUsuario, $idGuarderia);
  if (!$tutor) {
    responderPadre(403, [
      'status' => 'error',
      'mensaje' => 'No tienes permisos para consultar este perfil'
    ]);
  }

  $nino = obtenerHijoDeTutor($conexion, $idNino, (int) $tutor['id_tutor'], $idGuarderia);
  if (!$nino) {
    responderPadre(404, [
      'status' => 'error',
      'mensaje' => 'El hijo no existe o no esta vinculado a tu cuenta'
    ]);
  }

  $stmtExpediente = mysqli_prepare(
    $conexion,
    "SELECT
      tipo_sangre,
      alergias,
      padecimientos,
      medicamentos_habituales,
      restricciones_alimentarias,
      medico_nombre,
      medico_telefono,
      institucion_medica,
      numero_seguro,
      indicaciones_emergencia,
      observaciones
    FROM expedientes_medicos
    WHERE id_nino = ?
    LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtExpediente, 'i', $idNino);
  mysqli_stmt_execute($stmtExpediente);
  $expediente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtExpediente)) ?: [];

  $stmtTutores = mysqli_prepare(
    $conexion,
    "SELECT
      t.id_tutor,
      t.nombres,
      t.apellidos,
      t.telefono,
      t.telefono_alterno,
      t.direccion,
      t.foto_url,
      u.email,
      nt.parentesco,
      nt.es_principal,
      nt.recibe_notificaciones,
      nt.autorizado_recoger,
      nt.prioridad_contacto
    FROM nino_tutor nt
    INNER JOIN tutores t ON t.id_tutor = nt.id_tutor
    INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
    WHERE nt.id_nino = ?
      AND u.id_guarderia = ?
      AND u.estado = 'ACTIVO'
      AND u.eliminado_en IS NULL
      AND t.eliminado_en IS NULL
    ORDER BY nt.es_principal DESC, nt.prioridad_contacto ASC, t.apellidos ASC"
  );
  mysqli_stmt_bind_param($stmtTutores, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtTutores);
  $resultadoTutores = mysqli_stmt_get_result($stmtTutores);

  $tutores = [];
  while ($fila = mysqli_fetch_assoc($resultadoTutores)) {
    $tutores[] = [
      'id_tutor' => (int) $fila['id_tutor'],
      'nombres' => $fila['nombres'],
      'apellidos' => $fila['apellidos'],
      'telefono' => $fila['telefono'],
      'telefono_alterno' => $fila['telefono_alterno'],
      'direccion' => $fila['direccion'],
      'foto_url' => $fila['foto_url'],
      'email' => $fila['email'],
      'parentesco' => $fila['parentesco'],
      'es_principal' => (bool) $fila['es_principal'],
      'recibe_notificaciones' => (bool) $fila['recibe_notificaciones'],
      'autorizado_recoger' => (bool) $fila['autorizado_recoger'],
      'prioridad_contacto' => $fila['prioridad_contacto'] !== null
        ? (int) $fila['prioridad_contacto']
        : null
    ];
  }

  $stmtContactos = mysqli_prepare(
    $conexion,
    "SELECT
      c.id_contacto,
      c.nombres,
      c.apellidos,
      c.parentesco,
      c.telefono,
      c.email,
      c.direccion,
      c.foto_url,
      c.identificacion_referencia,
      nc.es_contacto_emergencia,
      nc.autorizado_recoger,
      nc.prioridad_emergencia,
      nc.observaciones
    FROM nino_contacto nc
    INNER JOIN contactos_autorizados c ON c.id_contacto = nc.id_contacto
    WHERE nc.id_nino = ?
      AND c.id_guarderia = ?
      AND c.eliminado_en IS NULL
    ORDER BY nc.prioridad_emergencia ASC, c.apellidos ASC"
  );
  mysqli_stmt_bind_param($stmtContactos, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtContactos);
  $resultadoContactos = mysqli_stmt_get_result($stmtContactos);

  $contactos = [];
  while ($fila = mysqli_fetch_assoc($resultadoContactos)) {
    $contactos[] = [
      'id_contacto' => (int) $fila['id_contacto'],
      'nombres' => $fila['nombres'],
      'apellidos' => $fila['apellidos'],
      'parentesco' => $fila['parentesco'],
      'telefono' => $fila['telefono'],
      'email' => $fila['email'],
      'direccion' => $fila['direccion'],
      'foto_url' => $fila['foto_url'],
      'identificacion_referencia' => $fila['identificacion_referencia'],
      'es_contacto_emergencia' => (bool) $fila['es_contacto_emergencia'],
      'autorizado_recoger' => (bool) $fila['autorizado_recoger'],
      'prioridad_emergencia' => $fila['prioridad_emergencia'] !== null
        ? (int) $fila['prioridad_emergencia']
        : null,
      'observaciones' => $fila['observaciones']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'tutor' => respuestaTutor($tutor),
    'nino' => array_merge(respuestaHijoResumen($nino), [
      'expediente_medico' => [
        'tipo_sangre' => $expediente['tipo_sangre'] ?? null,
        'alergias' => $expediente['alergias'] ?? null,
        'padecimientos' => $expediente['padecimientos'] ?? null,
        'medicamentos_habituales' => $expediente['medicamentos_habituales'] ?? null,
        'restricciones_alimentarias' => $expediente['restricciones_alimentarias'] ?? null,
        'medico_nombre' => $expediente['medico_nombre'] ?? null,
        'medico_telefono' => $expediente['medico_telefono'] ?? null,
        'institucion_medica' => $expediente['institucion_medica'] ?? null,
        'numero_seguro' => $expediente['numero_seguro'] ?? null,
        'indicaciones_emergencia' => $expediente['indicaciones_emergencia'] ?? null,
        'observaciones' => $expediente['observaciones'] ?? null
      ],
      'tutores' => $tutores,
      'contactos_autorizados' => $contactos
    ])
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  responderPadre(500, [
    'status' => 'error',
    'mensaje' => 'No fue posible consultar el perfil del hijo'
  ]);
}
