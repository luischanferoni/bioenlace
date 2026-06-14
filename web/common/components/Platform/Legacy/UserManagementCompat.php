<?php

namespace common\components\Legacy;

/**
 * Traducciones y menú admin sin depender de webvimark UserManagementModule.
 */
final class UserManagementCompat
{
    /**
     * @param array<string, string|int> $params
     */
    public static function t(string $category, string $message, array $params = []): string
    {
        $map = [
            'Users' => 'Usuarios',
            'User creation' => 'Alta de usuario',
            'Editing user: ' => 'Editar usuario: ',
            'Editing' => 'Edición',
            'Changing password for user: ' => 'Cambiar contraseña: ',
            'Changing password' => 'Cambiar contraseña',
            'Roles and permissions' => 'Roles y permisos',
            'Change password' => 'Cambiar contraseña',
            'Log in as this user' => 'Ingresar como este usuario',
            'Active' => 'Activo',
            'Inactive' => 'Inactivo',
            'Banned' => 'Baneado',
            'Save' => 'Guardar',
            'Create' => 'Crear',
            'Nuevo' => 'Nuevo',
            'Crear' => 'Crear',
            'For example: 123.34.56.78, 168.111.192.12' => 'Ejemplo: 123.34.56.78, 168.111.192.12',
        ];

        $text = $map[$message] ?? $message;
        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Menú «Administrar» (layout producción / legacy).
     *
     * @return list<array<string, mixed>>
     */
    public static function adminMenuItems(): array
    {
        return [
            ['label' => 'Usuarios', 'url' => ['/user-management/user/index']],
            ['label' => 'Catálogo de permisos', 'url' => ['/permission-catalog/index']],
        ];
    }
}
