<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Método no permitido';
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (empty($csrf) || !hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  http_response_code(400);
  echo 'CSRF inválido';
  exit;
}

$pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
$filename = isset($_POST['filename']) ? basename((string)$_POST['filename']) : '';

if ($pedidoId <= 0 || $filename === '') {
  http_response_code(400);
  echo 'Parámetros inválidos';
  exit;
}

try {
  if (!($pdo instanceof PDO)) {
    throw new RuntimeException('BD no disponible');
  }
  // Validar cliente y pertenencia del pedido
  $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
  $st->execute([$userId]);
  $clienteId = (int)($st->fetchColumn() ?: 0);
  if (!$clienteId) {
    throw new RuntimeException('Cliente no encontrado');
  }
  $q = $pdo->prepare('SELECT id FROM pedidos WHERE id = ? AND cliente_id = ? LIMIT 1');
  $q->execute([$pedidoId, $clienteId]);
  if (!$q->fetchColumn()) {
    throw new RuntimeException('Pedido no encontrado o no pertenece a tu cuenta');
  }

  $uploadDir = dirname(__DIR__, 1) . '/uploads/clientes/' . $clienteId . '/pedidos/' . $pedidoId;
  $filePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

  // Validaciones extra del path
  if (!is_file($filePath)) {
    throw new RuntimeException('Archivo no encontrado');
  }
  // Evitar eliminar fuera del directorio
  $realBase = realpath($uploadDir);
  $realFile = realpath($filePath);
  if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0) {
    throw new RuntimeException('Ruta inválida');
  }

  if (!@unlink($filePath)) {
    throw new RuntimeException('No se pudo eliminar el archivo');
  }

  $_SESSION['flash_success'] = 'Archivo eliminado correctamente.';
} catch (Throwable $e) {
  $_SESSION['flash_error'] = 'No se pudo eliminar el archivo: ' . $e->getMessage();
}

header('Location: /cliente/archivos.php?pedido_id=' . $pedidoId);
exit;