<?php
$user = $user ?? current_user();
if (strtolower((string) ($user['rol'] ?? '')) === 'cocina') {
    return;
}
$initial = strtoupper(substr($user['nombre'] ?? 'U', 0, 1));
$roleLabel = ($user['rol'] ?? 'negocio') === 'negocio' ? 'Negocio' : 'Cocina';
$titles = [
  'dashboard'     => 'Dashboard',
  'productos'     => 'Productos',
  'pedidos'       => 'Pedidos',
  'clientes'      => 'Clientes',
  'notificaciones'=> 'Notificaciones',
  'configuracion' => 'Configuracion',
  'perfil'        => 'Mi Perfil',
];
$topTitle = $titles[$currentPage ?? ''] ?? 'Panel';
?>
<header class="topbar">
  <div class="topbar-left">
    <span class="topbar-title"><?= htmlspecialchars($topTitle, ENT_QUOTES, 'UTF-8') ?></span>
    <span class="topbar-sub">Bienvenido, <?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div class="topbar-right">
    <div class="topbar-user">
      <div class="topbar-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
      <div>
        <div class="topbar-name"><?= htmlspecialchars($user['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
        <div class="topbar-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
</header>
