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
        // PES tiene FKs hijas RESTRICT: borrar árbol antes que la fila PES.
        $this->purgePesTreeForServicio($from);

        foreach (self::FK_ID_SERVICIO_TABLES as $table) {
            if ($table === 'profesional_efector_servicio') {
                continue; // ya manejado en purgePesTreeForServicio
            }
            $this->deleteWhereColumnEquals($table, 'id_servicio', $from);
        }
        $this->nullOrDeleteColumn('turnos', 'id_servicio_asignado', $from);
        $this->nullOrDeleteColumn('encounter_definition', 'service_id', $from);
        $this->nullOrDeleteColumn('service_request', 'target_service_id', $from);
        $this->deleteWhereColumnEquals('consultas_derivaciones', 'id_servicio', $from);
        $this->deleteWhereColumnEquals('turnos', 'id_servicio', $from);
    }

    /**
     * Borra PES y dependientes (condición laboral, agendas, referencias) para un id_servicio.
     */
    private function purgePesTreeForServicio(int $idServicio): void
    {
        $pesTable = '{{%profesional_efector_servicio}}';
        if ($this->db->schema->getTableSchema($pesTable, true) === null) {
            return;
        }

        $pesIds = (new Query())
            ->from($pesTable)
            ->select(['id'])
            ->where(['id_servicio' => $idServicio])
            ->column($this->db);
        $pesIds = array_values(array_filter(array_map('intval', $pesIds), static fn (int $id) => $id > 0));
        if ($pesIds === []) {
            return;
        }

        // Hijos directos de PES (RESTRICT) — orden: versiones → agendas → condición laboral.
        $this->deleteWhereIn('profesional_efector_servicio_agenda_version', 'id_profesional_efector_servicio', $pesIds);
        $this->deleteWhereIn('profesional_efector_servicio_agenda', 'id_profesional_efector_servicio', $pesIds);
        $this->deleteWhereIn('profesional_efector_servicio_condicion_laboral', 'id_profesional_efector_servicio', $pesIds);

        // Resto de columnas *id_profesional_efector_servicio* en el schema (null o delete).
        $this->detachPesReferences($pesIds);

        $this->delete($pesTable, ['id' => $pesIds]);
    }

    /**
     * Desengancha o borra filas que apuntan a PES (cualquier columna id_profesional_efector_servicio*).
     *
     * @param list<int> $pesIds
     */
    private function detachPesReferences(array $pesIds): void
    {
        if ($pesIds === [] || !in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            // Fallback mínimo fuera de MySQL.
            foreach ([
                ['turno_advance_offer_slot', 'id_profesional_efector_servicio'],
                ['turno_advance_offer', 'id_profesional_efector_servicio'],
                ['turno_waitlist', 'id_profesional_efector_servicio'],
                ['individual_slot_lock', 'id_profesional_efector_servicio'],
                ['turnos', 'id_profesional_efector_servicio'],
                ['consultas', 'id_profesional_efector_servicio'],
                ['consultas_derivaciones', 'id_profesional_efector_servicio'],
                ['documentos_externos', 'id_profesional_efector_servicio'],
                ['guardia', 'id_profesional_efector_servicio'],
                ['atenciones_enfermeria', 'id_profesional_efector_servicio'],
                ['seg_nivel_internacion_practica', 'id_profesional_efector_servicio_solicita'],
                ['seg_nivel_internacion_practica', 'id_profesional_efector_servicio_realiza'],
            ] as [$table, $col]) {
                $this->nullOrDeleteWhereIn($table, $col, $pesIds);
            }

            return;
        }

        $dbName = (new Query())->select(new \yii\db\Expression('DATABASE()'))->scalar($this->db);
        $rows = (new Query())
            ->from('information_schema.COLUMNS')
            ->select(['TABLE_NAME', 'COLUMN_NAME', 'IS_NULLABLE'])
            ->where([
                'TABLE_SCHEMA' => $dbName,
            ])
            ->andWhere(['like', 'COLUMN_NAME', 'id_profesional_efector_servicio%', false])
            ->andWhere(['not in', 'TABLE_NAME', [
                'profesional_efector_servicio',
                'profesional_efector_servicio_agenda',
                'profesional_efector_servicio_agenda_version',
                'profesional_efector_servicio_condicion_laboral',
            ]])
            ->all($this->db);

        // Preferir borrar tablas "satélite" (slots/locks) antes que padres.
        usort($rows, static function (array $a, array $b): int {
            $prio = static function (string $t): int {
                if (str_contains($t, '_slot') || str_contains($t, '_lock') || str_contains($t, 'waitlist')) {
                    return 0;
                }
                if (str_contains($t, 'advance_offer')) {
                    return 1;
                }

                return 2;
            };

            return $prio((string) $a['TABLE_NAME']) <=> $prio((string) $b['TABLE_NAME']);
        });

        foreach ($rows as $row) {
            $table = (string) $row['TABLE_NAME'];
            $column = (string) $row['COLUMN_NAME'];
            $allowNull = strtoupper((string) $row['IS_NULLABLE']) === 'YES';
            $full = '{{%' . $table . '}}';
            if ($this->db->schema->getTableSchema($full, true) === null) {
                continue;
            }
            if ($allowNull) {
                $this->update($full, [$column => null], [$column => $pesIds]);
            } else {
                $this->delete($full, [$column => $pesIds]);
            }
        }
    }

    /**
     * @param list<int> $ids
     */
    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $this->delete($full, [$column => $ids]);
    }

    /**
     * @param list<int> $ids
     */
    private function nullOrDeleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        $col = $schema->columns[$column];
        if ($col->allowNull) {
            $this->update($full, [$column => null], [$column => $ids]);
        } else {
            $this->delete($full, [$column => $ids]);
        }
    }

    private function remapColumnIfExists(string $table, string $column, int $from, int $to): void
    {
        $full = '{{%' . $table . '}}';
        $schema = $this->db->schema->getTableSchema($full, true);
        if ($schema === null || !isset($schema->columns[$column])) {
            return;
        }
        // PES: actualizar id_servicio (no borrar el árbol).
        if ($table === 'profesional_efector_servicio' && $column === 'id_servicio') {
            try {
                $this->update($full, [$column => $to], [$column => $from]);
            } catch (\Throwable $e) {
                // Unique (persona,efector,servicio): fusionar borrando origen tras cascade.
                echo "    > remap PES {$from}→{$to} conflicto unique; se purga origen.\n";
                $this->purgePesTreeForServicio($from);
            }

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
        if ($table === 'profesional_efector_servicio' && $column === 'id_servicio') {
            $this->purgePesTreeForServicio($value);

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
