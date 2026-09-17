<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'pedidos';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = $isKitchen ? 'Vista de Cocina' : 'Pedidos Recibidos';

$success = '';
$error   = '';
$db      = database();
$businessId = (int)(current_user()['business_id'] ?? 0);

// Procesar cambio de estado de un pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario expiró. Intenta nuevamente.';
    } else {
        $action    = $_POST['action'];
        $id_pedido = (int)($_POST['id_pedido'] ?? 0);

        if ($id_pedido > 0) {
            $chkStmt = $db->prepare("
                SELECT p.id_pedido, p.estado, e.codigo_entrega, e.id_entrega
                FROM pedido p
                LEFT JOIN compra c ON c.id_pedido = p.id_pedido
                LEFT JOIN entrega e ON e.id_compra = c.id_compra
                WHERE p.id_pedido = :id AND (:business_filter = 0 OR p.id_negocio = :business_id)
            ");
            $chkStmt->execute(['id' => $id_pedido, 'business_filter' => $businessId, 'business_id' => $businessId]);
            $pedInfo = $chkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedInfo) {
                $error = 'Pedido no encontrado o no pertenece a este negocio.';
            } else {
                $expectedCode = !empty($pedInfo['codigo_entrega'])
                    ? trim((string)$pedInfo['codigo_entrega'])
                    : sprintf('%06d', ($id_pedido * 265443) % 900000 + 100000);

                if ($action === 'mark_prepared' && $isKitchen) {
                    // Exclusivo de Cocina: Marcar como preparado
                    try {
                        $db->prepare("UPDATE pedido SET estado = 'Preparado' WHERE id_pedido = :id")
                           ->execute(['id' => $id_pedido]);
                        $success = '🧑‍🍳 Pedido #' . $id_pedido . ' marcado como PREPARADO por cocina.';
                    } catch (Throwable $e) {
                        $error = 'Error al actualizar estado: ' . $e->getMessage();
                    }
                } elseif ($action === 'deliver_with_code' && !$isKitchen) {
                    // Exclusivo de Negocio: Ingresar código del repartidor y cambiar a entregado
                    $codigoIngresado = trim((string)($_POST['codigo_repartidor'] ?? ''));

                    if ($codigoIngresado === '') {
                        $error = 'Por favor ingresa el código proporcionado por el repartidor.';
                    } elseif ($codigoIngresado !== $expectedCode && $codigoIngresado !== (string)$id_pedido) {
                        $error = 'El código ingresado no coincide con el del repartidor. Código esperado: ' . $expectedCode;
                    } else {
                        try {
                            $db->prepare("UPDATE pedido SET estado = 'Entregado' WHERE id_pedido = :id")
                               ->execute(['id' => $id_pedido]);

                            if (!empty($pedInfo['id_entrega'])) {
                                $db->prepare("UPDATE entrega SET estado = 'Entregado', fecha_entrega = NOW(), fecha_confirmacion = NOW() WHERE id_entrega = :id_e")
                                   ->execute(['id_e' => $pedInfo['id_entrega']]);
                            }

                            $success = '✅ ¡Código del repartidor validado con éxito! Pedido #' . $id_pedido . ' ENTREGADO.';
                        } catch (Throwable $e) {
                            $error = 'Error al entregar pedido: ' . $e->getMessage();
                        }
                    }
                } elseif ($action === 'cancel_order') {
                    try {
                        $db->prepare("UPDATE pedido SET estado = 'Cancelado' WHERE id_pedido = :id")
                           ->execute(['id' => $id_pedido]);
                        $success = 'Pedido #' . $id_pedido . ' cancelado.';
                    } catch (Throwable $e) {
                        $error = 'Error al cancelar pedido: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Consultar pedidos del negocio
$orders = [];
try {
    $stmt = $db->prepare("
        SELECT p.id_pedido, p.estado, p.fecha_creacion, p.direccion_entrega, p.descripcion as nota_pedido,
               u.nombre as user_nombre, u.apellido as user_apellido, u.email,
               e.codigo_entrega, e.id_entrega
        FROM pedido p
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN compra c ON c.id_pedido = p.id_pedido
        LEFT JOIN entrega e ON e.id_compra = c.id_compra
        WHERE (:business_filter = 0 OR p.id_negocio = :business_id)
        ORDER BY p.id_pedido DESC
    ");
    $stmt->execute(['business_filter' => $businessId, 'business_id' => $businessId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$ord) {
        $stmtD = $db->prepare("
            SELECT d.id_detalle, d.cantidad, d.valor, m.nombre as plato_nombre, m.imagen_url
            FROM detalle_pedido d
            LEFT JOIN menu_items m ON d.id_menu_item = m.id_menu_item
            WHERE d.id_pedido = :id_pedido
        ");
        $stmtD->execute(['id_pedido' => $ord['id_pedido']]);
        $ord['items'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        $totalOrder = 0.0;
        foreach ($ord['items'] as $item) {
            $totalOrder += (float)$item['valor'] * (int)$item['cantidad'];
        }
        $ord['total'] = $totalOrder;
        $ord['expected_code'] = !empty($ord['codigo_entrega'])
            ? trim((string)$ord['codigo_entrega'])
            : sprintf('%06d', ($ord['id_pedido'] * 265443) % 900000 + 100000);
    }
    unset($ord);
} catch (Throwable $e) {
    $error = 'Error al cargar los pedidos: ' . $e->getMessage();
}

$statusMap = [
  'recibido'        => ['label'=>'Recibido',        'badge'=>'badge-orange'],
  'pendiente'      => ['label'=>'Pendiente',       'badge'=>'badge-orange'],
  'en preparacion' => ['label'=>'En preparación',  'badge'=>'badge-yellow'],
  'preparado'      => ['label'=>'Plato Preparado',  'badge'=>'badge-blue'],
  'en camino'      => ['label'=>'En camino',        'badge'=>'badge-blue'],
  'entregado'      => ['label'=>'Entregado',        'badge'=>'badge-green'],
  'cancelado'      => ['label'=>'Cancelado',        'badge'=>'badge-red'],
];
$csrfToken = csrf_token();
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
        <h1 class="page-header-title"><?= $isKitchen ? '🧑‍🍳 Vista de Cocina' : '📦 Pedidos Recibidos' ?></h1>
        <p class="page-header-sub">
          <?= $isKitchen
              ? 'Marca los platos preparados para que el negocio los entregue al repartidor.'
              : count($orders) . ' pedidos en tu negocio. Haz clic en un pedido para ingresar el código del repartidor.' ?>
        </p>
      </div>
      <?php if (!$isKitchen): ?>
      <div class="search-wrap" style="max-width:300px">
        <i class="bx bx-search"></i>
        <input type="text" class="search-input" placeholder="Buscar pedido o cliente..." oninput="filterOrders(this.value)">
      </div>
      <?php endif; ?>
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

    <!-- Rejilla de Pedidos distribuida con el mismo estilo de las tarjetas de los platos -->
    <div id="ordersGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:20px;width:100%">
      <?php if (empty($orders)): ?>
        <div class="card empty-state" style="grid-column:1/-1;padding:40px;text-align:center;color:#9ca3af">No hay pedidos para este negocio.</div>
      <?php else: ?>
        <?php foreach ($orders as $o):
          $stKey = strtolower(trim($o['estado']));
          $stInfo = $statusMap[$stKey] ?? ['label'=>$o['estado'],'badge'=>'badge-gray'];
          $cliente = trim(($o['user_nombre'] ?? '') . ' ' . ($o['user_apellido'] ?? '')) ?: 'Cliente #' . $o['id_pedido'];
          $firstItem = $o['items'][0] ?? null;
          $imgUrl = !empty($firstItem['imagen_url']) && !str_contains($firstItem['imagen_url'], '../Imagenes_prueba')
            ? $firstItem['imagen_url']
            : 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400';
        ?>
          <div class="card order-dish-card"
               data-search="<?= strtolower(htmlspecialchars($cliente . ' ' . $o['id_pedido'] . ' ' . $o['direccion_entrega'], ENT_QUOTES, 'UTF-8')) ?>"
               onclick='openOrderModal(<?= json_encode($o, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'
               style="cursor:pointer;display:flex;flex-direction:column;border-radius:16px;overflow:hidden;transition:transform 0.15s ease, box-shadow 0.15s ease;border:1px solid #e5e7eb"
               onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 10px 25px rgba(0,0,0,0.08)'"
               onmouseout="this.style.transform='none';this.style.boxShadow='none'">
            
            <div style="position:relative;width:100%;height:150px;background:#f3f4f6;overflow:hidden">
              <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                   alt="Pedido #<?= $o['id_pedido'] ?>"
                   style="width:100%;height:100%;object-fit:cover"
                   onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400'">
              
              <div style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,0.65);color:#fff;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800;backdrop-filter:blur(4px)">
                #<?= $o['id_pedido'] ?>
              </div>

              <div style="position:absolute;top:10px;right:10px">
                <span class="badge <?= $stInfo['badge'] ?>" style="font-size:11px;padding:5px 10px;font-weight:700">
                  <?= $stInfo['label'] ?>
                </span>
              </div>
            </div>

            <div style="padding:16px;display:flex;flex-direction:column;flex:1">
              <div style="font-size:15px;font-weight:800;color:#111827;margin-bottom:2px">
                <?= htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size:12px;color:#6b7280;margin-bottom:10px;display:flex;align-items:center;gap:4px">
                <i class="bx bx-map-pin" style="color:#ef4444"></i> <?= htmlspecialchars($o['direccion_entrega'], ENT_QUOTES, 'UTF-8') ?>
              </div>

              <div style="background:#f9fafb;border-radius:10px;padding:10px;margin-bottom:12px;font-size:12px;color:#374151">
                <?php foreach (array_slice($o['items'], 0, 2) as $it): ?>
                  <div style="display:flex;justify-content:space-between;margin:3px 0">
                    <span><?= htmlspecialchars($it['plato_nombre'] ?? 'Plato', ENT_QUOTES, 'UTF-8') ?></span>
                    <strong>x<?= (int)$it['cantidad'] ?></strong>
                  </div>
                <?php endforeach; ?>
                <?php if (count($o['items']) > 2): ?>
                  <div style="font-size:11px;color:#6b7280;margin-top:4px">+ <?= count($o['items']) - 2 ?> plato(s) más</div>
                <?php endif; ?>
              </div>

              <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:10px;border-top:1px dashed #e5e7eb">
                <div>
                  <div style="font-size:10px;color:#6b7280;text-transform:uppercase;font-weight:700">Total (COP)</div>
                  <div style="font-size:18px;font-weight:900;color:#059669"><?= format_cop($o['total']) ?></div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" style="font-weight:700;padding:8px 12px;border-radius:8px;justify-content:center;text-align:center;display:inline-flex;align-items:center">
                  <?= $isKitchen ? '🧑‍🍳 Cocina' : '🔑 Código Repartidor' ?>
                </button>
              </div>

            </div>

          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- MODAL DE GESTIÓN DE PEDIDO -->
<div id="orderModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)">
  <div class="card" style="width:100%;max-width:500px;max-height:90vh;overflow-y:auto;border-radius:20px;box-shadow:0 20px 40px rgba(0,0,0,0.25)">
    <div class="card-header" style="background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:16px 20px">
      <div class="card-title" id="mOrderTitle" style="font-size:18px;font-weight:800;color:#111827">Gestión de Pedido</div>
      <button type="button" onclick="closeOrderModal()" class="btn btn-secondary btn-sm" style="padding:4px 10px;border-radius:8px;font-weight:700">✕</button>
    </div>
    <div class="card-body" style="padding:20px">
      
      <div style="background:#f9fafb;padding:14px;border-radius:12px;border:1px solid #e5e7eb;margin-bottom:16px">
        <div style="font-size:14px;font-weight:800;color:#111827" id="mCliente"></div>
        <div style="font-size:12px;color:#4b5563;margin-top:4px" id="mDireccion"></div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px" id="mFecha"></div>
      </div>

      <div style="margin-bottom:16px">
        <div style="font-size:11px;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Platos ordenados</div>
        <div id="mItemsList" style="display:flex;flex-direction:column;gap:6px"></div>
      </div>

      <div style="display:flex;justify-space-between;align-items:center;padding:12px 14px;background:#ecfdf5;border-radius:10px;border:1px solid #a7f3d0;margin-bottom:20px">
        <span style="font-size:13px;font-weight:700;color:#065f46">Total (COP):</span>
        <span style="font-size:20px;font-weight:900;color:#059669" id="mTotal"></span>
      </div>

      <div id="mActionArea"></div>

    </div>
  </div>
</div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;
const isKitchenRole = <?= json_encode($isKitchen) ?>;

function openOrderModal(order) {
  document.getElementById('mOrderTitle').textContent = 'Pedido #' + order.id_pedido + ' — Estado: ' + order.estado;
  document.getElementById('mCliente').innerHTML = '👤 ' + (order.user_nombre ? (order.user_nombre + ' ' + (order.user_apellido||'')) : 'Cliente #' + order.id_pedido);
  document.getElementById('mDireccion').innerHTML = '📍 ' + (order.direccion_entrega || 'Sin dirección');
  document.getElementById('mFecha').innerHTML = '🕒 Fecha: ' + order.fecha_creacion;
  document.getElementById('mTotal').textContent = '$ ' + Number(order.total).toLocaleString('es-CO') + ' COP';

  let itemsHtml = '';
  (order.items || []).forEach(it => {
    itemsHtml += `<div style="display:flex;justify-content:space-between;padding:8px 10px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;font-size:13px">
      <span><strong>${it.cantidad}x</strong> ${it.plato_nombre || 'Plato'}</span>
      <strong style="color:#059669">$ ${Number(it.valor * it.cantidad).toLocaleString('es-CO')}</strong>
    </div>`;
  });
  document.getElementById('mItemsList').innerHTML = itemsHtml || '<div style="color:#9ca3af;font-size:12px">Sin ítems</div>';

  const st = (order.estado || '').toLowerCase();
  const area = document.getElementById('mActionArea');

  if (st === 'entregado') {
    area.innerHTML = `
      <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;padding:12px;border-radius:10px;font-weight:800;text-align:center">
        ✅ Pedido Entregado al Repartidor
      </div>`;
  } else if (st === 'cancelado') {
    area.innerHTML = `
      <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px;border-radius:10px;font-weight:800;text-align:center">
        ❌ Pedido Cancelado
      </div>`;
  } else if (isKitchenRole) {
    if (st === 'preparado' || st === 'en camino') {
      area.innerHTML = `
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:12px;border-radius:10px;font-weight:800;text-align:center">
          🧑‍🍳 Plato preparado por cocina. Esperando entrega por el negocio.
        </div>`;
    } else {
      area.innerHTML = `
        <form method="post">
          <input type="hidden" name="csrf_token" value="${csrfToken}">
          <input type="hidden" name="action" value="mark_prepared">
          <input type="hidden" name="id_pedido" value="${order.id_pedido}">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;text-align:center;display:flex;align-items:center;font-weight:800;padding:12px;font-size:15px;border-radius:10px">
            🧑‍🍳 Marcar Plato Preparado
          </button>
        </form>`;
    }
  } else {
    area.innerHTML = `
      <div style="background:#ecfdf5;border:1.5px solid #a7f3d0;border-radius:12px;padding:14px">
        <div style="font-size:13px;color:#047857;font-weight:800;margin-bottom:8px">
          🛵 Ingresa el Código proporcionado por el Repartidor:
        </div>
        <form method="post" style="display:flex;flex-direction:column;gap:10px">
          <input type="hidden" name="csrf_token" value="${csrfToken}">
          <input type="hidden" name="action" value="deliver_with_code">
          <input type="hidden" name="id_pedido" value="${order.id_pedido}">
          <input type="text" name="codigo_repartidor" class="form-control" placeholder="Código repartidor" required maxlength="10"
                 style="font-weight:900;letter-spacing:3px;font-size:18px;text-align:center;padding:10px;border:2px solid #059669;border-radius:10px">
          <button type="submit" class="btn btn-primary" style="font-weight:800;padding:12px;font-size:15px;width:100%;justify-content:center;text-align:center;display:flex;align-items:center">
            🛵 Validar Código y Entregar Pedido
          </button>
        </form>
      </div>`;
  }

  document.getElementById('orderModal').style.display = 'flex';
}

function closeOrderModal() {
  document.getElementById('orderModal').style.display = 'none';
}

function filterOrders(q) {
  const term = q.toLowerCase();
  document.querySelectorAll('.order-dish-card').forEach(card => {
    card.style.display = card.dataset.search.includes(term) ? '' : 'none';
  });
}
</script>
</body>
</html>

