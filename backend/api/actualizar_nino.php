<?php
require_once __DIR__ . '/../cors.php';
configurarCors('PUT');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'mensaje' => 'Metodo no permitido'
  ]);
  exit();
}

require_once __DIR__ . '/../conexion.php';

function textoOpcionalActualizacion($valor): ?string
{
  if (!is_string($valor)) {
    return null;
  }

  $texto = trim($valor);
  return $texto === '' ? null : $texto;
}

function fechaValidaActualizacion(string $fecha): bool
{
  $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
  return $objetoFecha && $objetoFecha->format('Y-m-d') === $fecha;
}

function passwordTemporalActualizacion(): string
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

$idNino = isset($datos['id_nino']) ? (int) $datos['id_nino'] : 0;
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
$fechaIngreso = textoOpcionalActualizacion($nino['fecha_ingreso'] ?? null);
$generosValidos = ['FEMENINO', 'MASCULINO', 'OTRO', 'NO_ESPECIFICADO'];

if (
  $idNino <= 0 ||
  $idGuarderia <= 0 ||
  $idEmpleado <= 0 ||
  $nombresNino === '' ||
  $apellidosNino === '' ||
  !fechaValidaActualizacion($fechaNacimiento) ||
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

if ($fechaIngreso !== null && !fechaValidaActualizacion($fechaIngreso)) {
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

$emailsRecibidos = [];
foreach ($tutores as $indice => $tutor) {
  $email = strtolower(trim($tutor['email'] ?? ''));
  if (
    trim($tutor['nombres'] ?? '') === '' ||
    trim($tutor['apellidos'] ?? '') === '' ||
    trim($tutor['telefono'] ?? '') === '' ||
    trim($tutor['parentesco'] ?? '') === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
  ) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'Completa correctamente los datos del tutor ' . ($indice + 1)
    ]);
    exit();
  }

  if (in_array($email, $emailsRecibidos, true)) {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'mensaje' => 'No puedes registrar dos veces al mismo tutor'
    ]);
    exit();
  }

  $emailsRecibidos[] = $email;
}

