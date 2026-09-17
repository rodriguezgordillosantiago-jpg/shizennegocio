<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Usuario.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_auth(): void
{
    $rol = strtolower((string)($_SESSION['usuario_rol'] ?? ''));
    if (empty($_SESSION['id_usuario']) || empty($_SESSION['business_id']) || !in_array($rol, ['negocio', 'cocina'], true)) {
        header('Location: ../php/login.php');
        exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['id_usuario'])) {
        return null;
    }

    return [
        'id_usuario' => (int) $_SESSION['id_usuario'],
        'nombre' => (string) ($_SESSION['usuario_nombre'] ?? ''),
        'apellido' => (string) ($_SESSION['usuario_apellido'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
        'rol' => (string) ($_SESSION['usuario_rol'] ?? ''),
        'business_id' => (int) ($_SESSION['business_id'] ?? 0),
    ];
}

function format_cop(float|int|string|null $amount): string
{
    return '$ ' . number_format((float) ($amount ?? 0), 0, ',', '.') . ' COP';
}
