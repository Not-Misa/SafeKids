<?php

function responderEmpleado(int $codigo, array $contenido): void
{
  http_response_code($codigo);
  echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
  exit();
}

function textoOpcionalEmpleado($valor): ?string
{
  if (!is_string($valor)) {
    return null;
  }

  $texto = trim($valor);
  return $texto === '' ? null : $texto;
}

function longitudEmpleado(string $texto): int
{
  return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
}

function fechaValidaEmpleado(string $fecha): bool
{
  $objeto = DateTime::createFromFormat('Y-m-d', $fecha);
  return $objeto && $objeto->format('Y-m-d') === $fecha;
}

function datosEmpleadoValidados(array $datos): array
{
  $nombres = trim((string) ($datos['nombres'] ?? ''));
  $apellidos = trim((string) ($datos['apellidos'] ?? ''));
  $email = strtolower(trim((string) ($datos['email'] ?? '')));
  $telefono = textoOpcionalEmpleado($datos['telefono'] ?? null);
  $puesto = trim((string) ($datos['puesto'] ?? ''));
  $fechaIngreso = textoOpcionalEmpleado($datos['fecha_ingreso'] ?? null);
  $rol = strtoupper(trim((string) ($datos['rol'] ?? 'EMPLEADO')));
  $notas = textoOpcionalEmpleado($datos['notas'] ?? null);

  if (
    $nombres === '' ||
    longitudEmpleado($nombres) > 100 ||
    $apellidos === '' ||
    longitudEmpleado($apellidos) > 120 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    longitudEmpleado($email) > 190 ||
    $puesto === '' ||
    longitudEmpleado($puesto) > 100 ||
    !in_array($rol, ['ADMIN', 'EMPLEADO'], true)
  ) {
    throw new DomainException('Completa correctamente los datos obligatorios');
  }

  if ($telefono !== null && longitudEmpleado($telefono) > 20) {
    throw new DomainException('El telefono no puede superar 20 caracteres');
  }

  if ($notas !== null && longitudEmpleado($notas) > 500) {
    throw new DomainException('Las notas no pueden superar 500 caracteres');
  }

  if (
    $fechaIngreso !== null &&
    (!fechaValidaEmpleado($fechaIngreso) || $fechaIngreso > date('Y-m-d'))
  ) {
    throw new DomainException('La fecha de ingreso no es valida');
  }

  return [
    'nombres' => $nombres,
    'apellidos' => $apellidos,
    'email' => $email,
    'telefono' => $telefono,
    'puesto' => $puesto,
    'fecha_ingreso' => $fechaIngreso,
    'rol' => $rol,
    'notas' => $notas
  ];
}

function obtenerAdministrador(
  mysqli $conexion,
  int $idEmpleado,
  int $idGuarderia
): ?array {
  $stmt = mysqli_prepare(
    $conexion,
    "SELECT e.id_empleado, e.id_usuario
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
  mysqli_stmt_bind_param($stmt, 'ii', $idEmpleado, $idGuarderia);
  mysqli_stmt_execute($stmt);
  $administrador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $administrador ?: null;
}

function obtenerPersonalEmpleado(
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
  $empleado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  return $empleado ?: null;
}

function passwordTemporalEmpleado(): string
{
  return generarPasswordTemporalSegura();
}
