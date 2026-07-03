<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de intake consultas / seguimiento ({@see metadata/consultas_seguimiento_intake.yaml}).
 */
final class ConsultasSeguimientoIntakeCatalogService
{
    private const CATALOG_FILE = 'consultas_seguimiento_intake.yaml';

    public const INTAKE_CONSULTA_GENERAL = 'consulta_general';

    public const INTAKE_SEGUIMIENTO = 'seguimiento';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return list<array{code: string, label: string, description: string}>
     */
    public function opcionesTipo(): array
    {
        return $this->mapOpciones(self::load()['intake_tipos'] ?? []);
    }

    /**
     * @return list<array{code: string, label: string, description: string}>
     */
    public function opcionesNecesidad(): array
    {
        return $this->mapOpciones(self::load()['seguimiento_necesidades'] ?? []);
    }

    /**
     * @return list<array{code: string, label: string, description: string, tipo_atencion: string}>
     */
    public function opcionesPreferenciaTurno(): array
    {
        $defs = self::load()['preferencias_turno'] ?? [];
        if (!is_array($defs)) {
            return [];
        }
        $out = [];
        foreach ($defs as $code => $def) {
            if (!is_array($def)) {
                continue;
            }
            $c = trim((string) ($def['code'] ?? $code));
            if ($c === '') {
                continue;
            }
            $out[] = [
                'code' => $c,
                'label' => trim((string) ($def['label'] ?? $c)),
                'description' => trim((string) ($def['description'] ?? '')),
                'tipo_atencion' => trim((string) ($def['tipo_atencion'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{code: string, label: string, description: string, composer_placeholder: string, permite_async: bool}|null
     */
    public function necesidad(string $code): ?array
    {
        $code = trim($code);
        $def = self::load()['seguimiento_necesidades'][$code] ?? null;
        if (!is_array($def)) {
            return null;
        }

        return [
            'code' => $code,
            'label' => trim((string) ($def['label'] ?? $code)),
            'description' => trim((string) ($def['description'] ?? '')),
            'composer_placeholder' => trim((string) ($def['composer_placeholder'] ?? '')),
            'permite_async' => (bool) ($def['permite_async'] ?? true),
        ];
    }

    public function placeholderComposer(string $intakeTipo, ?string $necesidadCode = null): string
    {
        if ($intakeTipo === self::INTAKE_CONSULTA_GENERAL) {
            $def = self::load()['intake_tipos'][self::INTAKE_CONSULTA_GENERAL] ?? [];

            return trim((string) (is_array($def) ? ($def['composer_placeholder'] ?? '') : ''))
                ?: 'Contanos tu consulta con el mayor detalle posible.';
        }
        if ($necesidadCode !== null && $necesidadCode !== '') {
            $n = $this->necesidad($necesidadCode);
            if ($n !== null && $n['composer_placeholder'] !== '') {
                return $n['composer_placeholder'];
            }
        }

        return 'Contanos tu consulta con el mayor detalle posible.';
    }

    /**
     * Acciones de seguimiento para UI de care plan (enlaces al flow con parámetros).
     *
     * @return list<array{code: string, label: string, description: string, intake_tipo: string, seguimiento_necesidad: string}>
     */
    public function accionesSeguimientoCarePlan(): array
    {
        $out = [];
        foreach ($this->opcionesNecesidad() as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => (string) ($row['label'] ?? $code),
                'description' => (string) ($row['description'] ?? ''),
                'intake_tipo' => self::INTAKE_SEGUIMIENTO,
                'seguimiento_necesidad' => $code,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array{code: string, label: string, description: string}>
     */
    private function mapOpciones(array $block): array
    {
        $out = [];
        foreach ($block as $code => $def) {
            if (!is_array($def)) {
                continue;
            }
            $c = trim((string) ($def['code'] ?? $code));
            if ($c === '') {
                continue;
            }
            $out[] = [
                'code' => $c,
                'label' => trim((string) ($def['label'] ?? $c)),
                'description' => trim((string) ($def['description'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);

        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }
}
