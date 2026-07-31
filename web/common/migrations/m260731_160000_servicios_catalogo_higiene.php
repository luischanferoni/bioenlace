<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Limpieza dura del catálogo `servicios` (sin soft-flag / sin retrocompatibilidad):
 * - remapea FKs de filas-acto y admin hacia contenedores válidos
 * - DELETE de actos, admin/logística y duplicados
 * - DROP columnas obsoletas + `oferta_modelo` si existiera
 */
class m260731_160000_servicios_catalogo_higiene extends Migration
{
    private const SNOMED = 'http://snomed.info/sct';
    private const RADIOLOGY = '394914008';

    /** @var list<string> */
    private const IMAGING_CONTAINER_NAMES = [
        'DIAGNOSTICO POR IMAGENES',
        'RADIOLOGIA',
        'BIOIMAGEN',
    ];

    /** acto → contenedor imaging (luego DELETE) */
    private const DELETE_TO_IMAGING = [
        'ECOGRAFIA',
        'MAMOGRAFIA',
        'RAYOS X',
    ];

    /** acto → especialidad contenedora (luego DELETE) */
    private const DELETE_TO_SPECIALTY = [
        'ELECTROCARDIOGRAMA' => ['CARDIOLOGIA'],
        'PAPANICOLAU' => ['GINECOLOGIA', 'APS'],
        'YAG LASER' => ['OFTALMOLOGIA'],
        'LASER' => ['OFTALMOLOGIA'],
    ];

    /** admin / no oferta — DELETE (FK → null o borrar vínculo) */
    private const DELETE_ADMIN = [
        'ADMINISTRACION',
        'LIMPIEZA Y MANTENIMIENTO',
        'NO SE ESPECIFICA',
        'MEDICAMENTOS',
        'CONSERJERIA',
        'TRASLADO',
        'ADMINISTRAR EFECTOR',
        'FACTURACION',
        'AUDITORIA',
        'FISIOTERAPIA (DUP)',
    ];

    /** @var list<string> */
    private const DROP_COLUMNS = [
        'hallazgos_ecl',
        'medicamentos_ecl',
        'procedimientos_ecl',
        'verificacion_sisa',
        'profesion_snomed',
        'oferta_modelo',
    ];

    /** tablas con columna id_servicio a remapar/limpiar */
    private const FK_ID_SERVICIO_TABLES = [
        'profesional_efector_servicio',
        'servicios_efector',
        'linea_acto',
        'turnos',
        'referencia',
        'reserva_triage_codigo_servicio',
        'sensibilidad_regla_servicio',
        'servicio_teleconsulta_caso',
        'infraestructura_sala',
        'integration_fhir_service_code',
        'profesional_cobertura',
    ];

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            echo "    > servicios no existe; omitida.\n";

