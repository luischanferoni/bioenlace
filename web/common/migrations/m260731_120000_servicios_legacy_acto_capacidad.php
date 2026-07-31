<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Remapea puentes imaging hacia contenedor institucional (sin soft-flag legacy).
 * Tipifica RADIOLOGIA / DIAGNOSTICO POR IMAGENES / BIOIMAGEN.
 */
class m260731_120000_servicios_legacy_acto_capacidad extends Migration
{
    private const SNOMED = 'http://snomed.info/sct';
    private const RADIOLOGY = '394914008';

    /** @var list<string> */
    private const CONTAINER_NAMES = [
        'DIAGNOSTICO POR IMAGENES',
        'RADIOLOGIA',
        'BIOIMAGEN',
    ];

    /** @var list<string> */
    private const IMAGING_ACT_NAMES = [
        'ECOGRAFIA',
        'MAMOGRAFIA',
        'RAYOS X',
    ];

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            echo "    > servicios no existe; omitida.\n";

            return;
        }

        // Si quedó de un intento previo con soft-flag, se elimina en m260731_160000.
        $rows = (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db);
        $byName = $this->indexByNormalizedName($rows);
        $containerId = $this->resolveImagingContainerId($byName);
        if ($containerId === null) {
            echo "    > sin contenedor imaging; omitido remap.\n";

            return;
        }

        $this->update(
            $servicios,
            [
                'tipo' => 'diagnostico',
                'specialty_code' => self::RADIOLOGY,
                'specialty_system' => self::SNOMED,
            ],
            ['id_servicio' => $containerId]
        );
        foreach (self::CONTAINER_NAMES as $name) {
            if (!isset($byName[$name]) || $byName[$name] === $containerId) {
                continue;
            }
            $this->update(
                $servicios,
                [
                    'tipo' => 'diagnostico',
                    'specialty_code' => self::RADIOLOGY,
                    'specialty_system' => self::SNOMED,
                ],
                ['id_servicio' => $byName[$name]]
            );
        }

        $fromIds = [];
        foreach (self::IMAGING_ACT_NAMES as $name) {
            if (isset($byName[$name])) {
                $fromIds[] = $byName[$name];
            }
        }
        $this->remapLineaActo($fromIds, $containerId);
    }

    public function safeDown(): void
    {
        // Irreversible (remap de puentes).
    }

    /**
     * @param list<int> $fromIds
     */
    private function remapLineaActo(array $fromIds, int $containerId): void
    {
        $lineaActo = '{{%linea_acto}}';
        if ($this->db->schema->getTableSchema($lineaActo, true) === null || $fromIds === []) {
            return;
        }

        $links = (new Query())
            ->from($lineaActo)
            ->where(['id_servicio' => $fromIds])
            ->all($this->db);

        foreach ($links as $link) {
            $actoId = (int) $link['id_acto'];
            $efectorId = $link['id_efector'];
            $preferente = (bool) $link['preferente'];
            $exists = (new Query())
                ->from($lineaActo)
                ->where([
                    'id_servicio' => $containerId,
                    'id_acto' => $actoId,
                    'id_efector' => $efectorId,
                ])
                ->exists($this->db);

            if (!$exists) {
                $this->insert($lineaActo, [
                    'id_servicio' => $containerId,
                    'id_acto' => $actoId,
                    'id_efector' => $efectorId,
                    'preferente' => $preferente ? 1 : 0,
                ]);
            } elseif ($preferente) {
                $this->update(
                    $lineaActo,
                    ['preferente' => 1],
                    [
                        'id_servicio' => $containerId,
                        'id_acto' => $actoId,
                        'id_efector' => $efectorId,
                    ]
                );
            }
            $this->delete($lineaActo, ['id' => (int) $link['id']]);
        }
    }

    /**
     * @param list<array{id_servicio: mixed, nombre: mixed}> $rows
     * @return array<string, int>
     */
    private function indexByNormalizedName(array $rows): array
    {
        $byName = [];
        foreach ($rows as $row) {
            $nombre = $this->normalizeName((string) ($row['nombre'] ?? ''));
            if ($nombre !== '') {
                $byName[$nombre] = (int) $row['id_servicio'];
            }
        }

        return $byName;
    }

    /**
     * @param array<string, int> $byName
     */
    private function resolveImagingContainerId(array $byName): ?int
    {
        foreach (self::CONTAINER_NAMES as $name) {
            if (isset($byName[$name])) {
                return $byName[$name];
            }
        }
        foreach ($byName as $nombre => $id) {
            if (str_contains($nombre, 'RADIOLOG') || str_contains($nombre, 'IMAGEN')) {
                return $id;
            }
        }

        return null;
    }

    private function normalizeName(string $nombre): string
    {
        $n = mb_strtoupper(trim($nombre), 'UTF-8');

        return str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'], ['A', 'E', 'I', 'O', 'U', 'U'], $n);
    }
}
