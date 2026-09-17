<?php
$navItems = [
  ['href' => '../pages/dashboard.php',      'icon' => 'bx bx-home-alt-2',  'label' => 'Dashboard',       'key' => 'dashboard'],
  ['href' => '../pages/productos.php',      'icon' => 'bx bx-package',      'label' => 'Productos',       'key' => 'productos'],
  ['href' => '../pages/pedidos.php',        'icon' => 'bx bx-receipt',      'label' => 'Pedidos',         'key' => 'pedidos'],
  ['href' => '../pages/clientes.php',       'icon' => 'bx bx-group',        'label' => 'Clientes',        'key' => 'clientes'],
  ['href' => '../pages/notificaciones.php', 'icon' => 'bx bx-bell',         'label' => 'Notificaciones',  'key' => 'notificaciones'],
  ['href' => '../pages/configuracion.php',  'icon' => 'bx bx-cog',          'label' => 'Configuracion',   'key' => 'configuracion'],
  ['href' => '../pages/perfil.php',         'icon' => 'bx bx-user',         'label' => 'Mi Perfil',       'key' => 'perfil'],
];
$currentPage = $currentPage ?? '';
$isKitchen = strtolower((string) (($user ?? current_user())['rol'] ?? '')) === 'cocina';
?>
<?php if ($isKitchen): ?>
<nav class="kitchen-nav">
  <a class="kitchen-brand" href="../pages/pedidos.php"><img src="../assets/logo.png" alt="SHIZEN"></a>
  <div class="kitchen-divider"></div>
  <div class="kitchen-title"><strong>Vista de Cocina</strong><span>Cocinero: <?= htmlspecialchars(trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span></div>
  <a class="kitchen-logout" href="../auth/logout.php">Cerrar Sesion</a>
</nav>
<?php else: ?>
<nav class="sidebar">
  <div class="sidebar-brand" style="padding:16px 20px">
    <a href="../pages/dashboard.php" style="display:block">
      <img src="../assets/logo.png" alt="SHIZEN Negocio" style="max-height:48px;width:auto;display:block;margin:0 auto">
    </a>
  </div>

  <div class="sidebar-nav">
    <?php if (!$isKitchen): ?>
      <p class="sidebar-label">Menu principal</p>
      <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= $currentPage === $item['key'] ? 'active' : '' ?>">
          <i class="<?= $item['icon'] ?>"></i>
          <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="sidebar-label">Área de cocina</p>
      <a href="../pages/pedidos.php" class="nav-item active">
        <i class="bx bx-dish"></i>
        Pedidos
      </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="nav-item nav-item-logout">
      <i class="bx bx-log-out"></i>
      Cerrar sesion
    </a>
  </div>
</nav>
<?php endif; ?>
