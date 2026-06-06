<?php

namespace common\components\Core\DataAccess\Grant;

use common\components\Core\DataAccess\AttributeGroupCatalog;

final class YamlRoleGrantSource implements RoleGrantSourceInterface
{
    /** @var AttributeGroupCatalog */
    private $catalog;

    public function __construct(?AttributeGroupCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
    }

    public function getGrant(string $roleName, string $entityGroupKey): ?array
    {
        $grant = $this->catalog->getYamlRoleGrant($roleName, $entityGroupKey);
        if ($grant === null) {
            return null;
        }

        return self::normalizeGrant($grant);
    }

    /**
     * @param array<string, mixed> $grant
     * @return array{operations: list<string>, scope_checker?: string|null}
     */
    public static function normalizeGrant(array $grant): array
    {
        $ops = isset($grant['operations']) && is_array($grant['operations'])
            ? array_values(array_filter(array_map('strval', $grant['operations'])))
            : [];
        $checker = trim((string) ($grant['scope_checker'] ?? ''));

        $out = ['operations' => $ops];
        if ($checker !== '') {
            $out['scope_checker'] = $checker;
        }

        return $out;
    }
}
