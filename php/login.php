<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$currentRol = strtolower((string)($_SESSION['usuario_rol'] ?? ''));
if (!empty($_SESSION['id_usuario']) && !empty($_SESSION['business_id']) && in_array($currentRol, ['negocio', 'cocina'], true)) {
    header('Location: ' . ($currentRol === 'cocina' ? '../pages/pedidos.php' : '../pages/dashboard.php'));
    exit;
}

$error = '';
$registered = !empty($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'La sesion del formulario expiro. Intenta nuevamente.';
    } else {
        $email    = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string)($_POST['password'] ?? '');

        try {
            $account = $email ? Usuario::autenticar($email, $password) : null;
            $business = $account ? Usuario::negocio((int) $account['id_usuario']) : null;

            if (!$account || !$business || !in_array(strtolower((string) $account['rol']), ['negocio', 'cocina'], true)) {
                throw new RuntimeException('Credenciales no válidas.');
            }

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int) $account['id_usuario'];
            $_SESSION['usuario_nombre'] = (string) $account['nombre'];
            $_SESSION['usuario_apellido'] = (string) ($account['apellido'] ?? '');
            $_SESSION['usuario_email'] = (string) $account['email'];
            $_SESSION['usuario_rol'] = strtolower((string) $account['rol']);
            $_SESSION['business_id'] = (int) $business['id_negocio'];
            header('Location: ' . (strtolower((string) $account['rol']) === 'cocina' ? '../pages/pedidos.php' : '../pages/dashboard.php'));
            exit;
        } catch (Throwable $e) {
            $error = 'No fue posible validar la cuenta en la base de datos.';
        }
        if ($error === '') {
            $error = 'Correo o contrasena incorrectos.';
        }
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesion | SHIZEN Negocio</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/app.css">
</head>
<body>
<div class="login-page">

  <!-- Panel izquierdo con imagen de fondo y tarjeta estetica -->
  <div class="login-left" style="justify-content:flex-end;padding-bottom:48px">
    <div style="background:rgba(0,0,0,0.52);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.18);border-radius:20px;padding:32px 28px;box-shadow:0 10px 30px rgba(0,0,0,0.3)">
      
      <h1 style="font-size:25px;font-weight:800;color:#ffffff;line-height:1.3;margin-bottom:10px;letter-spacing:-0.3px">
        Gestiona tu negocio con <span style="color:#6ee7b7">inteligencia</span>
      </h1>
      
      <p style="font-size:14px;color:rgba(255,255,255,0.85);line-height:1.6;margin-bottom:22px;font-weight:400">
        Plataforma integral para restaurantes vegetarianos y veganos. Controla pedidos, productos y clientes desde un solo lugar.
      </p>

      <div style="display:flex;flex-direction:column;gap:10px">
        <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08)">
          <div style="width:30px;height:30px;border-radius:8px;background:rgba(110,231,183,0.2);color:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="bx bx-line-chart"></i>
          </div>
          <span style="font-size:13.5px;color:#ffffff;font-weight:500">Panel de control en tiempo real</span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08)">
          <div style="width:30px;height:30px;border-radius:8px;background:rgba(110,231,183,0.2);color:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="bx bx-package"></i>
          </div>
          <span style="font-size:13.5px;color:#ffffff;font-weight:500">Gestion de productos y stock</span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08)">
          <div style="width:30px;height:30px;border-radius:8px;background:rgba(110,231,183,0.2);color:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="bx bx-dish"></i>
          </div>
          <span style="font-size:13.5px;color:#ffffff;font-weight:500">Seguimiento de pedidos de cocina</span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08)">
          <div style="width:30px;height:30px;border-radius:8px;background:rgba(110,231,183,0.2);color:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="bx bx-group"></i>
          </div>
          <span style="font-size:13.5px;color:#ffffff;font-weight:500">Historial de clientes</span>
        </div>

        <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.08);padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08)">
          <div style="width:30px;height:30px;border-radius:8px;background:rgba(110,231,183,0.2);color:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
            <i class="bx bx-pie-chart-alt-2"></i>
          </div>
          <span style="font-size:13.5px;color:#ffffff;font-weight:500">Reportes y estadisticas</span>
        </div>
      </div>

    </div>
  </div>

  <!-- Panel derecho con formulario -->
  <div class="login-right">
    <div class="login-form-wrap">
      <div style="margin-bottom:24px;text-align:center">
        <img src="../assets/logo.png" alt="SHIZEN Negocio" style="height:52px;width:auto;display:inline-block;filter:brightness(0)">
      </div>
      <h2 class="login-form-title">Bienvenido de vuelta</h2>
      <p class="login-form-sub">Ingresa tus credenciales para continuar</p>

      <?php if ($registered): ?>
        <div class="alert alert-success">
          <i class="bx bx-check-circle" style="font-size:18px;flex-shrink:0;margin-top:1px"></i>
          Cuenta creada exitosamente. Ya puedes iniciar sesion.
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bx bx-error-circle" style="font-size:18px;flex-shrink:0;margin-top:1px"></i>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label class="form-label" for="email">Correo electronico</label>
          <div class="input-icon-wrap">
            <i class="bx bx-envelope"></i>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="tu@correo.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Contrasena</label>
          <div class="input-icon-wrap">
            <i class="bx bx-lock-alt"></i>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="••••••••" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:8px">
          <i class="bx bx-log-in"></i>
          Iniciar sesion
        </button>
      </form>

      <p class="login-link">
        No tienes cuenta?
        <a href="../forms/registro_negocio.php">Registrate aqui</a>
      </p>

      <div style="margin-top:32px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
        <p style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:8px">CUENTAS DE PRUEBA (CONTRASEÑA: 123456)</p>
        <p style="font-size:13px;color:#374151;margin-bottom:4px">
          <strong>Negocio 1:</strong> contacto@veganocentral.com
        </p>
        <p style="font-size:13px;color:#374151;margin-bottom:4px">
          <strong>Negocio 2:</strong> contacto@ecomarketchapinero.com
        </p>
        <p style="font-size:13px;color:#374151;margin-bottom:4px">
          <strong>Cocina 1:</strong> cocina1@shizen.com
        </p>
        <p style="font-size:13px;color:#374151">
          <strong>Cocina 2:</strong> cocina2@shizen.com
        </p>
      </div>
    </div>
  </div>

</div>
</body>
</html>
