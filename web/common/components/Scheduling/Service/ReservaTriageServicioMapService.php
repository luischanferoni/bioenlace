<?php

namespace common\components\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Mapa declarativo rol lógico → criterios sobre {@see Servicio} ({@see metadata/reserva_triage_servicio_map_v1.yaml}).
 */
final class ReservaTriageServicioMapService
{
    private const MAP_FILE = 'reserva_triage_servicio_map_v1.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function getDefaultRol(): string
    {
        $m = self::load();

        return trim((string) ($m['default_rol'] ?? 'clinica_general'));
    }

    /**
     * @return array{nombre_patterns: list<string>, item_names: list<string>}|null
     */
    public function getMatchCriteriaForRol(string $rol): ?array
    {
        $rol = trim($rol);
        if ($rol === '') {
            return null;
        }

        $roles = self::load()['roles'] ?? [];
        if (!is_array($roles) || !isset($roles[$rol]) || !is_array($roles[$rol])) {
            return null;
        }

        return $this->mergeCriteriaForRolDef($roles[$rol], $roles, []);
    }

    public function getLabelForRol(string $rol): string
    {
        $rol = trim($rol);
        $roles = self::load()['roles'] ?? [];
        if (!is_array($roles) || !isset($roles[$rol]) || !is_array($roles[$rol])) {
            return $rol;
        }

        $label = trim((string) ($roles[$rol]['label'] ?? ''));

        return $label !== '' ? $label : $rol;
    }

    /**
     * @param array<string, mixed> $rolDef
     * @param array<string, mixed> $allRoles
     * @param list<string> $visited evita ciclos en inherit_from
     * @return array{nombre_patterns: list<string>, item_names: list<string>}
     */
    private function mergeCriteriaForRolDef(array $rolDef, array $allRoles, array $visited): array
    {
        $patterns = [];
        $itemNames = [];

        $inherit = trim((string) ($rolDef['inherit_from'] ?? ''));
        if ($inherit !== '' && !in_array($inherit, $visited, true)
            && isset($allRoles[$inherit]) && is_array($allRoles[$inherit])) {
            $parent = $this->mergeCriteriaForRolDef(
                $allRoles[$inherit],
                $allRoles,
                array_merge($visited, [$inherit])
            );
            $patterns = array_merge($patterns, $parent['nombre_patterns']);
            $itemNames = array_merge($itemNames, $parent['item_names']);
        }

        foreach ($rolDef['nombre_patterns'] ?? [] as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $patterns[] = $p;
            }
        }
        foreach ($rolDef['item_names'] ?? [] as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $itemNames[] = $n;
            }
        }

        return [
            'nombre_patterns' => array_values(array_unique($patterns)),
            'item_names' => array_values(array_unique($itemNames)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__) . '/metadata/' . self::MAP_FILE;
        if (!is_file($path)) {
            throw new \RuntimeException('Mapa triage→servicio no encontrado: ' . $path);
        }
        $data = Yaml::parseFile($path);
        if (!is_array($data)) {
            throw new \RuntimeException('Mapa triage→servicio inválido.');
        }
        self::$cache = $data;

        return self::$cache;
    }
}
