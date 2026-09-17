<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'clientes';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = 'Clientes';

$clients = [];
$error   = '';
$businessId = (int)($_SESSION['business_id'] ?? 0);

try {
    $db = database();
    // Únicamente personas que han realizado compras en ESTE negocio
    $stmt = $db->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.direccion, u.ciudad, u.fecha_registro,
               COUNT(DISTINCT p.id_pedido) as total_pedidos,
               COALESCE(SUM(d.valor * d.cantidad), 0) as total_gastado,
               MAX(p.fecha_creacion) as ultima_compra
        FROM usuario u
        INNER JOIN pedido p ON u.id_usuario = p.id_usuario
        INNER JOIN detalle_pedido d ON p.id_pedido = d.id_pedido
        WHERE p.id_negocio = :business_id
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.email, u.direccion, u.ciudad, u.fecha_registro
        ORDER BY ultima_compra DESC
    ");
    $stmt->execute(['business_id' => $businessId]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Error al consultar clientes del negocio: ' . $e->getMessage();
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
        <h1 class="page-header-title">Clientes del Negocio</h1>
        <p class="page-header-sub"><?= count($clients) ?> clientes que han comprado en tu negocio</p>
      </div>
      <div class="search-wrap" style="max-width:280px">
        <i class="bx bx-search"></i>
        <input type="text" class="search-input" placeholder="Buscar cliente..."
               oninput="filterClients(this.value)">
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bx bx-error-circle" style="font-size:18px;flex-shrink:0"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Correo electrónico</th>
              <th>Dirección de Entrega</th>
              <th>Pedidos Realizados</th>
              <th>Total Gastado (COP)</th>
              <th>Última Compra</th>
            </tr>
          </thead>
          <tbody id="clientsBody">
            <?php if (empty($clients)): ?>
              <tr>
                <td colspan="6" style="text-align:center;color:#9ca3af;padding:28px">No hay registros de clientes que hayan comprado en tu negocio aún.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($clients as $c):
                $nombreCompleto = trim($c['nombre'] . ' ' . $c['apellido']);
                $initial = strtoupper(substr($c['nombre'] ?? 'C', 0, 1));
                $fechaUltima = !empty($c['ultima_compra']) ? date('d/m/Y H:i', strtotime($c['ultima_compra'])) : date('d/m/Y', strtotime($c['fecha_registro']));
              ?>
                <tr data-name="<?= strtolower(htmlspecialchars($nombreCompleto . ' ' . $c['email'], ENT_QUOTES, 'UTF-8')) ?>">
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div style="width:38px;height:38px;border-radius:50%;background:#d1fae5;color:#047857;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;flex-shrink:0">
                        <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                      <div style="font-weight:700;color:#111827">
                        <?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    </div>
                  </td>
                  <td style="font-size:13px;color:#374151">
                    <?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td style="font-size:13px;color:#4b5563">
                    <?= htmlspecialchars($c['direccion'] ?? 'Dirección registrada en pedido', ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td><strong style="font-size:14px;color:#111827"><?= (int)$c['total_pedidos'] ?></strong></td>
                  <td><strong style="color:#059669;font-size:14px"><?= format_cop($c['total_gastado']) ?></strong></td>
                  <td style="color:#6b7280;font-size:12px"><?= htmlspecialchars($fechaUltima, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script>
function filterClients(q) {
  const term = q.toLowerCase();
  document.querySelectorAll('#clientsBody tr').forEach(row => {
    row.style.display = row.dataset.name ? (row.dataset.name.includes(term) ? '' : 'none') : '';
  });
}
</script>
</body>
</html>
