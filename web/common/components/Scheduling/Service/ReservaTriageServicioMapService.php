<?php

namespace common\components\Scheduling\Service;

use common\models\Servicio;
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

        return trim((string) ($m['default_rol'] ?? 'medicina_clinica'));
    }

    /** Rol hub de autogestión paciente (Medicina clínica / generalistas). */
    public function getHubRol(): string
    {
        $m = self::load();
        $acceso = isset($m['acceso']) && is_array($m['acceso']) ? $m['acceso'] : [];
        $hub = trim((string) ($acceso['hub_rol'] ?? ''));

        return $hub !== '' ? $hub : $this->getDefaultRol();
    }

    public function especialistaSoloTeleconsultaConDerivacion(): bool
    {
        $m = self::load();
        $acceso = isset($m['acceso']) && is_array($m['acceso']) ? $m['acceso'] : [];

        return !empty($acceso['especialista_solo_teleconsulta_con_derivacion']);
    }

    public function isHubRol(string $rol): bool
    {
        $def = $this->getRolDef($rol);

        return $def !== null && !empty($def['hub']);
    }

    public function permiteAutogestionPaciente(string $rol): bool
    {
        $def = $this->getRolDef($rol);
        if ($def === null) {
            return false;
        }

        return !empty($def['autogestion_paciente']);
    }

    public function teleconsultaSoloConDerivacion(string $rol): bool
    {
        $def = $this->getRolDef($rol);
        if ($def === null) {
            return false;
        }

        return !empty($def['teleconsulta_solo_con_derivacion']);
    }

    /**
     * @return array{nombre_patterns: list<string>, item_names: list<string>}|null
     */
    public function getMatchCriteriaForRol(string $rol): ?array
    {
        $def = $this->getRolDef($rol);
        if ($def === null) {
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
        $def = $this->getRolDef($rol);
        if ($def === null) {
            return $rol;
        }
        $label = trim((string) ($def['label'] ?? ''));

        return $label !== '' ? $label : $rol;
    }

    /**
     * Primer rol cuyos criterios coinciden con el servicio (entre elegibles para turnos).
     */
    public function resolveRolForServicio(Servicio $servicio, array $eligibleIds): ?string
    {
        $id = (int) $servicio->id_servicio;
        if ($id <= 0 || ($eligibleIds !== [] && !in_array($id, $eligibleIds, true))) {
            return null;
        }

        $roles = self::load()['roles'] ?? [];
        if (!is_array($roles)) {
            return null;
        }

        foreach (array_keys($roles) as $rolKey) {
            if (!is_string($rolKey) || trim($rolKey) === '') {
                continue;
            }
            $criteria = $this->getMatchCriteriaForRol($rolKey);
            if ($criteria === null) {
                continue;
            }
            if ($this->servicioCoincide($servicio, $criteria['nombre_patterns'], $criteria['item_names'])) {
                return trim($rolKey);
            }
        }

        return null;
    }

    /**
     * @param list<int> $eligibleIds
     */
    public function idsServicioParaRol(string $rol, array $eligibleIds): array
    {
        $rol = trim($rol);
        if ($rol === '' || $eligibleIds === []) {
            return [];
        }

        $criteria = $this->getMatchCriteriaForRol($rol);
        if ($criteria === null) {
            return [];
        }

        $patterns = $criteria['nombre_patterns'];
        $itemNames = $criteria['item_names'];
        if ($patterns === [] && $itemNames === []) {
            return [];
        }

        $rows = Servicio::find()
            ->where(['id_servicio' => $eligibleIds])
            ->all();

        $out = [];
        foreach ($rows as $servicio) {
            if (!$servicio instanceof Servicio) {
                continue;
            }
            if ($this->servicioCoincide($servicio, $patterns, $itemNames)) {
                $out[] = (int) $servicio->id_servicio;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>|null definición merged con inherit_from
     */
    private function getRolDef(string $rol): ?array
    {
        $rol = trim($rol);
        if ($rol === '') {
            return null;
        }
        $roles = self::load()['roles'] ?? [];
        if (!is_array($roles) || !isset($roles[$rol]) || !is_array($roles[$rol])) {
            return null;
        }

        return $this->mergeRolDef($roles[$rol], $roles, []);
    }

    /**
     * @param array<string, mixed> $rolDef
     * @param array<string, mixed> $allRoles
     * @param list<string> $visited
     * @return array<string, mixed>
     */
    private function mergeRolDef(array $rolDef, array $allRoles, array $visited): array
    {
        $merged = [];
        $inherit = trim((string) ($rolDef['inherit_from'] ?? ''));
        if ($inherit !== '' && !in_array($inherit, $visited, true)
            && isset($allRoles[$inherit]) && is_array($allRoles[$inherit])) {
            $merged = $this->mergeRolDef(
                $allRoles[$inherit],
                $allRoles,
                array_merge($visited, [$inherit])
            );
        }
        foreach ($rolDef as $key => $value) {
            if ($key === 'inherit_from') {
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
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
     * @param list<string> $patterns
     * @param list<string> $itemNames
     */
    private function servicioCoincide(Servicio $servicio, array $patterns, array $itemNames): bool
    {
        $nombre = mb_strtolower(trim((string) $servicio->nombre));
        foreach ($patterns as $pattern) {
            $p = mb_strtolower(trim($pattern));
            if ($p !== '' && str_contains($nombre, $p)) {
                return true;
            }
        }

        $item = trim((string) ($servicio->item_name ?? ''));
        if ($item !== '' && in_array($item, $itemNames, true)) {
            return true;
        }

        return false;
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
