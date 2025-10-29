<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$error = null;
$success = null;

// Verificar CSRF y método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $error = 'Método no permitido.';
} elseif (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
  $error = 'Token de seguridad inválido.';
} else {
  $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
  $archivos = $_FILES['archivos'] ?? [];

  if ($pedidoId <= 0) {
    $error = 'ID de pedido inválido.';
  } elseif (empty($archivos['name']) || !is_array($archivos['name'])) {
    $error = 'No se seleccionaron archivos.';
  } elseif (!($pdo instanceof PDO)) {
    $error = 'Error de conexión a la base de datos.';
  } else {
    try {
      // Validar pertenencia del pedido al cliente
      $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
      $st->execute([$userId]);
      $clienteId = (int)($st->fetchColumn() ?: 0);

      if (!$clienteId) {
        $error = 'No se encontró el perfil del cliente.';
      } else {
        $q = $pdo->prepare('SELECT id FROM pedidos WHERE id = ? AND cliente_id = ? LIMIT 1');
        $q->execute([$pedidoId, $clienteId]);
        if (!$q->fetchColumn()) {
          $error = 'Pedido no encontrado o no pertenece a tu cuenta.';
        } else {
          // Preparar directorio de destino
          $uploadDir = dirname(__DIR__, 1) . '/uploads/clientes/' . $clienteId . '/pedidos/' . $pedidoId;
          if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
          }

          if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $error = 'No se pudo crear el directorio de archivos.';
          } else {
            // Políticas
            $maxFileSize = 10 * 1024 * 1024; // 10 MB
            $maxTotalSize = 100 * 1024 * 1024; // 100 MB
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

            // Calcular tamaño actual
            $currentSize = 0;
            if (is_dir($uploadDir)) {
              $files = @scandir($uploadDir) ?: [];
              foreach ($files as $fn) {
                if ($fn === '.' || $fn === '..') continue;
                $fp = $uploadDir . DIRECTORY_SEPARATOR . $fn;
                if (is_file($fp)) {
                  $currentSize += @filesize($fp) ?: 0;
                }
              }
            }

            $uploadedFiles = [];
            $totalUploadSize = 0;

            // Procesar archivos
            for ($i = 0; $i < count($archivos['name']); $i++) {
              $fileName = trim((string)($archivos['name'][$i] ?? ''));
              $fileTmpName = (string)($archivos['tmp_name'][$i] ?? '');
              $fileError = (int)($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE);
              $fileSize = (int)($archivos['size'][$i] ?? 0);

              if ($fileError === UPLOAD_ERR_NO_FILE) continue;
              if ($fileError !== UPLOAD_ERR_OK) {
                $error = "Error al subir el archivo '$fileName'.";
                break;
              }
              if ($fileSize > $maxFileSize) {
                $error = "El archivo '$fileName' excede el tamaño máximo de 10 MB.";
                break;
              }

              $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
              if (!in_array($ext, $allowedExts, true)) {
                $error = "Extensión no permitida para '$fileName'. Permitidas: " . implode(', ', $allowedExts);
                break;
              }

              $totalUploadSize += $fileSize;
              if (($currentSize + $totalUploadSize) > $maxTotalSize) {
                $error = "La subida excedería el límite total de 100 MB para este pedido.";
                break;
              }

              // Renombrar para evitar conflictos y problemas de seguridad
              $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
              if (strlen($safeName) > 100) $safeName = substr($safeName, 0, 100);
              $finalName = $safeName . '.' . $ext;

              // Evitar duplicados
              $counter = 1;
              while (file_exists($uploadDir . DIRECTORY_SEPARATOR . $finalName)) {
                $finalName = $safeName . '_' . $counter . '.' . $ext;
                $counter++;
              }

              $finalPath = $uploadDir . DIRECTORY_SEPARATOR . $finalName;
              if (move_uploaded_file($fileTmpName, $finalPath)) {
                $uploadedFiles[] = $finalName;
              } else {
                $error = "No se pudo guardar el archivo '$fileName'.";
                break;
              }
            }

            if (!$error) {
              $count = count($uploadedFiles);
              if ($count > 0) {
                $success = "Se subieron $count archivo(s) correctamente.";
              } else {
                $error = 'No se subió ningún archivo válido.';
              }
            }
          }
        }
      }
    } catch (Throwable $e) {
      $error = 'Error interno al procesar los archivos.';
    }
  }
}

// Redirigir con mensaje
if ($error) {
  $_SESSION['flash_error'] = $error;
} elseif ($success) {
  $_SESSION['flash_success'] = $success;
}

$redirectUrl = '/cliente/archivos.php?pedido_id=' . ($pedidoId ?? 0);
header('Location: ' . $redirectUrl);
exit;