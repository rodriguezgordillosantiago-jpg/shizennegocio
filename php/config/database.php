<?php
declare(strict_types=1);

declare(strict_types=1);

require_once __DIR__ . '/../../BD/conexion.php';

function database(): PDO
{
    return obtenerConexion();
}
