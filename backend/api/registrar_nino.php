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

function textoOpcional($valor): ?string
{
  if (!is_string($valor)) {
    return null;
  }

  $texto = trim($valor);
  return $texto === '' ? null : $texto;
}

function fechaValida(string $fecha): bool
{
  $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
  return $objetoFecha && $objetoFecha->format('Y-m-d') === $fecha;
}

function passwordTemporal(): string
{
  return generarPasswordTemporalSegura();
}

$datos = json_decode(file_get_contents('php://input'), true);

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
$nino = is_array($datos['nino'] ?? null) ? $datos['nino'] : [];
$expediente = is_array($datos['expediente_medico'] ?? null)
  ? $datos['expediente_medico']
  : [];
$tutores = is_array($datos['tutores'] ?? null) ? $datos['tutores'] : [];
$contactos = is_array($datos['contactos_autorizados'] ?? null)
  ? $datos['contactos_autorizados']
  : [];

$nombresNino = trim($nino['nombres'] ?? '');
$apellidosNino = trim($nino['apellidos'] ?? '');
$fechaNacimiento = trim($nino['fecha_nacimiento'] ?? '');
$genero = strtoupper(trim($nino['genero'] ?? 'NO_ESPECIFICADO'));
$fechaIngreso = textoOpcional($nino['fecha_ingreso'] ?? null);
$fotoUrl = null;

$generosValidos = ['FEMENINO', 'MASCULINO', 'OTRO', 'NO_ESPECIFICADO'];

if (
  $idGuarderia <= 0 ||
  $idEmpleado <= 0 ||
  $nombresNino === '' ||
  $apellidosNino === '' ||
  !fechaValida($fechaNacimiento) ||
  !in_array($genero, $generosValidos, true)
) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Completa correctamente los datos obligatorios del nino'
  ]);
  exit();
}

if ($fechaNacimiento > date('Y-m-d')) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'La fecha de nacimiento no puede ser futura'
  ]);
  exit();
}

if ($fechaIngreso !== null && !fechaValida($fechaIngreso)) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'La fecha de ingreso no es valida'
  ]);
  exit();
}

if (count($tutores) < 1 || count($tutores) > 2) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Registra uno o dos tutores'
  ]);
  exit();
}

if (count($contactos) > 2) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Solo se permiten hasta dos contactos autorizados'
  ]);
  exit();
}

$referenciasTutores = [];
foreach ($tutores as $indice => $tutor) {
  $idTutorExistente = isset($tutor['id_tutor']) ? (int) $tutor['id_tutor'] : 0;
  $email = strtolower(trim($tutor['email'] ?? ''));
  $parentesco = trim($tutor['parentesco'] ?? '');

  if ($parentesco === '') {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Indica el parentesco del tutor ' . ($indice + 1)
    ]);
    exit();
  }

  if ($idTutorExistente > 0) {
    $referencia = 'id:' . $idTutorExistente;
  } else {
    if (
      trim($tutor['nombres'] ?? '') === '' ||
      trim($tutor['apellidos'] ?? '') === '' ||
      trim($tutor['telefono'] ?? '') === '' ||
      !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
      http_response_code(400);
      echo json_encode([
        'status' => 'error',
        'mensaje' => 'Completa correctamente los datos del tutor ' . ($indice + 1)
      ]);
      exit();
    }
    $referencia = 'email:' . $email;
  }

  if (in_array($referencia, $referenciasTutores, true)) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'No puedes registrar dos veces al mismo tutor'
    ]);
    exit();
  }

  $referenciasTutores[] = $referencia;
}

foreach ($contactos as $indice => $contacto) {
  $emailContacto = textoOpcional($contacto['email'] ?? null);
  if (
    trim($contacto['nombres'] ?? '') === '' ||
    trim($contacto['apellidos'] ?? '') === '' ||
    trim($contacto['telefono'] ?? '') === ''
  ) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Completa los datos obligatorios del contacto ' . ($indice + 1)
    ]);
    exit();
  }

  if ($emailContacto !== null && !filter_var($emailContacto, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'El correo del contacto ' . ($indice + 1) . ' no es valido'
    ]);
    exit();
  }
}

