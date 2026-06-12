<?php

namespace common\components\Core\DataAccess\Grant;

/**
 * Grants efectivos desde BD ({@see DataAccessRoleGrant}).
 */
final class CompositeRoleGrantSource implements RoleGrantSourceInterface
{
    /** @var DatabaseRoleGrantSource */
    private $database;

    public function __construct(?DatabaseRoleGrantSource $database = null)
    {
        $this->database = $database ?? new DatabaseRoleGrantSource();
    }

    public function getGrant(string $roleName, string $entityGroupKey): ?array
    {
        return $this->database->getGrant($roleName, $entityGroupKey);
    }
}
