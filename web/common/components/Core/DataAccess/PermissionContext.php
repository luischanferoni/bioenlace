<?php

namespace common\components\Core\DataAccess;

/**
 * Contexto de autorización de una consulta (usuario autenticado; identidad ya validada en capa auth).
 */
final class PermissionContext
{
    public int $userId;

    /** @var list<string> */
    public array $roleNames;

    public function __construct(int $userId, array $roleNames = [])
    {
        $this->userId = $userId;
        $this->roleNames = array_values(array_filter(array_map('strval', $roleNames)));
    }

    public static function fromCurrentUser(): self
    {
        $userId = (int) (\Yii::$app->user->id ?? 0);
        $roles = [];
        if ($userId > 0 && \Yii::$app->user->identity !== null) {
            $assigned = \Yii::$app->authManager->getRolesByUser($userId);
            if (is_array($assigned)) {
                $roles = array_keys($assigned);
            }
        }

        return new self($userId, $roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roleNames, true);
    }
}
