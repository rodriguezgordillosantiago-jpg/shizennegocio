<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Usuario
{
    public static function autenticar(string $email, string $password): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT * FROM usuario WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $usuario = $statement->fetch();

        if (!$usuario || !password_verify($password, (string) $usuario['password_hash'])) {
            return null;
        }

        return $usuario;
    }

    public static function negocio(int $userId): ?array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT id_negocio, nombre FROM negocios WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $negocio = $statement->fetch();

        if ($negocio) {
            return $negocio;
        }

        $statement = Database::getConnection()->prepare(
            'SELECT n.id_negocio, n.nombre
             FROM usuario u
             JOIN negocios n ON n.id_negocio = u.id_cocina_negocio_asociado
             WHERE u.id_usuario = :id_usuario
             LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $kitchenBusiness = $statement->fetch();

        return $kitchenBusiness ?: null;
    }
}
