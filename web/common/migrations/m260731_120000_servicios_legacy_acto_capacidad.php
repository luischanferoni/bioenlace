<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Soft-depreca filas-acto (ECOGRAFIA/MAMOGRAFIA) y remapea linea_acto al contenedor imaging.
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

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            echo "    > servicios no existe; omitida.\n";

            return;
        }

        if (!isset($schema->columns['oferta_modelo'])) {
            $this->addColumn(
                $servicios,
                'oferta_modelo',
                $this->string(32)->notNull()->defaultValue('institucional')
            );
        }

        $legacyNames = ['ECOGRAFIA', 'MAMOGRAFIA'];
        $metaPath = dirname(__DIR__) . '/metadata/bioenlace/clinical/pedido-atencion.yaml';
        if (is_file($metaPath) && class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                $data = \Symfony\Component\Yaml\Yaml::parseFile($metaPath);
                if (is_array($data['legacy_acto_as_servicio_names'] ?? null)) {
                    $legacyNames = [];
                    foreach ($data['legacy_acto_as_servicio_names'] as $n) {
                        $u = mb_strtoupper(trim((string) $n), 'UTF-8');
                        if ($u !== '') {
                            $legacyNames[] = $u;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // keep defaults
            }
        }

        $rows = (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db);
        $legacyIds = [];
        foreach ($rows as $row) {
            $nombre = mb_strtoupper(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            if (in_array($nombre, $legacyNames, true)) {
                $id = (int) $row['id_servicio'];
                $legacyIds[] = $id;
                $this->update(
                    $servicios,
                    ['oferta_modelo' => 'legacy_acto'],
                    ['id_servicio' => $id]
                );
            }
        }

        $containerId = $this->resolveImagingContainerId($rows);
        if ($containerId === null) {
            echo "    > sin contenedor imaging; solo marcado legacy_acto.\n";

            return;
        }

        $this->update(
            $servicios,
            [
                'tipo' => 'diagnostico',
                'specialty_code' => self::RADIOLOGY,
                'specialty_system' => self::SNOMED,
                'oferta_modelo' => 'institucional',
            ],
            ['id_servicio' => $containerId]
        );

        $lineaActo = '{{%linea_acto}}';
        if ($this->db->schema->getTableSchema($lineaActo, true) === null || $legacyIds === []) {
            return;
        }

        $links = (new Query())
            ->from($lineaActo)
            ->where(['id_servicio' => $legacyIds])
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

    public function safeDown(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['oferta_modelo'])) {
            $this->dropColumn($servicios, 'oferta_modelo');
        }
        // No restaura puentes legacy (irreversible a propósito).
    }

    /**
     * @param list<array{id_servicio: mixed, nombre: mixed}> $rows
     */
    private function resolveImagingContainerId(array $rows): ?int
    {
        $byName = [];
        foreach ($rows as $row) {
            $nombre = mb_strtoupper(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            $byName[$nombre] = (int) $row['id_servicio'];
        }
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
}
