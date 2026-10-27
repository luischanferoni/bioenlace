<?php

namespace common\components\Platform\Core\Permission;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Roles operativos de personal de salud: alias a clave canónica y precedencia.
 *
 * Fuente: {@see ProductMetadataPaths::operationalRolesFile()}.
 */
final class OperationalRoleManifest
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * Clave canónica para un nombre de rol (RBAC o `servicios.item_name`), o null si no está declarado.
     */
    public function canonicalFor(string $roleName): ?string
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return null;
        }

        $alias = $this->aliases()[$roleName] ?? null;

        return $alias !== null ? (string) $alias : null;
    }

    /**
     * Elige la clave canónica de mayor precedencia entre los nombres dados.
     *
     * @param list<string> $roleNames
     */
    public function select(array $roleNames): ?string
    {
        $present = [];
        foreach ($roleNames as $roleName) {
            $canonical = $this->canonicalFor((string) $roleName);
            if ($canonical !== null) {
                $present[$canonical] = true;
            }
        }
        if ($present === []) {
            return null;
        }

        foreach ($this->precedence() as $canonical) {
            if (isset($present[$canonical])) {
                return $canonical;
            }
        }

        // Rol declarado en alias pero ausente de precedence: orden alfabético para ser determinista.
        $remaining = array_keys($present);
        sort($remaining);

        return (string) $remaining[0];
    }

    /**
     * Claves canónicas presentes, ordenadas por precedencia.
     *
     * @param list<string> $roleNames
     * @return list<string>
     */
    public function selectAll(array $roleNames): array
    {
        $present = [];
        foreach ($roleNames as $roleName) {
            $canonical = $this->canonicalFor((string) $roleName);
            if ($canonical !== null) {
                $present[$canonical] = true;
            }
        }

        $out = [];
        foreach ($this->precedence() as $canonical) {
            if (isset($present[$canonical])) {
                $out[] = $canonical;
                unset($present[$canonical]);
            }
        }
        $remaining = array_keys($present);
        sort($remaining);

        return array_merge($out, $remaining);
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        $raw = $this->load()['aliases'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $alias => $canonical) {
            $alias = trim((string) $alias);
            $canonical = trim((string) $canonical);
            if ($alias !== '' && $canonical !== '') {
                $out[$alias] = $canonical;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function precedence(): array
    {
        return $this->stringList($this->load()['precedence'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function canonicalRoles(): array
    {
        $raw = $this->load()['roles'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        return $this->stringList(array_keys($raw));
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::operationalRolesFile();
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }

        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }
}
