<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\CareProtocol;

/**
 * Catálogo definitional de protocolos de cuidado (PlanDefinition-lite) — fuente: BD.
 */
final class CareProtocolCatalogService
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $overrideForTests = null;

    /** @var array<string, list<array<string, mixed>>> */
    private static array $cacheByKey = [];

    /**
     * Protocolos habilitados para una jurisdicción (NATION + PROVINCE de esa provincia).
     * Sin provincia: solo NATION.
     *
     * @return list<array<string, mixed>>
     */
    public function allProtocols(?int $idProvincia = null, bool $enabledOnly = true): array
    {
        if (self::$overrideForTests !== null) {
            return $this->filterOverride(self::$overrideForTests, $idProvincia, $enabledOnly);
        }

        $cacheKey = ($idProvincia ?? 0) . ':' . ($enabledOnly ? '1' : '0');
        if (isset(self::$cacheByKey[$cacheKey])) {
            return self::$cacheByKey[$cacheKey];
        }

        $q = CareProtocol::find()->orderBy(['orden' => SORT_ASC, 'id' => SORT_ASC]);
        if ($enabledOnly) {
            $q->andWhere(['enabled' => true]);
        }
        if ($idProvincia === null || $idProvincia <= 0) {
            $q->andWhere(['scope_type' => CareProtocol::SCOPE_NATION]);
        } else {
            $q->andWhere([
                'or',
                ['scope_type' => CareProtocol::SCOPE_NATION],
                [
                    'and',
                    ['scope_type' => CareProtocol::SCOPE_PROVINCE],
                    ['id_provincia' => $idProvincia],
                ],
            ]);
        }

        $out = [];
        /** @var CareProtocol $row */
        foreach ($q->all() as $row) {
            $out[] = $row->toCatalogArray();
        }
        self::$cacheByKey[$cacheKey] = $out;

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $protocolId, ?int $idProvincia = null, bool $enabledOnly = true): ?array
    {
        $protocolId = trim($protocolId);
        if ($protocolId === '') {
            return null;
        }
        foreach ($this->allProtocols($idProvincia, $enabledOnly) as $p) {
            if (($p['id'] ?? '') === $protocolId) {
                return $p;
            }
        }

        return null;
    }

    public function defaultOutcome(): string
    {
        return 'captura_mensaje';
    }

    /**
     * @param list<array<string, mixed>>|null $protocols
     */
    public static function setOverrideForTests(?array $protocols): void
    {
        self::$overrideForTests = $protocols;
        self::$cacheByKey = [];
    }

    public static function clearCache(): void
    {
        self::$cacheByKey = [];
    }

    public static function resetCacheForTests(): void
    {
        self::$overrideForTests = null;
        self::$cacheByKey = [];
    }

    /**
     * @param list<array<string, mixed>> $protocols
     * @return list<array<string, mixed>>
     */
    private function filterOverride(array $protocols, ?int $idProvincia, bool $enabledOnly): array
    {
        $out = [];
        foreach ($protocols as $p) {
            if (!is_array($p)) {
                continue;
            }
            if ($enabledOnly && !($p['enabled'] ?? true)) {
                continue;
            }
            $scope = (string) ($p['scope_type'] ?? CareProtocol::SCOPE_NATION);
            $prov = isset($p['id_provincia']) && $p['id_provincia'] !== null ? (int) $p['id_provincia'] : null;
            if ($scope === CareProtocol::SCOPE_NATION) {
                $out[] = $p;
                continue;
            }
            if ($scope === CareProtocol::SCOPE_PROVINCE
                && $idProvincia !== null
                && $idProvincia > 0
                && $prov === $idProvincia
            ) {
                $out[] = $p;
            }
        }
        usort($out, static function (array $a, array $b): int {
            return ((int) ($a['orden'] ?? 100)) <=> ((int) ($b['orden'] ?? 100));
        });

        return $out;
    }
}