try {
  mysqli_begin_transaction($conexion);

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
    throw new DomainException('Solo un administrador puede registrar ninos');
  }

  $stmtGuarderia = mysqli_prepare(
    $conexion,
    "SELECT id_guarderia FROM guarderias
     WHERE id_guarderia = ? AND estado = 'ACTIVA' AND eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmtGuarderia, 'i', $idGuarderia);
  mysqli_stmt_execute($stmtGuarderia);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtGuarderia))) {
    throw new DomainException('La guarderia no esta activa');
  }

  $stmtNino = mysqli_prepare(
    $conexion,
    "INSERT INTO ninos (
      id_guarderia,
      nombres,
      apellidos,
      fecha_nacimiento,
      genero,
      foto_url,
      codigo_qr,
      estado,
      fecha_ingreso
    ) VALUES (?, ?, ?, ?, ?, ?, UUID(), 'ACTIVO', ?)"
  );
  mysqli_stmt_bind_param(
    $stmtNino,
    'issssss',
    $idGuarderia,
    $nombresNino,
    $apellidosNino,
    $fechaNacimiento,
    $genero,
    $fotoUrl,
    $fechaIngreso
  );
  mysqli_stmt_execute($stmtNino);
  $idNino = (int) mysqli_insert_id($conexion);

  $tipoSangre = textoOpcional($expediente['tipo_sangre'] ?? null);
  $alergias = textoOpcional($expediente['alergias'] ?? null);
  $padecimientos = textoOpcional($expediente['padecimientos'] ?? null);
  $medicamentos = textoOpcional($expediente['medicamentos_habituales'] ?? null);
  $restricciones = textoOpcional($expediente['restricciones_alimentarias'] ?? null);
  $medicoNombre = textoOpcional($expediente['medico_nombre'] ?? null);
  $medicoTelefono = textoOpcional($expediente['medico_telefono'] ?? null);
  $institucionMedica = textoOpcional($expediente['institucion_medica'] ?? null);
  $numeroSeguro = textoOpcional($expediente['numero_seguro'] ?? null);
  $indicaciones = textoOpcional($expediente['indicaciones_emergencia'] ?? null);
  $observaciones = textoOpcional($expediente['observaciones'] ?? null);

  $stmtExpediente = mysqli_prepare(
    $conexion,
    "INSERT INTO expedientes_medicos (
      id_nino,
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );
  mysqli_stmt_bind_param(
    $stmtExpediente,
    'isssssssssss',
    $idNino,
    $tipoSangre,
    $alergias,
    $padecimientos,
    $medicamentos,
    $restricciones,
    $medicoNombre,
    $medicoTelefono,
    $institucionMedica,
    $numeroSeguro,
    $indicaciones,
    $observaciones
  );
  mysqli_stmt_execute($stmtExpediente);

  $stmtRol = mysqli_prepare(
    $conexion,
    "SELECT id_rol FROM roles WHERE nombre = 'TUTOR' LIMIT 1"
  );
  mysqli_stmt_execute($stmtRol);
  $rolTutor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtRol));
  if (!$rolTutor) {
    throw new DomainException('No existe el rol de tutor');
  }
  $idRolTutor = (int) $rolTutor['id_rol'];

  $credencialesNuevas = [];
  $tutoresVinculados = [];
  $idsTutoresVinculados = [];

  foreach ($tutores as $indice => $tutor) {
    $idTutorSolicitado = isset($tutor['id_tutor']) ? (int) $tutor['id_tutor'] : 0;
    $nombresTutor = trim($tutor['nombres'] ?? '');
    $apellidosTutor = trim($tutor['apellidos'] ?? '');
    $telefono = trim($tutor['telefono'] ?? '');
    $telefonoAlterno = textoOpcional($tutor['telefono_alterno'] ?? null);
    $direccion = textoOpcional($tutor['direccion'] ?? null);
    $email = strtolower(trim($tutor['email'] ?? ''));
    $parentesco = trim($tutor['parentesco']);
    $recibeNotificaciones = array_key_exists('recibe_notificaciones', $tutor)
      ? (int) (bool) $tutor['recibe_notificaciones']
      : 1;
    $autorizadoRecoger = array_key_exists('autorizado_recoger', $tutor)
      ? (int) (bool) $tutor['autorizado_recoger']
      : 1;

    if ($idTutorSolicitado > 0) {
      $stmtTutorExistente = mysqli_prepare(
        $conexion,
        "SELECT
          t.id_tutor,
          t.nombres,
          t.apellidos,
          t.telefono,
          t.telefono_alterno,
          t.direccion,
          u.email
        FROM tutores t
        INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
        INNER JOIN roles r ON r.id_rol = u.id_rol
        WHERE t.id_tutor = ?
          AND u.id_guarderia = ?
          AND r.nombre = 'TUTOR'
          AND u.estado = 'ACTIVO'
          AND u.eliminado_en IS NULL
          AND t.eliminado_en IS NULL
        LIMIT 1"
      );
      mysqli_stmt_bind_param($stmtTutorExistente, 'ii', $idTutorSolicitado, $idGuarderia);
      mysqli_stmt_execute($stmtTutorExistente);
      $tutorExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTutorExistente));

      if (!$tutorExistente) {
        throw new DomainException(
          'El tutor seleccionado no esta disponible en esta guarderia'
        );
      }

      $idTutor = (int) $tutorExistente['id_tutor'];
      $nombresTutor = $tutorExistente['nombres'];
      $apellidosTutor = $tutorExistente['apellidos'];
      $telefono = $tutorExistente['telefono'];
      $telefonoAlterno = $tutorExistente['telefono_alterno'];
      $direccion = $tutorExistente['direccion'];
      $email = $tutorExistente['email'];
    } else {
      $stmtUsuario = mysqli_prepare(
        $conexion,
        "SELECT
          u.id_guarderia,
          u.estado,
          r.nombre AS rol,
          t.id_tutor,
          t.nombres,
          t.apellidos,
          t.telefono,
          t.telefono_alterno,
          t.direccion,
          u.email
        FROM usuarios u
        INNER JOIN roles r ON r.id_rol = u.id_rol
        LEFT JOIN tutores t ON t.id_usuario = u.id_usuario
        WHERE u.email = ? AND u.eliminado_en IS NULL
        LIMIT 1"
      );
      mysqli_stmt_bind_param($stmtUsuario, 's', $email);
      mysqli_stmt_execute($stmtUsuario);
      $usuarioExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

      if ($usuarioExistente) {
        if (
          (int) $usuarioExistente['id_guarderia'] !== $idGuarderia ||
          $usuarioExistente['rol'] !== 'TUTOR' ||
          $usuarioExistente['estado'] !== 'ACTIVO' ||
          $usuarioExistente['id_tutor'] === null
        ) {
          throw new DomainException(
            'El correo ' . $email . ' ya pertenece a una cuenta que no puede vincularse'
          );
        }

        $idTutor = (int) $usuarioExistente['id_tutor'];
        $nombresTutor = $usuarioExistente['nombres'];
        $apellidosTutor = $usuarioExistente['apellidos'];
        $telefono = $usuarioExistente['telefono'];
        $telefonoAlterno = $usuarioExistente['telefono_alterno'];
        $direccion = $usuarioExistente['direccion'];
        $email = $usuarioExistente['email'];
      } else {
        $password = passwordTemporal();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmtNuevoUsuario = mysqli_prepare(
          $conexion,
          "INSERT INTO usuarios (
            id_guarderia,
            id_rol,
            email,
            password_hash,
            requiere_cambio_password,
            estado
          ) VALUES (?, ?, ?, ?, TRUE, 'ACTIVO')"
        );
        mysqli_stmt_bind_param($stmtNuevoUsuario, 'iiss', $idGuarderia, $idRolTutor, $email, $passwordHash);
        mysqli_stmt_execute($stmtNuevoUsuario);
        $idUsuario = (int) mysqli_insert_id($conexion);

        $stmtNuevoTutor = mysqli_prepare(
          $conexion,
          "INSERT INTO tutores (
            id_usuario,
            nombres,
            apellidos,
            telefono,
            telefono_alterno,
            direccion
          ) VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
          $stmtNuevoTutor,
          'isssss',
          $idUsuario,
          $nombresTutor,
          $apellidosTutor,
          $telefono,
          $telefonoAlterno,
          $direccion
        );
        mysqli_stmt_execute($stmtNuevoTutor);
        $idTutor = (int) mysqli_insert_id($conexion);

        $credencialesNuevas[] = [
          'nombre' => $nombresTutor . ' ' . $apellidosTutor,
          'email' => $email,
          'password_temporal' => $password
        ];
      }
    }

    if (in_array($idTutor, $idsTutoresVinculados, true)) {
      throw new DomainException('No puedes vincular dos veces al mismo tutor');
    }
    $idsTutoresVinculados[] = $idTutor;

    $esPrincipal = $indice === 0 ? 1 : 0;
    $prioridad = $indice + 1;
    $stmtVinculoTutor = mysqli_prepare(
      $conexion,
      "INSERT INTO nino_tutor (
        id_nino,
        id_tutor,
        parentesco,
        es_principal,
        recibe_notificaciones,
        autorizado_recoger,
        prioridad_contacto
      ) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
      $stmtVinculoTutor,
      'iisiiii',
      $idNino,
      $idTutor,
      $parentesco,
      $esPrincipal,
      $recibeNotificaciones,
      $autorizadoRecoger,
      $prioridad
    );
    mysqli_stmt_execute($stmtVinculoTutor);

    $tutoresVinculados[] = [
      'id_tutor' => $idTutor,
      'nombres' => $nombresTutor,
      'apellidos' => $apellidosTutor,
      'email' => $email,
      'telefono' => $telefono,
      'telefono_alterno' => $telefonoAlterno,
      'direccion' => $direccion,
      'parentesco' => $parentesco,
      'recibe_notificaciones' => (bool) $recibeNotificaciones,
      'autorizado_recoger' => (bool) $autorizadoRecoger
    ];
  }

  foreach ($contactos as $indice => $contacto) {
    $nombresContacto = trim($contacto['nombres']);
    $apellidosContacto = trim($contacto['apellidos']);
    $parentescoContacto = textoOpcional($contacto['parentesco'] ?? null);
    $telefonoContacto = trim($contacto['telefono']);
    $emailContacto = textoOpcional($contacto['email'] ?? null);
    $direccionContacto = textoOpcional($contacto['direccion'] ?? null);
    $identificacion = textoOpcional($contacto['identificacion_referencia'] ?? null);
    $autorizadoRecogerContacto = !empty($contacto['autorizado_recoger']) ? 1 : 0;
    $observacionesContacto = textoOpcional($contacto['observaciones'] ?? null);
    $prioridadEmergencia = $indice + 1;
    $esContactoEmergencia = 1;

    $stmtContacto = mysqli_prepare(
      $conexion,
      "INSERT INTO contactos_autorizados (
        id_guarderia,
        nombres,
        apellidos,
        parentesco,
        telefono,
        email,
        direccion,
        identificacion_referencia
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
      $stmtContacto,
      'isssssss',
      $idGuarderia,
      $nombresContacto,
      $apellidosContacto,
      $parentescoContacto,
      $telefonoContacto,
      $emailContacto,
      $direccionContacto,
      $identificacion
    );
    mysqli_stmt_execute($stmtContacto);
    $idContacto = (int) mysqli_insert_id($conexion);

    $stmtVinculoContacto = mysqli_prepare(
      $conexion,
      "INSERT INTO nino_contacto (
        id_nino,
        id_contacto,
        es_contacto_emergencia,
        autorizado_recoger,
        prioridad_emergencia,
        observaciones
      ) VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
      $stmtVinculoContacto,
      'iiiiis',
      $idNino,
      $idContacto,
      $esContactoEmergencia,
      $autorizadoRecogerContacto,
      $prioridadEmergencia,
      $observacionesContacto
    );
    mysqli_stmt_execute($stmtVinculoContacto);
  }

  mysqli_commit($conexion);
  http_response_code(201);
  echo json_encode([
    'status' => 'success',
    'mensaje' => 'El nino fue registrado correctamente',
    'id_nino' => $idNino,
    'credenciales_nuevas' => $credencialesNuevas,
    'tutores_vinculados' => $tutoresVinculados
  ], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
  mysqli_rollback($conexion);
  http_response_code(422);
  echo json_encode([
    'status' => 'error',
    'mensaje' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
  mysqli_rollback($conexion);
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'No fue posible registrar al nino'
  ]);
}
