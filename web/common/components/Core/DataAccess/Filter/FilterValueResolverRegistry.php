<?php

namespace common\components\Core\DataAccess\Filter;

/**
 * Registro de resolvers de filtros declarados en metadata (`resolver:` en YAML).
 */
final class FilterValueResolverRegistry
{
    /** @var array<string, FilterValueResolverInterface> */
    private static $instances = [];

    public static function get(string $resolverId): FilterValueResolverInterface
    {
        $resolverId = trim($resolverId);
        if ($resolverId === '') {
            throw new \InvalidArgumentException('resolver de filtro vacío.');
        }

        if (!isset(self::$instances[$resolverId])) {
            self::$instances[$resolverId] = self::build($resolverId);
        }

        return self::$instances[$resolverId];
    }

    private static function build(string $resolverId): FilterValueResolverInterface
    {
        switch ($resolverId) {
            case 'servicio_rol_efector_ids':
                return new ServicioRolEfectorIdsFilterResolver();
            case 'servicio_rol_from_mention':
                return new ServicioRolFromMentionFilterResolver();
            case 'sexo_biologico':
                return new SexoBiologicoFilterResolver();
            default:
                throw new \InvalidArgumentException('resolver de filtro desconocido: ' . $resolverId);
        }
    }
}