foreach ($contactos as $indice => $contacto) {
  $emailContacto = textoOpcionalActualizacion($contacto['email'] ?? null);
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
    throw new DomainException('Solo un administrador puede editar ninos');
  }

  $stmtNinoActual = mysqli_prepare(
    $conexion,
    "SELECT id_nino
     FROM ninos
     WHERE id_nino = ?
       AND id_guarderia = ?
       AND eliminado_en IS NULL
     LIMIT 1
     FOR UPDATE"
  );
  mysqli_stmt_bind_param($stmtNinoActual, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmtNinoActual);
  if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtNinoActual))) {
    throw new DomainException('El nino no pertenece a la guarderia');
  }

  $idsContactosOriginales = [];
  $stmtContactosOriginales = mysqli_prepare(
    $conexion,
    "SELECT id_contacto FROM nino_contacto WHERE id_nino = ?"
  );
  mysqli_stmt_bind_param($stmtContactosOriginales, 'i', $idNino);
  mysqli_stmt_execute($stmtContactosOriginales);
  $resultadoContactosOriginales = mysqli_stmt_get_result($stmtContactosOriginales);
  while ($filaContacto = mysqli_fetch_assoc($resultadoContactosOriginales)) {
    $idsContactosOriginales[] = (int) $filaContacto['id_contacto'];
  }

  $stmtActualizarNino = mysqli_prepare(
    $conexion,
    "UPDATE ninos
     SET nombres = ?,
         apellidos = ?,
         fecha_nacimiento = ?,
         genero = ?,
         fecha_ingreso = ?
     WHERE id_nino = ? AND id_guarderia = ?"
  );
  mysqli_stmt_bind_param(
    $stmtActualizarNino,
    'sssssii',
    $nombresNino,
    $apellidosNino,
    $fechaNacimiento,
    $genero,
    $fechaIngreso,
    $idNino,
    $idGuarderia
  );
  mysqli_stmt_execute($stmtActualizarNino);

  $tipoSangre = textoOpcionalActualizacion($expediente['tipo_sangre'] ?? null);
  $alergias = textoOpcionalActualizacion($expediente['alergias'] ?? null);
  $padecimientos = textoOpcionalActualizacion($expediente['padecimientos'] ?? null);
  $medicamentos = textoOpcionalActualizacion($expediente['medicamentos_habituales'] ?? null);
  $restricciones = textoOpcionalActualizacion($expediente['restricciones_alimentarias'] ?? null);
  $medicoNombre = textoOpcionalActualizacion($expediente['medico_nombre'] ?? null);
  $medicoTelefono = textoOpcionalActualizacion($expediente['medico_telefono'] ?? null);
  $institucionMedica = textoOpcionalActualizacion($expediente['institucion_medica'] ?? null);
  $numeroSeguro = textoOpcionalActualizacion($expediente['numero_seguro'] ?? null);
  $indicaciones = textoOpcionalActualizacion($expediente['indicaciones_emergencia'] ?? null);
  $observaciones = textoOpcionalActualizacion($expediente['observaciones'] ?? null);

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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      tipo_sangre = VALUES(tipo_sangre),
      alergias = VALUES(alergias),
      padecimientos = VALUES(padecimientos),
      medicamentos_habituales = VALUES(medicamentos_habituales),
      restricciones_alimentarias = VALUES(restricciones_alimentarias),
      medico_nombre = VALUES(medico_nombre),
      medico_telefono = VALUES(medico_telefono),
      institucion_medica = VALUES(institucion_medica),
      numero_seguro = VALUES(numero_seguro),
      indicaciones_emergencia = VALUES(indicaciones_emergencia),
      observaciones = VALUES(observaciones)"
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
  $idsTutores = [];
  $tutoresResueltos = [];
  $credencialesNuevas = [];

  foreach ($tutores as $tutor) {
    $idTutorSolicitado = isset($tutor['id_tutor']) ? (int) $tutor['id_tutor'] : 0;
    $nombresTutor = trim($tutor['nombres']);
    $apellidosTutor = trim($tutor['apellidos']);
    $telefono = trim($tutor['telefono']);
    $telefonoAlterno = textoOpcionalActualizacion($tutor['telefono_alterno'] ?? null);
    $direccion = textoOpcionalActualizacion($tutor['direccion'] ?? null);
    $email = strtolower(trim($tutor['email']));
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
        "SELECT t.id_tutor, t.id_usuario
         FROM nino_tutor nt
         INNER JOIN tutores t ON t.id_tutor = nt.id_tutor
         INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
         WHERE nt.id_nino = ?
           AND t.id_tutor = ?
           AND u.id_guarderia = ?
           AND t.eliminado_en IS NULL
           AND u.eliminado_en IS NULL
         LIMIT 1"
      );
      mysqli_stmt_bind_param(
        $stmtTutorExistente,
        'iii',
        $idNino,
        $idTutorSolicitado,
        $idGuarderia
      );
      mysqli_stmt_execute($stmtTutorExistente);
      $tutorExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTutorExistente));
      if (!$tutorExistente) {
        throw new DomainException('Uno de los tutores no pertenece al nino');
      }

      $idTutor = (int) $tutorExistente['id_tutor'];
      $idUsuarioTutor = (int) $tutorExistente['id_usuario'];

      $stmtEmailOcupado = mysqli_prepare(
        $conexion,
        "SELECT id_usuario
         FROM usuarios
         WHERE email = ?
           AND id_usuario <> ?
           AND eliminado_en IS NULL
         LIMIT 1"
      );
      mysqli_stmt_bind_param($stmtEmailOcupado, 'si', $email, $idUsuarioTutor);
      mysqli_stmt_execute($stmtEmailOcupado);
      if (mysqli_fetch_row(mysqli_stmt_get_result($stmtEmailOcupado))) {
        throw new DomainException('El correo ' . $email . ' ya esta registrado');
      }

      $stmtActualizarUsuario = mysqli_prepare(
        $conexion,
        "UPDATE usuarios SET email = ? WHERE id_usuario = ?"
      );
      mysqli_stmt_bind_param($stmtActualizarUsuario, 'si', $email, $idUsuarioTutor);
      mysqli_stmt_execute($stmtActualizarUsuario);

      $stmtActualizarTutor = mysqli_prepare(
        $conexion,
        "UPDATE tutores
         SET nombres = ?, apellidos = ?, telefono = ?, telefono_alterno = ?, direccion = ?
         WHERE id_tutor = ?"
      );
      mysqli_stmt_bind_param(
        $stmtActualizarTutor,
        'sssssi',
        $nombresTutor,
        $apellidosTutor,
        $telefono,
        $telefonoAlterno,
        $direccion,
        $idTutor
      );
      mysqli_stmt_execute($stmtActualizarTutor);
    } else {
      $stmtUsuario = mysqli_prepare(
        $conexion,
        "SELECT
          u.id_usuario,
          u.id_guarderia,
          u.estado,
          r.nombre AS rol,
          t.id_tutor
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

        $stmtActualizarTutor = mysqli_prepare(
          $conexion,
          "UPDATE tutores
           SET nombres = ?, apellidos = ?, telefono = ?, telefono_alterno = ?, direccion = ?
           WHERE id_tutor = ?"
        );
        mysqli_stmt_bind_param(
          $stmtActualizarTutor,
          'sssssi',
          $nombresTutor,
          $apellidosTutor,
          $telefono,
          $telefonoAlterno,
          $direccion,
          $idTutor
        );
        mysqli_stmt_execute($stmtActualizarTutor);
      } else {
        $password = passwordTemporalActualizacion();
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
        mysqli_stmt_bind_param(
          $stmtNuevoUsuario,
          'iiss',
          $idGuarderia,
          $idRolTutor,
          $email,
          $passwordHash
        );
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

    if (in_array($idTutor, $idsTutores, true)) {
      throw new DomainException('No puedes vincular dos veces al mismo tutor');
    }

    $idsTutores[] = $idTutor;
    $tutoresResueltos[] = [
      'id_tutor' => $idTutor,
      'parentesco' => $parentesco,
      'recibe_notificaciones' => $recibeNotificaciones,
      'autorizado_recoger' => $autorizadoRecoger
    ];
  }

  $stmtEliminarVinculosTutor = mysqli_prepare(
    $conexion,
    "DELETE FROM nino_tutor WHERE id_nino = ?"
  );
  mysqli_stmt_bind_param($stmtEliminarVinculosTutor, 'i', $idNino);
  mysqli_stmt_execute($stmtEliminarVinculosTutor);

  foreach ($tutoresResueltos as $indice => $tutorResuelto) {
    $idTutor = $tutorResuelto['id_tutor'];
    $parentesco = $tutorResuelto['parentesco'];
    $esPrincipal = $indice === 0 ? 1 : 0;
    $recibeNotificaciones = $tutorResuelto['recibe_notificaciones'];
    $autorizadoRecoger = $tutorResuelto['autorizado_recoger'];
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
  }

  $contactosResueltos = [];
  foreach ($contactos as $contacto) {
    $idContactoSolicitado = isset($contacto['id_contacto'])
      ? (int) $contacto['id_contacto']
      : 0;
    $nombresContacto = trim($contacto['nombres']);
    $apellidosContacto = trim($contacto['apellidos']);
    $parentescoContacto = textoOpcionalActualizacion($contacto['parentesco'] ?? null);
    $telefonoContacto = trim($contacto['telefono']);
    $emailContacto = textoOpcionalActualizacion($contacto['email'] ?? null);
    $direccionContacto = textoOpcionalActualizacion($contacto['direccion'] ?? null);
    $identificacion = textoOpcionalActualizacion(
      $contacto['identificacion_referencia'] ?? null
    );
    $autorizadoRecogerContacto = !empty($contacto['autorizado_recoger']) ? 1 : 0;
    $observacionesContacto = textoOpcionalActualizacion(
      $contacto['observaciones'] ?? null
    );

    if ($idContactoSolicitado > 0) {
      if (!in_array($idContactoSolicitado, $idsContactosOriginales, true)) {
        throw new DomainException('Uno de los contactos no pertenece al nino');
      }

      $stmtActualizarContacto = mysqli_prepare(
        $conexion,
        "UPDATE contactos_autorizados
         SET nombres = ?,
             apellidos = ?,
             parentesco = ?,
             telefono = ?,
             email = ?,
             direccion = ?,
             identificacion_referencia = ?
         WHERE id_contacto = ?
           AND id_guarderia = ?
           AND eliminado_en IS NULL"
      );
      mysqli_stmt_bind_param(
        $stmtActualizarContacto,
        'sssssssii',
        $nombresContacto,
        $apellidosContacto,
        $parentescoContacto,
        $telefonoContacto,
        $emailContacto,
        $direccionContacto,
        $identificacion,
        $idContactoSolicitado,
        $idGuarderia
      );
      mysqli_stmt_execute($stmtActualizarContacto);
      $idContacto = $idContactoSolicitado;
    } else {
      $stmtNuevoContacto = mysqli_prepare(
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
        $stmtNuevoContacto,
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
      mysqli_stmt_execute($stmtNuevoContacto);
      $idContacto = (int) mysqli_insert_id($conexion);
    }

    $contactosResueltos[] = [
      'id_contacto' => $idContacto,
      'autorizado_recoger' => $autorizadoRecogerContacto,
      'observaciones' => $observacionesContacto
    ];
  }

  $stmtEliminarVinculosContacto = mysqli_prepare(
    $conexion,
    "DELETE FROM nino_contacto WHERE id_nino = ?"
  );
  mysqli_stmt_bind_param($stmtEliminarVinculosContacto, 'i', $idNino);
  mysqli_stmt_execute($stmtEliminarVinculosContacto);

  $idsContactosSeleccionados = [];
  foreach ($contactosResueltos as $indice => $contactoResuelto) {
    $idContacto = $contactoResuelto['id_contacto'];
    $idsContactosSeleccionados[] = $idContacto;
    $autorizadoRecoger = $contactoResuelto['autorizado_recoger'];
    $observaciones = $contactoResuelto['observaciones'];
    $prioridad = $indice + 1;
    $esEmergencia = 1;
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
      $esEmergencia,
      $autorizadoRecoger,
      $prioridad,
      $observaciones
    );
    mysqli_stmt_execute($stmtVinculoContacto);
  }

  foreach ($idsContactosOriginales as $idContactoOriginal) {
    if (!in_array($idContactoOriginal, $idsContactosSeleccionados, true)) {
      $stmtArchivarContacto = mysqli_prepare(
        $conexion,
        "UPDATE contactos_autorizados c
         SET eliminado_en = CURRENT_TIMESTAMP
         WHERE c.id_contacto = ?
           AND NOT EXISTS (
             SELECT 1 FROM nino_contacto nc WHERE nc.id_contacto = c.id_contacto
           )"
      );
      mysqli_stmt_bind_param($stmtArchivarContacto, 'i', $idContactoOriginal);
      mysqli_stmt_execute($stmtArchivarContacto);
    }
  }

  mysqli_commit($conexion);
  echo json_encode([
    'status' => 'success',
    'mensaje' => 'El expediente fue actualizado correctamente',
    'id_nino' => $idNino,
    'credenciales_nuevas' => $credencialesNuevas
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
    'mensaje' => 'No fue posible actualizar el expediente'
  ]);
}
