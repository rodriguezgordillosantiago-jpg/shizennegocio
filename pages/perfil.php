<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'perfil';
$pageTitle   = 'Mi Perfil y Cocina';

$success = '';
$error   = '';
$db      = database();
$businessId = (int)($_SESSION['business_id'] ?? 0);

// Obtener datos del negocio actual
$business = null;
try {
    $bStmt = $db->prepare('SELECT * FROM negocios WHERE id_negocio = :id');
    $bStmt->execute(['id' => $businessId]);
    $business = $bStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    //
}

// Procesar formularios (Actualizar datos personales o Registrar Sub-rol de Cocina)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if (mb_strlen($name) < 2) {
            $error = 'Ingresa un nombre válido.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo válido.';
        } else {
            try {
                $parts = preg_split('/\s+/', $name, 2);
                $stmt = $db->prepare(
                    'UPDATE usuario SET nombre = :nombre, apellido = :apellido, email = :email WHERE id_usuario = :id_usuario'
                );
                $stmt->execute([
                    'nombre' => $parts[0] ?? $name,
                    'apellido' => $parts[1] ?? '',
                    'email' => $email,
                    'id_usuario' => $user['id_usuario'],
                ]);
                $_SESSION['usuario_nombre'] = $parts[0] ?? $name;
                $_SESSION['usuario_apellido'] = $parts[1] ?? '';
                $_SESSION['usuario_email'] = $email;
                $user['nombre'] = $parts[0] ?? $name;
                $user['apellido'] = $parts[1] ?? '';
                $user['email'] = $email;
                $success = 'Perfil actualizado correctamente.';
            } catch (Throwable $e) {
                $error = 'Error al actualizar perfil: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'create_kitchen') {
        // Registrar usuario con sub-rol de cocina asociado a este negocio
        $kNombre   = trim((string)($_POST['kitchen_name'] ?? ''));
        $kEmail    = filter_var(trim((string)($_POST['kitchen_email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $kPassword = (string)($_POST['kitchen_password'] ?? '');

        if (mb_strlen($kNombre) < 2) {
            $error = 'Ingresa un nombre válido para el encargado de cocina.';
        } elseif (!$kEmail) {
            $error = 'Ingresa un correo electrónico válido para la cuenta de cocina.';
        } elseif (strlen($kPassword) < 6) {
            $error = 'La contraseña para cocina debe tener al menos 6 caracteres.';
        } else {
            try {
                // Verificar si ya existe este correo
                $chk = $db->prepare('SELECT id_usuario FROM usuario WHERE email = :email');
                $chk->execute(['email' => $kEmail]);
                if ($chk->fetch()) {
                    $error = 'Ya existe un usuario registrado con este correo electrónico.';
                } else {
                    $hash = password_hash($kPassword, PASSWORD_DEFAULT);
                    $parts = preg_split('/\s+/', $kNombre, 2);
                    $ins = $db->prepare('
                        INSERT INTO usuario (nombre, apellido, email, password_hash, rol, id_cocina_negocio_asociado)
                        VALUES (:nombre, :apellido, :email, :pass, "cocina", :bus_id)
                    ');
                    $ins->execute([
                        'nombre'     => $parts[0] ?? $kNombre,
                        'apellido'   => $parts[1] ?? 'Cocina',
                        'email'      => $kEmail,
                        'pass'       => $hash,
                        'bus_id'     => $businessId,
                    ]);
                    $success = '🧑‍🍳 ¡Cuenta de Sub-rol Cocina (' . htmlspecialchars($kEmail) . ') registrada con éxito!';
                }
            } catch (Throwable $e) {
                $error = 'Error al registrar cuenta de cocina: ' . $e->getMessage();
            }
        }
    }
}

// Obtener cuentas de cocina asociadas a este negocio
$kitchenUsers = [];
try {
    $kList = $db->prepare('
        SELECT id_usuario, nombre, apellido, email, rol, fecha_registro
        FROM usuario
        WHERE (rol = "cocina" OR rol = "Cocina") AND id_cocina_negocio_asociado = :b_id
        ORDER BY id_usuario DESC
    ');
    $kList->execute(['b_id' => $businessId]);
    $kitchenUsers = $kList->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    //
}

$initial = strtoupper(substr($user['nombre'] ?? 'U', 0, 1));
$isKitchen = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$roleLabel = $isKitchen ? 'Cocina' : 'Administrador Negocio';
?>
<!DOCTYPE html>
<html lang="es">
<?php require_once __DIR__ . '/../php/includes/head.php'; ?>
<body class="layout <?= $isKitchen ? 'kitchen-layout' : '' ?>">

<?php require_once __DIR__ . '/../php/includes/sidebar.php'; ?>

<div class="main">
  <?php require_once __DIR__ . '/../php/includes/topbar.php'; ?>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-header-title">Mi Perfil y Gestión de Cocina</h1>
        <p class="page-header-sub">Información de la cuenta, datos del negocio y sub-roles de cocina</p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="bx bx-check-circle" style="font-size:18px;flex-shrink:0"></i>
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bx bx-error-circle" style="font-size:18px;flex-shrink:0"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;margin-bottom:24px">

      <!-- Tarjeta 1: Información del Usuario & Negocio Actual -->
      <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
          <div class="card-body" style="text-align:center;padding:28px 20px">
            <div class="profile-avatar-lg" style="margin:0 auto 16px">
              <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <h2 class="profile-name"><?= htmlspecialchars(trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="profile-email"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <div style="margin-top:10px">
              <span class="badge badge-green"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        </div>

        <!-- Información del Negocio Actual -->
        <div class="card">
          <div class="card-header" style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
            <div class="card-title" style="font-size:15px;display:flex;align-items:center;gap:8px">
              <i class="bx bx-store" style="color:#059669;font-size:20px"></i> Negocio Actual
            </div>
          </div>
          <div class="card-body" style="padding:16px 20px">
            <?php if ($business): ?>
              <div style="font-size:16px;font-weight:800;color:#111827;margin-bottom:6px">
                <?= htmlspecialchars($business['nombre'] ?? 'Mi Negocio', ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size:13px;color:#4b5563;margin-bottom:4px">
                <i class="bx bx-map-pin" style="color:#ef4444"></i> <?= htmlspecialchars($business['direccion'] ?? 'Sin dirección registrada', ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size:13px;color:#4b5563">
                <i class="bx bx-envelope" style="color:#2563eb"></i> <?= htmlspecialchars($business['gmail_negocio'] ?? $user['email'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="margin-top:12px;font-size:11px;color:#6b7280;background:#ecfdf5;padding:6px 10px;border-radius:6px;border:1px solid #a7f3d0">
                <strong>ID Negocio:</strong> #<?= (int)$business['id_negocio'] ?>
              </div>
            <?php else: ?>
              <p style="color:#6b7280;font-size:13px">No se encontró información del negocio.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Formularios: Editar Cuenta + Registrar Sub-rol de Cocina -->
      <div style="display:flex;flex-direction:column;gap:24px">

        <!-- Formulario 1: Editar Datos Personales -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Editar información personal</div>
          </div>
          <div class="card-body">
            <form method="post" novalidate>
              <input type="hidden" name="action" value="update_profile">
              <div class="form-group">
                <label class="form-label" for="name">Nombre completo</label>
                <div class="input-icon-wrap">
                  <i class="bx bx-user"></i>
                  <input type="text" id="name" name="name" class="form-control"
                         value="<?= htmlspecialchars(trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="email">Correo electrónico personal</label>
                <div class="input-icon-wrap">
                  <i class="bx bx-envelope"></i>
                  <input type="email" id="email" name="email" class="form-control"
                         value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
              </div>

              <button type="submit" class="btn btn-primary">
                <i class="bx bx-check"></i> Guardar datos de perfil
              </button>
            </form>
          </div>
        </div>

        <!-- Formulario 2: Registrar Sub-Rol de Cocina -->
        <div class="card" style="border:2px solid #bfdbfe">
          <div class="card-header" style="background:#eff6ff">
            <div>
              <div class="card-title" style="color:#1e40af;display:flex;align-items:center;gap:8px">
                🧑‍🍳 Registrar Sub-Rol de Cocina
              </div>
              <div class="card-subtitle" style="color:#3b82f6">
                Crea usuarios dedicados exclusivamente a la vista de cocina para tu negocio
              </div>
            </div>
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="action" value="create_kitchen">

              <div class="form-group">
                <label class="form-label">Nombre del encargado de cocina *</label>
                <div class="input-icon-wrap">
                  <i class="bx bx-user"></i>
                  <input type="text" name="kitchen_name" class="form-control" placeholder="Ej: Pedro Cocina" required>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                  <label class="form-label">Correo electrónico (para login de cocina) *</label>
                  <div class="input-icon-wrap">
                    <i class="bx bx-envelope"></i>
                    <input type="email" name="kitchen_email" class="form-control" placeholder="cocina@minegocio.com" required>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Contraseña *</label>
                  <div class="input-icon-wrap">
                    <i class="bx bx-lock-alt"></i>
                    <input type="password" name="kitchen_password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                  </div>
                </div>
              </div>

              <button type="submit" class="btn btn-primary" style="background:#2563eb;border:none">
                <i class="bx bx-plus-circle"></i> Registrar Sub-Rol Cocina
              </button>
            </form>

            <!-- Lista de sub-roles de cocina registrados -->
            <div style="margin-top:24px;padding-top:16px;border-top:1px dashed #cbd5e1">
              <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:10px">
                Cuentas de cocina asociadas (<?= count($kitchenUsers) ?>)
              </div>
              <?php if (empty($kitchenUsers)): ?>
                <div style="font-size:12px;color:#94a3b8">No has registrado cuentas de cocina todavía.</div>
              <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px">
                  <?php foreach ($kitchenUsers as $ku): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
                      <div>
                        <div style="font-size:13px;font-weight:700;color:#0f172a">
                          🧑‍🍳 <?= htmlspecialchars(trim($ku['nombre'] . ' ' . $ku['apellido']), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="font-size:12px;color:#64748b"><?= htmlspecialchars($ku['email'], ENT_QUOTES, 'UTF-8') ?></div>
                      </div>
                      <span class="badge badge-blue" style="font-size:11px">Rol Cocina</span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

          </div>
        </div>

      </div>

    </div>

  </main>
</div>
</body>
</html>
