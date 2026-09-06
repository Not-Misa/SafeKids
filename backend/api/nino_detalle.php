<?php
require_once __DIR__ . '/../cors.php';
configurarCors('GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Metodo no permitido'
  ]);
  exit();
}

require_once __DIR__ . '/../conexion.php';

$idNino = isset($_GET['id_nino']) ? (int) $_GET['id_nino'] : 0;
$idGuarderia = isset($_GET['id_guarderia']) ? (int) $_GET['id_guarderia'] : 0;

if ($idNino <= 0 || $idGuarderia <= 0) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Los datos del perfil no son validos'
  ]);
  exit();
}

try {
  $sqlNino = "
    SELECT
      n.id_nino,
      n.nombres,
      n.apellidos,
      n.fecha_nacimiento,
      TIMESTAMPDIFF(YEAR, n.fecha_nacimiento, CURDATE()) AS edad,
      n.genero,
      n.foto_url,
      n.codigo_qr,
      n.estado,
      n.fecha_ingreso,
      em.tipo_sangre,
      em.alergias,
      em.padecimientos,
      em.medicamentos_habituales,
      em.restricciones_alimentarias,
      em.medico_nombre,
      em.medico_telefono,
      em.institucion_medica,
      em.numero_seguro,
      em.indicaciones_emergencia,
      em.observaciones
    FROM ninos n
    LEFT JOIN expedientes_medicos em ON em.id_nino = n.id_nino
    WHERE n.id_nino = ?
      AND n.id_guarderia = ?
      AND n.eliminado_en IS NULL
    LIMIT 1
  ";

  $stmtNino = mysqli_prepare($conexion, $sqlNino);
  mysqli_stmt_bind_param($stmtNino, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtNino);
  $resultadoNino = mysqli_stmt_get_result($stmtNino);
  $nino = mysqli_fetch_assoc($resultadoNino);

  if (!$nino) {
    http_response_code(404);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'El perfil no existe o no pertenece a esta guarderia'
    ]);
    exit();
  }

  $sqlTutores = "
    SELECT
      t.id_tutor,
      t.nombres,
      t.apellidos,
      t.telefono,
      t.telefono_alterno,
      t.direccion,
      t.foto_url,
      u.email,
      u.requiere_cambio_password,
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
      AND t.eliminado_en IS NULL
      AND u.eliminado_en IS NULL
    ORDER BY nt.es_principal DESC, nt.prioridad_contacto ASC, t.apellidos ASC
  ";

  $stmtTutores = mysqli_prepare($conexion, $sqlTutores);
  mysqli_stmt_bind_param($stmtTutores, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtTutores);
  $resultadoTutores = mysqli_stmt_get_result($stmtTutores);

  $tutores = [];
  while ($tutor = mysqli_fetch_assoc($resultadoTutores)) {
    $tutores[] = [
      'id_tutor' => (int) $tutor['id_tutor'],
      'nombres' => $tutor['nombres'],
      'apellidos' => $tutor['apellidos'],
      'telefono' => $tutor['telefono'],
      'telefono_alterno' => $tutor['telefono_alterno'],
      'direccion' => $tutor['direccion'],
      'foto_url' => $tutor['foto_url'],
      'email' => $tutor['email'],
      'requiere_cambio_password' => (bool) $tutor['requiere_cambio_password'],
      'parentesco' => $tutor['parentesco'],
      'es_principal' => (bool) $tutor['es_principal'],
      'recibe_notificaciones' => (bool) $tutor['recibe_notificaciones'],
      'autorizado_recoger' => (bool) $tutor['autorizado_recoger'],
      'prioridad_contacto' => $tutor['prioridad_contacto'] !== null
        ? (int) $tutor['prioridad_contacto']
        : null
    ];
  }

  $sqlContactos = "
    SELECT
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
    ORDER BY nc.prioridad_emergencia ASC, c.apellidos ASC
  ";

  $stmtContactos = mysqli_prepare($conexion, $sqlContactos);
  mysqli_stmt_bind_param($stmtContactos, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtContactos);
  $resultadoContactos = mysqli_stmt_get_result($stmtContactos);

  $contactos = [];
  while ($contacto = mysqli_fetch_assoc($resultadoContactos)) {
    $contactos[] = [
      'id_contacto' => (int) $contacto['id_contacto'],
      'nombres' => $contacto['nombres'],
      'apellidos' => $contacto['apellidos'],
      'parentesco' => $contacto['parentesco'],
      'telefono' => $contacto['telefono'],
      'email' => $contacto['email'],
      'direccion' => $contacto['direccion'],
      'foto_url' => $contacto['foto_url'],
      'identificacion_referencia' => $contacto['identificacion_referencia'],
      'es_contacto_emergencia' => (bool) $contacto['es_contacto_emergencia'],
      'autorizado_recoger' => (bool) $contacto['autorizado_recoger'],
      'prioridad_emergencia' => $contacto['prioridad_emergencia'] !== null
        ? (int) $contacto['prioridad_emergencia']
        : null,
      'observaciones' => $contacto['observaciones']
    ];
  }

  echo json_encode([
    'status' => 'success',
    'nino' => [
      'id_nino' => (int) $nino['id_nino'],
      'nombres' => $nino['nombres'],
      'apellidos' => $nino['apellidos'],
      'fecha_nacimiento' => $nino['fecha_nacimiento'],
      'edad' => (int) $nino['edad'],
      'genero' => $nino['genero'],
      'foto_url' => $nino['foto_url'],
      'codigo_qr' => $nino['codigo_qr'],
      'estado' => $nino['estado'],
      'fecha_ingreso' => $nino['fecha_ingreso'],
      'expediente_medico' => [
        'tipo_sangre' => $nino['tipo_sangre'],
        'alergias' => $nino['alergias'],
        'padecimientos' => $nino['padecimientos'],
        'medicamentos_habituales' => $nino['medicamentos_habituales'],
        'restricciones_alimentarias' => $nino['restricciones_alimentarias'],
        'medico_nombre' => $nino['medico_nombre'],
        'medico_telefono' => $nino['medico_telefono'],
        'institucion_medica' => $nino['institucion_medica'],
        'numero_seguro' => $nino['numero_seguro'],
        'indicaciones_emergencia' => $nino['indicaciones_emergencia'],
        'observaciones' => $nino['observaciones']
      ],
      'tutores' => $tutores,
      'contactos_autorizados' => $contactos
    ]
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible consultar el perfil del nino'
  ]);
}
