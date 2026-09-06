<?php

function responderPadre(int $codigo, array $contenido): void
{
  http_response_code($codigo);
  echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
  exit();
}

function textoOpcionalPadre($valor): ?string
{
  if (!is_string($valor)) {
    return null;
  }

  $texto = trim($valor);
  return $texto === '' ? null : $texto;
}

function fechaSimplePadreValida(string $fecha): bool
{
  $objeto = DateTime::createFromFormat('Y-m-d', $fecha);
  return $objeto && $objeto->format('Y-m-d') === $fecha;
}

function obtenerTutorActivo(
  mysqli $conexion,
  int $idUsuario,
  int $idGuarderia
): ?array {
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      u.id_usuario,
      u.id_guarderia,
      u.email,
      u.requiere_cambio_password,
      t.id_tutor,
      t.nombres,
      t.apellidos,
      t.telefono,
      t.telefono_alterno,
      t.direccion,
      t.foto_url
    FROM usuarios u
    INNER JOIN roles r ON r.id_rol = u.id_rol
    INNER JOIN tutores t ON t.id_usuario = u.id_usuario
    WHERE u.id_usuario = ?
      AND u.id_guarderia = ?
      AND r.nombre = 'TUTOR'
      AND u.estado = 'ACTIVO'
      AND u.eliminado_en IS NULL
      AND t.eliminado_en IS NULL
    LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 'ii', $idUsuario, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $tutor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $tutor ?: null;
}

function obtenerHijoDeTutor(
  mysqli $conexion,
  int $idNino,
  int $idTutor,
  int $idGuarderia
): ?array {
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT
      n.id_nino,
      n.id_guarderia,
      n.nombres,
      n.apellidos,
      n.fecha_nacimiento,
      TIMESTAMPDIFF(YEAR, n.fecha_nacimiento, CURDATE()) AS edad,
      n.genero,
      n.foto_url,
      n.codigo_qr,
      n.estado,
      n.fecha_ingreso,
      nt.parentesco,
      nt.es_principal,
      nt.recibe_notificaciones,
      nt.autorizado_recoger,
      nt.prioridad_contacto
    FROM nino_tutor nt
    INNER JOIN ninos n ON n.id_nino = nt.id_nino
    WHERE nt.id_nino = ?
      AND nt.id_tutor = ?
      AND n.id_guarderia = ?
      AND n.eliminado_en IS NULL
    LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 'iii', $idNino, $idTutor, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $nino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $nino ?: null;
}

function validarFiltrosFechaPadre(string $desde, string $hasta): void
{
  if (!fechaSimplePadreValida($desde) || !fechaSimplePadreValida($hasta) || $desde > $hasta) {
    responderPadre(400, [
      'status' => 'error',
      'mensaje' => 'El rango de fechas no es valido'
    ]);
  }

  $inicio = new DateTime($desde);
  $fin = new DateTime($hasta);
  if ($inicio->diff($fin)->days > 92) {
    responderPadre(422, [
      'status' => 'error',
      'mensaje' => 'El rango de consulta no puede superar 93 dias'
    ]);
  }
}

function respuestaTutor(array $tutor): array
{
  return [
    'id_usuario' => (int) $tutor['id_usuario'],
    'id_tutor' => (int) $tutor['id_tutor'],
    'id_guarderia' => (int) $tutor['id_guarderia'],
    'email' => $tutor['email'],
    'nombres' => $tutor['nombres'],
    'apellidos' => $tutor['apellidos'],
    'telefono' => $tutor['telefono'],
    'telefono_alterno' => $tutor['telefono_alterno'],
    'direccion' => $tutor['direccion'],
    'foto_url' => $tutor['foto_url'],
    'requiere_cambio_password' => (bool) $tutor['requiere_cambio_password']
  ];
}

function respuestaHijoResumen(array $nino): array
{
  return [
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
    'relacion' => [
      'parentesco' => $nino['parentesco'],
      'es_principal' => (bool) $nino['es_principal'],
      'recibe_notificaciones' => (bool) $nino['recibe_notificaciones'],
      'autorizado_recoger' => (bool) $nino['autorizado_recoger'],
      'prioridad_contacto' => $nino['prioridad_contacto'] !== null
        ? (int) $nino['prioridad_contacto']
        : null
    ]
  ];
}
