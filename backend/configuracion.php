<?php

function rutaConfiguracionSafeKids(
  string $variableEntorno,
  string $nombreArchivo
): string {
  $rutaExplicita = getenv($variableEntorno);
  if (is_string($rutaExplicita) && trim($rutaExplicita) !== '') {
    return trim($rutaExplicita);
  }

  $candidatas = [];
  $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
  if (is_string($documentRoot) && trim($documentRoot) !== '') {
    $candidatas[] = dirname(rtrim($documentRoot, '/\\'))
      . DIRECTORY_SEPARATOR
      . 'safekids-config'
      . DIRECTORY_SEPARATOR
      . $nombreArchivo;
  }

  $candidatas[] = 'C:\\xampp\\safekids-config\\' . $nombreArchivo;

  foreach ($candidatas as $ruta) {
    if (is_file($ruta)) {
      return $ruta;
    }
  }

  return $candidatas[0];
}
