<?php

namespace common\components\Core\DataAccess\Grant;

/**
 * BD tiene prioridad sobre YAML cuando existe grant activo.
 */
final class CompositeRoleGrantSource implements RoleGrantSourceInterface
{
    /** @var DatabaseRoleGrantSource */
    private $database;

    /** @var YamlRoleGrantSource */
    private $yaml;

    public function __construct(
        ?DatabaseRoleGrantSource $database = null,
        ?YamlRoleGrantSource $yaml = null
    ) {
        $this->database = $database ?? new DatabaseRoleGrantSource();
        $this->yaml = $yaml ?? new YamlRoleGrantSource();
    }

    public function getGrant(string $roleName, string $entityGroupKey): ?array
    {
        $db = $this->database->getGrant($roleName, $entityGroupKey);
        if ($db !== null) {
            return $db;
        }

        return $this->yaml->getGrant($roleName, $entityGroupKey);
    }
}