            return;
        }

        $rows = (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db);
        $byName = $this->indexByNormalizedName($rows);

        $imagingId = $this->firstExistingId($byName, self::IMAGING_CONTAINER_NAMES);
        if ($imagingId !== null) {
            $this->update(
                $servicios,
                [
                    'tipo' => 'diagnostico',
                    'specialty_code' => self::RADIOLOGY,
                    'specialty_system' => self::SNOMED,
                ],
                ['id_servicio' => $imagingId]
            );
            foreach (self::IMAGING_CONTAINER_NAMES as $name) {
                if (!isset($byName[$name])) {
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
        }

        $this->mergeDuplicateFisioterapia($servicios, $byName);

        foreach (self::DELETE_TO_IMAGING as $name) {
            if (!isset($byName[$name]) || $imagingId === null) {
                continue;
            }
            $from = $byName[$name];
            $this->remapAllServicioFks($from, $imagingId);
            $this->delete($servicios, ['id_servicio' => $from]);
            echo "    > eliminado acto-como-servicio {$name} (id={$from}) → imaging id={$imagingId}\n";
        }

        foreach (self::DELETE_TO_SPECIALTY as $name => $targets) {
            if (!isset($byName[$name])) {
                continue;
            }
            $from = $byName[$name];
            $to = $this->firstExistingId($byName, $targets);
            if ($to !== null) {
                $this->remapAllServicioFks($from, $to);
            } else {
                $this->purgeServicioFks($from);
            }
            $this->delete($servicios, ['id_servicio' => $from]);
            echo "    > eliminado acto-como-servicio {$name} (id={$from})\n";
        }

        // Refrescar índice tras deletes previos
        $rows = (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db);
        $byName = $this->indexByNormalizedName($rows);

        foreach (self::DELETE_ADMIN as $name) {
            if (!isset($byName[$name])) {
                continue;
            }
            $from = $byName[$name];
            $this->purgeServicioFks($from);
            $this->delete($servicios, ['id_servicio' => $from]);
            echo "    > eliminado no-asistencial {$name} (id={$from})\n";
        }

        // Duplicados FISIOTERAPIA restantes (mismo nombre)
        $this->deleteExtraFisioterapia($servicios);

        $this->dropObsoleteColumns($servicios);
        $this->refreshTableComment($servicios);
    }

    public function safeDown(): void
    {
        // Irreversible (DELETE de catálogo).
    }

    /**
     * @param array<string, int> $byName
     */
    private function mergeDuplicateFisioterapia(string $servicios, array &$byName): void
    {
        $ids = [];
        foreach ($byName as $nombre => $id) {
            if ($nombre === 'FISIOTERAPIA') {
                $ids[] = $id;
            }
        }
        sort($ids);
        if (count($ids) < 2) {
            return;
        }
        $keep = array_shift($ids);
        foreach ($ids as $dup) {
            $this->remapAllServicioFks($dup, $keep);
            $this->delete($servicios, ['id_servicio' => $dup]);
            echo "    > FISIOTERAPIA duplicada id={$dup} fusionada en id={$keep}\n";
        }
        $byName = $this->indexByNormalizedName(
            (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db)
        );
    }

    private function deleteExtraFisioterapia(string $servicios): void
    {
        $rows = (new Query())->from($servicios)->select(['id_servicio', 'nombre'])->all($this->db);
        $ids = [];
        foreach ($rows as $row) {
            if ($this->normalizeName((string) $row['nombre']) === 'FISIOTERAPIA') {
                $ids[] = (int) $row['id_servicio'];
            }
        }
        sort($ids);
        if (count($ids) < 2) {
            return;
        }
        $keep = array_shift($ids);
        foreach ($ids as $dup) {
            $this->remapAllServicioFks($dup, $keep);
            $this->delete($servicios, ['id_servicio' => $dup]);
        }
    }

    private function remapAllServicioFks(int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }
        foreach (self::FK_ID_SERVICIO_TABLES as $table) {
            $this->remapColumnIfExists($table, 'id_servicio', $from, $to);
        }
        $this->remapColumnIfExists('turnos', 'id_servicio_asignado', $from, $to);
        $this->remapColumnIfExists('encounter_definition', 'service_id', $from, $to);
        $this->remapColumnIfExists('service_request', 'target_service_id', $from, $to);
        $this->remapColumnIfExists('consultas_derivaciones', 'id_servicio', $from, $to);
    }

    private function purgeServicioFks(int $from): void
    {
        foreach (self::FK_ID_SERVICIO_TABLES as $table) {
            $this->deleteWhereColumnEquals($table, 'id_servicio', $from);
        }
        $this->nullOrDeleteColumn('turnos', 'id_servicio_asignado', $from);
        $this->nullOrDeleteColumn('encounter_definition', 'service_id', $from);
        $this->nullOrDeleteColumn('service_request', 'target_service_id', $from);
        $this->deleteWhereColumnEquals('consultas_derivaciones', 'id_servicio', $from);
        // turnos con id_servicio obligatorio: reasignar es preferible; si queda, borrar filas huérfanas de catálogo admin
        $this->deleteWhereColumnEquals('turnos', 'id_servicio', $from);
    }

    private function remapColumnIfExists(string $table, string $column, int $from, int $to): void
    {
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        try {
            $this->update($full, [$column => $to], [$column => $from]);
        } catch (\Throwable $e) {
            $this->delete($full, [$column => $from]);
            echo "    > remap {$table}.{$column} {$from}→{$to} conflicto; filas origen eliminadas.\n";
        }
    }

    private function deleteWhereColumnEquals(string $table, string $column, int $value): void
    {
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $this->delete($full, [$column => $value]);
    }

    private function nullOrDeleteColumn(string $table, string $column, int $value): void
    {
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $col = $schema->columns[$column];
        if ($col->allowNull) {
            $this->update($full, [$column => null], [$column => $value]);
        } else {
            $this->delete($full, [$column => $value]);
        }
    }

    private function dropObsoleteColumns(string $servicios): void
    {
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            return;
        }
        foreach (self::DROP_COLUMNS as $col) {
            if (isset($schema->columns[$col])) {
                $this->dropColumn($servicios, $col);
            }
        }
    }

    private function refreshTableComment(string $servicios): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }
        $raw = $this->db->schema->getRawTableName($servicios);
        $comment = 'Oferta asistencial del establecimiento (HealthcareService). '
            . 'Solo areas del centro; actos SNOMED van en actos_clinicos/service_request. '
            . 'Ver docs/producto/glosario-servicio-pes-acto.md';
        $this->execute("ALTER TABLE `{$raw}` COMMENT = '" . addslashes($comment) . "'");
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
     * @param list<string> $names
     */
    private function firstExistingId(array $byName, array $names): ?int
    {
        foreach ($names as $name) {
            $n = $this->normalizeName($name);
            if (isset($byName[$n])) {
                return $byName[$n];
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
