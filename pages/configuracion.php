<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'configuracion';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = 'Configuración';

$saved = false;
$error = '';
$db    = database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saved = true;
}
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
        <h1 class="page-header-title">Configuración del Sistema y Pagos</h1>
        <p class="page-header-sub">Parámetros de atención, métodos de cobro y tiempos en <strong>Pesos Colombianos (COP $)</strong></p>
      </div>
    </div>

    <?php if ($saved): ?>
      <div class="alert alert-success">
        <i class="bx bx-check-circle" style="font-size:18px;flex-shrink:0"></i>
        Configuración guardada exitosamente.
      </div>
    <?php endif; ?>

    <form method="post">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        
        <!-- Parámetros de Moneda y Cobro en Pesos Colombianos -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="bx bx-money"></i> Moneda de Pago y Cobro</div>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Moneda Oficial del Sistema</label>
              <input type="text" class="form-control" value="Pesos Colombianos (COP - $)" disabled style="background:#f0fdf4;color:#047857;font-weight:700">
            </div>
            <div class="form-group">
              <label class="form-label">Valor mínimo de pedido en Pesos Colombianos (COP $)</label>
              <input type="number" class="form-control" name="minOrder" value="15000" placeholder="15000 COP">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Radio de domicilio (km)</label>
              <input type="number" class="form-control" name="radius" value="5">
            </div>
          </div>
        </div>

        <!-- Parámetros de Cocina y Preparación -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><i class="bx bx-dish"></i> Parámetros de Operación</div>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Tiempo estimado de preparación (minutos)</label>
              <input type="number" class="form-control" name="prepTime" value="25" min="5" max="120">
            </div>
            <div class="form-group">
              <label class="form-label">Máximo de pedidos simultáneos</label>
              <input type="number" class="form-control" name="maxOrders" value="12" min="1">
            </div>
          </div>
        </div>

        <!-- Métodos de Pago en Moneda Colombiana -->
        <div class="card" style="grid-column: span 2">
          <div class="card-header">
            <div class="card-title"><i class="bx bx-credit-card"></i> Métodos de Pago Habilitados (COP)</div>
          </div>
          <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
            <div class="toggle-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px">
              <div>
                <div class="toggle-label">Efectivo (COP)</div>
                <div class="toggle-desc">Pago en efectivo al entregar</div>
              </div>
              <div class="toggle on" onclick="this.classList.toggle('on')"></div>
            </div>
            <div class="toggle-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px">
              <div>
                <div class="toggle-label">Pagos Digitales (Nequi / Daviplata / PSE)</div>
                <div class="toggle-desc">Transferencias directas COP</div>
              </div>
              <div class="toggle on" onclick="this.classList.toggle('on')"></div>
            </div>
            <div class="toggle-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px">
              <div>
                <div class="toggle-label">Tarjetas Débito / Crédito</div>
                <div class="toggle-desc">Cobro con datáfono o pasarela</div>
              </div>
              <div class="toggle on" onclick="this.classList.toggle('on')"></div>
            </div>
          </div>
        </div>

      </div>

      <div style="margin-top:24px;display:flex;justify-content:flex-end">
        <button type="submit" class="btn btn-primary btn-lg" style="width:auto">
          <i class="bx bx-save"></i> Guardar configuración
        </button>
      </div>
    </form>

  </main>
</div>
</body>
</html>
