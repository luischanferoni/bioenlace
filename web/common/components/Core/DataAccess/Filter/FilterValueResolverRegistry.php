<?php

namespace common\components\Core\DataAccess\Filter;

use common\components\Organization\DataAccess\Filter\ServicioRolEfectorIdsFilterResolver;
use common\components\Organization\DataAccess\Filter\ServicioRolFromMentionFilterResolver;
use common\components\Person\DataAccess\Filter\SexoBiologicoFilterResolver;

/**
 * Registro de resolvers de filtros declarados en metadata (`resolver:` en YAML).
 */
final class FilterValueResolverRegistry
{
    /** @var array<string, FilterValueResolverInterface> */
    private static $instances = [];

    /** @var array<string, class-string<FilterValueResolverInterface>> */
    private const HANDLERS = [
        'servicio_rol_efector_ids' => ServicioRolEfectorIdsFilterResolver::class,
        'servicio_rol_from_mention' => ServicioRolFromMentionFilterResolver::class,
        'sexo_biologico' => SexoBiologicoFilterResolver::class,
    ];

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
        if (!isset(self::HANDLERS[$resolverId])) {
            throw new \InvalidArgumentException('resolver de filtro desconocido: ' . $resolverId);
        }

        $class = self::HANDLERS[$resolverId];

        return new $class();
    }
}
