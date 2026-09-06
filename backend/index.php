<?php
header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
  'status' => 'success',
  'mensaje' => 'SafeKids API activa',
  'api' => '/SafeKids-api/api'
], JSON_UNESCAPED_UNICODE);
