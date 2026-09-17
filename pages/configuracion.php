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

$db->exec(
    'CREATE TABLE IF NOT EXISTS negocio_configuracion (
        id_negocio INT PRIMARY KEY,
        min_order INT NOT NULL DEFAULT 15000,
        radius DECIMAL(6,2) NOT NULL DEFAULT 5,
        prep_time INT NOT NULL DEFAULT 25,
        max_orders INT NOT NULL DEFAULT 12,
        cash_enabled TINYINT(1) NOT NULL DEFAULT 1,
        digital_enabled TINYINT(1) NOT NULL DEFAULT 1,
        card_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$defaults = [
    'min_order' => 15000, 'radius' => 5, 'prep_time' => 25, 'max_orders' => 12,
    'cash_enabled' => 1, 'digital_enabled' => 1, 'card_enabled' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'min_order' => max(0, (int)($_POST['minOrder'] ?? $defaults['min_order'])),
        'radius' => max(0, (float)($_POST['radius'] ?? $defaults['radius'])),
        'prep_time' => max(5, min(120, (int)($_POST['prepTime'] ?? $defaults['prep_time']))),
        'max_orders' => max(1, (int)($_POST['maxOrders'] ?? $defaults['max_orders'])),
        'cash_enabled' => (int)($_POST['cash_enabled'] ?? 0),
        'digital_enabled' => (int)($_POST['digital_enabled'] ?? 0),
        'card_enabled' => (int)($_POST['card_enabled'] ?? 0),
    ];
    $stmt = $db->prepare(
        'INSERT INTO negocio_configuracion
            (id_negocio, min_order, radius, prep_time, max_orders, cash_enabled, digital_enabled, card_enabled)
         VALUES (:id_negocio, :min_order, :radius, :prep_time, :max_orders, :cash_enabled, :digital_enabled, :card_enabled)
         ON DUPLICATE KEY UPDATE min_order=VALUES(min_order), radius=VALUES(radius),
            prep_time=VALUES(prep_time), max_orders=VALUES(max_orders),
            cash_enabled=VALUES(cash_enabled), digital_enabled=VALUES(digital_enabled),
            card_enabled=VALUES(card_enabled)'
    );
    $stmt->execute(['id_negocio' => (int)($_SESSION['business_id'] ?? 0)] + $settings);
    $saved = true;
} else {
    $stmt = $db->prepare('SELECT min_order, radius, prep_time, max_orders, cash_enabled, digital_enabled, card_enabled FROM negocio_configuracion WHERE id_negocio = ?');
    $stmt->execute([(int)($_SESSION['business_id'] ?? 0)]);
    $settings = array_merge($defaults, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
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
              <input type="number" class="form-control" name="minOrder" value="<?= htmlspecialchars((string)$settings['min_order'], ENT_QUOTES, 'UTF-8') ?>" placeholder="15000 COP">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Radio de domicilio (km)</label>
              <input type="number" class="form-control" name="radius" value="<?= htmlspecialchars((string)$settings['radius'], ENT_QUOTES, 'UTF-8') ?>">
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
              <input type="number" class="form-control" name="prepTime" value="<?= htmlspecialchars((string)$settings['prep_time'], ENT_QUOTES, 'UTF-8') ?>" min="5" max="120">
            </div>
            <div class="form-group">
              <label class="form-label">Máximo de pedidos simultáneos</label>
              <input type="number" class="form-control" name="maxOrders" value="<?= htmlspecialchars((string)$settings['max_orders'], ENT_QUOTES, 'UTF-8') ?>" min="1">
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
              <input type="hidden" name="cash_enabled" value="<?= (int)$settings['cash_enabled'] ?>">
              <div class="toggle <?= $settings['cash_enabled'] ? 'on' : '' ?>" onclick="this.classList.toggle('on'); this.previousElementSibling.value = this.classList.contains('on') ? '1' : '0'"></div>
            </div>
            <div class="toggle-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px">
              <div>
                <div class="toggle-label">Pagos Digitales (Nequi / Daviplata / PSE)</div>
                <div class="toggle-desc">Transferencias directas COP</div>
              </div>
              <input type="hidden" name="digital_enabled" value="<?= (int)$settings['digital_enabled'] ?>">
              <div class="toggle <?= $settings['digital_enabled'] ? 'on' : '' ?>" onclick="this.classList.toggle('on'); this.previousElementSibling.value = this.classList.contains('on') ? '1' : '0'"></div>
            </div>
            <div class="toggle-row" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px">
              <div>
                <div class="toggle-label">Tarjetas Débito / Crédito</div>
                <div class="toggle-desc">Cobro con datáfono o pasarela</div>
              </div>
              <input type="hidden" name="card_enabled" value="<?= (int)$settings['card_enabled'] ?>">
              <div class="toggle <?= $settings['card_enabled'] ? 'on' : '' ?>" onclick="this.classList.toggle('on'); this.previousElementSibling.value = this.classList.contains('on') ? '1' : '0'"></div>
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
