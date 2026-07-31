<?php

use yii\db\Expression;
use yii\db\Query;

/**
 * Remapeo / purge de FKs al borrar o fusionar filas de `servicios`.
 * Usado por migraciones de higiene del catálogo institucional.
 */
trait ServiciosCatalogoFkPurgeTrait
{
    /**
     * @return list<string>
     */
    private function fkIdServicioTables(): array
    {
        return [
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
    }

    private function remapAllServicioFks(int $from, int $to): void
    {
        if ($from === $to || $from <= 0 || $to <= 0) {
            return;
        }
        foreach ($this->fkIdServicioTables() as $table) {
            $this->remapColumnIfExists($table, 'id_servicio', $from, $to);
        }
        $this->remapColumnIfExists('turnos', 'id_servicio_asignado', $from, $to);
        $this->remapColumnIfExists('encounter_definition', 'service_id', $from, $to);
        $this->remapColumnIfExists('service_request', 'target_service_id', $from, $to);
        $this->remapColumnIfExists('consultas_derivaciones', 'id_servicio', $from, $to);
    }

    private function purgeServicioFks(int $from): void
    {
        $this->purgePesTreeForServicio($from);

        foreach ($this->fkIdServicioTables() as $table) {
            if ($table === 'profesional_efector_servicio') {
                continue;
            }
            $this->deleteWhereColumnEquals($table, 'id_servicio', $from);
        }
        $this->nullOrDeleteColumn('turnos', 'id_servicio_asignado', $from);
        $this->nullOrDeleteColumn('encounter_definition', 'service_id', $from);
        $this->nullOrDeleteColumn('service_request', 'target_service_id', $from);
        $this->deleteWhereColumnEquals('consultas_derivaciones', 'id_servicio', $from);
        $this->deleteWhereColumnEquals('turnos', 'id_servicio', $from);
    }

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

        $this->deleteWhereIn('profesional_efector_servicio_agenda_version', 'id_profesional_efector_servicio', $pesIds);
        $this->deleteWhereIn('profesional_efector_servicio_agenda', 'id_profesional_efector_servicio', $pesIds);
        $this->deleteWhereIn('profesional_efector_servicio_condicion_laboral', 'id_profesional_efector_servicio', $pesIds);
        $this->detachPesReferences($pesIds);
        $this->delete($pesTable, ['id' => $pesIds]);
    }

    /**
     * @param list<int> $pesIds
     */
    private function detachPesReferences(array $pesIds): void
    {
        if ($pesIds === [] || !in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
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

        $dbName = (new Query())->select(new Expression('DATABASE()'))->scalar($this->db);
        $rows = (new Query())
            ->from('information_schema.COLUMNS')
            ->select(['TABLE_NAME', 'COLUMN_NAME', 'IS_NULLABLE'])
            ->where(['TABLE_SCHEMA' => $dbName])
            ->andWhere(['like', 'COLUMN_NAME', 'id_profesional_efector_servicio%', false])
            ->andWhere(['not in', 'TABLE_NAME', [
                'profesional_efector_servicio',
                'profesional_efector_servicio_agenda',
                'profesional_efector_servicio_agenda_version',
                'profesional_efector_servicio_condicion_laboral',
            ]])
            ->all($this->db);

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
        if ($table === 'profesional_efector_servicio' && $column === 'id_servicio') {
            try {
                $this->update($full, [$column => $to], [$column => $from]);
            } catch (\Throwable $e) {
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

    private function normalizeName(string $nombre): string
    {
        $nombre = trim(mb_strtoupper($nombre, 'UTF-8'));
        $nombre = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'U', 'N'], $nombre);
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        return $nombre;
    }

    /**
     * @param array<string, int> $byName
     * @param list<string> $candidates
     */
    private function firstExistingId(array $byName, array $candidates): ?int
    {
        foreach ($candidates as $name) {
            $key = $this->normalizeName($name);
            if (isset($byName[$key])) {
                return $byName[$key];
            }
        }

        return null;
    }
}
