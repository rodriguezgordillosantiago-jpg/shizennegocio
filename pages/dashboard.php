<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'dashboard';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = 'Dashboard';

$prodCount    = 0;
$pedidosCount = 0;
$ingresos     = 0.0;
$userCount    = 0;
$recientes    = [];
$topProducts  = [];
$businessId   = (int)($_SESSION['business_id'] ?? 0);

try {
    $db = database();

    // Estadísticas dinámicas filtradas EXCLUSIVAMENTE por el negocio actual
    $stmtProd = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE id_negocio = :b_id");
    $stmtProd->execute(['b_id' => $businessId]);
    $prodCount = (int)$stmtProd->fetchColumn();

    $stmtPed = $db->prepare("SELECT COUNT(*) FROM pedido WHERE id_negocio = :b_id");
    $stmtPed->execute(['b_id' => $businessId]);
    $pedidosCount = (int)$stmtPed->fetchColumn();

    $stmtIng = $db->prepare("
        SELECT COALESCE(SUM(d.valor * d.cantidad), 0)
        FROM detalle_pedido d
        JOIN pedido p ON p.id_pedido = d.id_pedido
        WHERE p.id_negocio = :b_id
    ");
    $stmtIng->execute(['b_id' => $businessId]);
    $ingresos = (float)$stmtIng->fetchColumn();

    $stmtCli = $db->prepare("
        SELECT COUNT(DISTINCT p.id_usuario)
        FROM pedido p
        WHERE p.id_negocio = :b_id
    ");
    $stmtCli->execute(['b_id' => $businessId]);
    $userCount = (int)$stmtCli->fetchColumn();

    // Últimos pedidos en tiempo real del negocio
    $stmtR = $db->prepare("
        SELECT p.id_pedido, p.estado, p.fecha_creacion, p.direccion_entrega,
               u.nombre, u.apellido,
               COALESCE(SUM(d.valor * d.cantidad), 0) as total_pedido
        FROM pedido p
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN detalle_pedido d ON p.id_pedido = d.id_pedido
        WHERE p.id_negocio = :b_id
        GROUP BY p.id_pedido
        ORDER BY p.id_pedido DESC
        LIMIT 5
    ");
    $stmtR->execute(['b_id' => $businessId]);
    $recientes = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    // Productos más vendidos del negocio
    $stmtT = $db->prepare("
        SELECT m.id_menu_item as id, m.nombre, m.precio, m.imagen_url, m.id_categoria as categoria,
               COALESCE(SUM(d.cantidad), 0) as total_vendidos
        FROM menu_items m
        LEFT JOIN detalle_pedido d ON m.id_menu_item = d.id_menu_item
        WHERE m.id_negocio = :b_id
        GROUP BY m.id_menu_item
        ORDER BY total_vendidos DESC, m.id_menu_item DESC
        LIMIT 5
    ");
    $stmtT->execute(['b_id' => $businessId]);
    $topProducts = $stmtT->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // Manejo defensivo
}

$statusBadges = [
  'pendiente'      => 'badge-orange',
  'en preparacion' => 'badge-yellow',
  'en camino'      => 'badge-blue',
  'entregado'      => 'badge-green',
  'cancelado'      => 'badge-red',
];
?>
<!DOCTYPE html>
<html lang="es">
<?php require_once __DIR__ . '/../php/includes/head.php'; ?>
<body class="layout <?= $isKitchen ? 'kitchen-layout' : '' ?>">

<?php require_once __DIR__ . '/../php/includes/sidebar.php'; ?>

<div class="main">
  <?php require_once __DIR__ . '/../php/includes/topbar.php'; ?>

  <main class="content">

    <!-- Tarjetas de Estadísticas conectadas exclusivamente al negocio actual -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bx bx-package"></i></div>
        <div>
          <div class="stat-value"><?= number_format($prodCount) ?></div>
          <div class="stat-label">Productos del negocio</div>
          <div class="stat-change up"><i class="bx bx-check-shield"></i> Tu catálogo</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon orange"><i class="bx bx-receipt"></i></div>
        <div>
          <div class="stat-value"><?= number_format($pedidosCount) ?></div>
          <div class="stat-label">Pedidos recibidos</div>
          <div class="stat-change up"><i class="bx bx-check-shield"></i> Tu negocio</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue"><i class="bx bx-dollar-circle"></i></div>
        <div>
          <div class="stat-value" style="font-size:22px"><?= format_cop($ingresos) ?></div>
          <div class="stat-label">Ventas acumuladas (COP)</div>
          <div class="stat-change up"><i class="bx bx-trending-up"></i> Total de tu negocio</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon yellow"><i class="bx bx-group"></i></div>
        <div>
          <div class="stat-value"><?= number_format($userCount) ?></div>
          <div class="stat-label">Clientes atendidos</div>
          <div class="stat-change up"><i class="bx bx-user-check"></i> Tus clientes</div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px">

      <!-- Tabla de Pedidos Recientes de tu negocio -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Pedidos Recientes de Tu Negocio</div>
            <div class="card-subtitle">Últimos pedidos recibidos</div>
          </div>
          <a href="../pages/pedidos.php" class="btn btn-secondary btn-sm">Ver Cocina / Pedidos</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#Pedido</th>
                <th>Cliente</th>
                <th>Total (COP)</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recientes)): ?>
                <tr>
                  <td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">No hay pedidos registrados para tu negocio aún.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recientes as $r):
                  $stKey = strtolower(trim($r['estado']));
                  $badgeClass = $statusBadges[$stKey] ?? 'badge-gray';
                  $nombreCliente = trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')) ?: 'Cliente #' . $r['id_pedido'];
                ?>
                  <tr>
                    <td><strong>#<?= $r['id_pedido'] ?></strong></td>
                    <td><?= htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><strong style="color:#059669"><?= format_cop($r['total_pedido']) ?></strong></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($r['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Productos Más Vendidos de tu negocio -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Productos Más Vendidos</div>
            <div class="card-subtitle">Ranking de tus platos</div>
          </div>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (empty($topProducts)): ?>
            <div style="padding:24px;text-align:center;color:#9ca3af">Sin registros de venta en tu negocio.</div>
          <?php else: ?>
            <?php foreach ($topProducts as $i => $tp):
              $imgUrl = !empty($tp['imagen_url']) && !str_contains($tp['imagen_url'], '../Imagenes_prueba')
                ? $tp['imagen_url']
                : 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=200';
            ?>
              <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:<?= $i < count($topProducts)-1 ? '1px solid #e5e7eb' : 'none' ?>">
                <div style="width:40px;height:40px;border-radius:8px;overflow:hidden;background:#f3f4f6;flex-shrink:0">
                  <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                       alt="<?= htmlspecialchars($tp['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                       style="width:100%;height:100%;object-fit:cover"
                       onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=200'">
                </div>
                <div style="flex:1">
                  <div style="font-size:14px;font-weight:600"><?= htmlspecialchars($tp['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div style="font-size:12px;color:#6b7280"><?= (int)$tp['total_vendidos'] ?> unidades vendidas</div>
                </div>
                <div style="font-size:13px;font-weight:700;color:#059669"><?= format_cop($tp['precio']) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </main>
</div>
</body>
</html>
