<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'notificaciones';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = 'Notificaciones';

$notifications = [
  ['icon'=>'bx bx-receipt',      'color'=>'#ffedd5','iconColor'=>'#f97316','title'=>'Nuevo pedido #1042',             'body'=>'Ana Martinez realizo un pedido por $28.000', 'time'=>'Hace 5 min',  'read'=>false],
  ['icon'=>'bx bx-error',        'color'=>'#fee2e2','iconColor'=>'#ef4444','title'=>'Stock bajo: Pizza Vegana',        'body'=>'Solo quedan 8 unidades disponibles',         'time'=>'Hace 12 min', 'read'=>false],
  ['icon'=>'bx bx-check-circle', 'color'=>'#d1fae5','iconColor'=>'#059669','title'=>'Pedido #1039 entregado',          'body'=>'Luis Perez recibio su pedido exitosamente',  'time'=>'Hace 28 min', 'read'=>false],
  ['icon'=>'bx bx-user-plus',    'color'=>'#dbeafe','iconColor'=>'#3b82f6','title'=>'Nuevo cliente registrado',        'body'=>'Pedro Romero creo una cuenta nueva',         'time'=>'Hace 1 hora', 'read'=>true],
  ['icon'=>'bx bx-star',         'color'=>'#fef3c7','iconColor'=>'#f59e0b','title'=>'Nueva resena recibida',           'body'=>'Carlos Lopez califico con 5 estrellas',      'time'=>'Hace 2 horas','read'=>true],
  ['icon'=>'bx bx-receipt',      'color'=>'#ffedd5','iconColor'=>'#f97316','title'=>'Pedido #1036 cancelado',          'body'=>'Laura Mendez cancelo su pedido',              'time'=>'Hace 3 horas','read'=>true],
  ['icon'=>'bx bx-check-circle', 'color'=>'#d1fae5','iconColor'=>'#059669','title'=>'Pedido #1035 entregado',          'body'=>'Pedro Romero recibio su orden',               'time'=>'Hace 4 horas','read'=>true],
];
$unread = count(array_filter($notifications, fn($n) => !$n['read']));
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
        <h1 class="page-header-title">Notificaciones</h1>
        <p class="page-header-sub">
          <?= $unread ?> sin leer de <?= count($notifications) ?> total
        </p>
      </div>
      <?php if ($unread > 0): ?>
        <button class="btn btn-secondary" onclick="markAllRead()">
          <i class="bx bx-check-double"></i> Marcar todas como leidas
        </button>
      <?php endif; ?>
    </div>

    <div class="card">
      <?php foreach ($notifications as $i => $n): ?>
        <div class="notif-item <?= !$n['read'] ? 'unread' : '' ?>" id="notif-<?= $i ?>">
          <div class="notif-dot <?= $n['read'] ? 'read' : '' ?>" id="dot-<?= $i ?>"></div>
          <div class="notif-icon" style="background:<?= $n['color'] ?>;color:<?= $n['iconColor'] ?>">
            <i class="<?= $n['icon'] ?>"></i>
          </div>
          <div style="flex:1">
            <div class="notif-title"><?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="notif-body"><?= htmlspecialchars($n['body'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="notif-time">
              <i class="bx bx-time-five"></i>
              <?= htmlspecialchars($n['time'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
          <?php if (!$n['read']): ?>
            <button class="btn btn-secondary btn-sm" onclick="markRead(<?= $i ?>)" style="flex-shrink:0">
              Marcar leida
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </main>
</div>

<script>
function markRead(i) {
  const item = document.getElementById('notif-' + i);
  const dot  = document.getElementById('dot-' + i);
  item.classList.remove('unread');
  dot.classList.add('read');
  item.querySelector('button').remove();
}
function markAllRead() {
  document.querySelectorAll('.notif-item.unread').forEach(item => {
    item.classList.remove('unread');
    item.querySelector('.notif-dot')?.classList.add('read');
    item.querySelector('button')?.remove();
  });
}
</script>
</body>
</html>
