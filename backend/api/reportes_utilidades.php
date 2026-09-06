<?php

function responderReporte(int $codigo, array $contenido): void
{
  http_response_code($codigo);
  echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
  exit();
}

function textoOpcionalReporte($valor): ?string
{
  if (!is_string($valor)) {
    return null;
  }

  $texto = trim($valor);
  return $texto === '' ? null : $texto;
}

function longitudReporte(string $texto): int
{
  return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
}

function obtenerPersonalActivo(
  mysqli $conexion,
  int $idEmpleado,
  int $idGuarderia
): ?array {
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT e.id_empleado, e.id_usuario, r.nombre AS rol
     FROM empleados e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
     INNER JOIN roles r ON r.id_rol = u.id_rol
     WHERE e.id_empleado = ?
       AND u.id_guarderia = ?
       AND r.nombre IN ('ADMIN', 'EMPLEADO')
       AND u.estado = 'ACTIVO'
       AND e.eliminado_en IS NULL
       AND u.eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $personal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $personal ?: null;
}

function obtenerNinoReporte(
  mysqli $conexion,
  int $idNino,
  int $idGuarderia
): ?array {
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT id_nino, nombres, apellidos, foto_url, estado
     FROM ninos
     WHERE id_nino = ?
       AND id_guarderia = ?
       AND eliminado_en IS NULL
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, 'ii', $idNino, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $nino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $nino ?: null;
}

function fechaHoraReporte(string $valor): string
{
  $formatos = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];
  $fecha = false;
  foreach ($formatos as $formato) {
    $fecha = DateTime::createFromFormat($formato, $valor);
    if ($fecha && $fecha->format($formato) === $valor) {
      break;
    }
    $fecha = false;
  }

  if (!$fecha) {
    throw new DomainException('La fecha y hora del reporte no son validas');
  }

  $ahora = new DateTime();
  $limiteFuturo = (clone $ahora)->modify('+5 minutes');
  $limiteAnterior = (clone $ahora)->modify('-90 days');
  if ($fecha > $limiteFuturo || $fecha < $limiteAnterior) {
    throw new DomainException(
      'La fecha del reporte debe estar dentro de los ultimos 90 dias y no ser futura'
    );
  }

  return $fecha->format('Y-m-d H:i:s');
}

function detalleReporteValidado($detalle): ?string
{
  if ($detalle === null || $detalle === []) {
    return null;
  }
  if (!is_array($detalle)) {
    throw new DomainException('El detalle adicional del reporte no es valido');
  }

  $clavesPermitidas = [
    'alimento',
    'cantidad_consumida',
    'tipo_cambio',
    'resultado',
    'medicamento',
    'dosis',
    'autorizado_por',
    'estado_animo',
    'actividad',
    'participacion',
    'zona_cuerpo',
    'accion_realizada'
  ];
  $limpio = [];
  foreach ($detalle as $clave => $valor) {
    if (!in_array($clave, $clavesPermitidas, true) || !is_string($valor)) {
      continue;
    }
    $texto = trim($valor);
    if ($texto === '') {
      continue;
    }
    if (longitudReporte($texto) > 180) {
      throw new DomainException('Uno de los detalles adicionales es demasiado largo');
    }
    $limpio[$clave] = $texto;
  }

  return $limpio === []
    ? null
    : json_encode($limpio, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function fechaSimpleValida(string $fecha): bool
{
  $objeto = DateTime::createFromFormat('Y-m-d', $fecha);
  return $objeto && $objeto->format('Y-m-d') === $fecha;
}
