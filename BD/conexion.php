<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Database.php';

function obtenerConexion(): PDO
{
    return Database::getConnection();
}
