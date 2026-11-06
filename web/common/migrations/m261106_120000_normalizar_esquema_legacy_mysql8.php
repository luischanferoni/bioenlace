<?php

use common\components\Shared\Infrastructure\Migration\MigrationEnumColumn;
use common\models\Organization\Servicio;
use common\models\Organization\ServiciosEfector;
use yii\db\Migration;

/**
 * Normaliza el esquema legacy restaurado del dump de producción para MySQL 8:
 *
 * - `servicios.reserva_autogestion_paciente`: ENUM con literal '' duplicado (MySQL 8 lo rechaza
 *   al reconstruir la tabla) → ENUM('SI','NO').
 * - `servicios.teleconsulta_politica`: saca el literal '' que no usa el dominio.
 * - `servicios_efector.formas_atencion`: valores '' y vocabulario legacy `*_RRHH` → literales
 *   canónicos de {@see ServiciosEfector::FORMAS_ATENCION}.
 * - Columnas `FLOAT(M,D)` → `DECIMAL(M,D)` (MySQL 8 deprecó los dígitos en punto flotante).
 * - Enteros con ancho de display (`int(11)`, `tinyint(1)`, …) → sin ancho.
 * - Tablas utf8mb3 → utf8mb4 (MySQL 8 deprecó el juego utf8mb3).
 * - Índices duplicados heredados del dump (MySQL avisa "Duplicate index").
 *
 * Es idempotente: sobre un esquema ya normalizado no emite DDL.
 */
class m261106_120000_normalizar_esquema_legacy_mysql8 extends Migration
{
    private const SERVICIOS = '{{%servicios}}';
    private const SERVICIOS_EFECTOR = '{{%servicios_efector}}';

    public function safeUp(): void
    {
        $this->normalizeServiciosEnum(
            'reserva_autogestion_paciente',
            Servicio::reservaAutogestionPacienteValues(),
            Servicio::RESERVA_AUTOGESTION_PACIENTE_NO,
            'SI = paciente puede reservar turno directo (hub clínica); NO = solo con derivación/staff'
        );
        $this->normalizeServiciosEnum(
            'teleconsulta_politica',
            Servicio::teleconsultaPoliticaValues(),
            Servicio::TELECONSULTA_POLITICA_NINGUNA
        );
        $this->normalizeFormasAtencion();
        $this->normalizeFloatToDecimal('controles_planillas_plz', 'peso', 5, 2);
        $this->normalizeFloatToDecimal('prestaciones_sumar', 'valor', 7, 2);
        $this->normalizeIntegerDisplayWidths();
        $this->convertUtf8mb3ToUtf8mb4();
        $this->dropRedundantLegacyIndexes();
    }

    public function safeDown(): void
    {
        // Normalización de datos: no se revierte (reintroduciría literales ENUM inválidos).
    }

    /**
     * @param list<string> $values
     */
    private function normalizeServiciosEnum(
        string $column,
        array $values,
        string $default,
        ?string $comment = null
    ): void {
        $info = $this->columnInfo(self::SERVICIOS, $column);
        if ($info === null) {
            return;
        }

        $this->updateInvalidEnumRows(self::SERVICIOS, $column, $values, $default);

        if (!$this->isEnum((string) $info['COLUMN_TYPE']) || $this->sameEnumDefinition((string) $info['COLUMN_TYPE'], $values)) {
            return;
        }

        $this->alterColumn(
            self::SERVICIOS,
            $column,
            MigrationEnumColumn::mysqlEnum($values, $default, true, $comment)
        );
    }

    private function normalizeFormasAtencion(): void
    {
        $column = 'formas_atencion';
        $info = $this->columnInfo(self::SERVICIOS_EFECTOR, $column);
        if ($info === null || !$this->isEnum((string) $info['COLUMN_TYPE'])) {
            return;
        }

        $values = array_keys(ServiciosEfector::FORMAS_ATENCION);
        $default = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
        $legacy = [
            'DELEGAR_A_CADA_RRHH' => ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL,
            'DERIVACION_DELEGAR_A_CADA_RRHH' => ServiciosEfector::DERIVACION_DELEGAR_A_CADA_PROFESIONAL,
        ];

        $current = $this->enumValues((string) $info['COLUMN_TYPE']);
        if (array_diff(array_values($legacy), $current) !== []) {
            $union = array_values(array_unique(array_merge($current, $values)));
            $this->alterColumn(
                self::SERVICIOS_EFECTOR,
                $column,
                MigrationEnumColumn::mysqlEnum($union, $default, true)
            );
        }

        foreach ($legacy as $from => $to) {
            $this->update(self::SERVICIOS_EFECTOR, [$column => $to], [$column => $from]);
        }

        $this->updateInvalidEnumRows(self::SERVICIOS_EFECTOR, $column, $values, $default);

        if (!$this->sameEnumDefinition((string) $info['COLUMN_TYPE'], $values)) {
            $this->alterColumn(
                self::SERVICIOS_EFECTOR,
                $column,
                MigrationEnumColumn::mysqlEnum($values, $default, true)
            );
        }
    }

    private function normalizeFloatToDecimal(string $table, string $column, int $precision, int $scale): void
    {
        $info = $this->columnInfo($table, $column);
        if ($info === null) {
            return;
        }

        $type = strtolower(trim((string) $info['COLUMN_TYPE']));
        if ($type === sprintf('decimal(%d,%d)', $precision, $scale)) {
            return;
        }
        if (!str_starts_with($type, 'float')) {
            return;
        }

        $definition = sprintf('DECIMAL(%d,%d)', $precision, $scale);
        $nullable = strtoupper((string) $info['IS_NULLABLE']) === 'YES';
        $definition .= $nullable ? ' NULL' : ' NOT NULL';
        if ($info['COLUMN_DEFAULT'] !== null) {
            $definition .= ' DEFAULT ' . $this->db->quoteValue((string) $info['COLUMN_DEFAULT']);
        } elseif ($nullable) {
            $definition .= ' DEFAULT NULL';
        }
        if ((string) $info['COLUMN_COMMENT'] !== '') {
            $definition .= ' COMMENT ' . $this->db->quoteValue((string) $info['COLUMN_COMMENT']);
        }

        $this->alterColumn($table, $column, $definition);
    }

    /**
     * MySQL 8.4 deprecó el ancho de display en TODOS los enteros (incluido `tinyint(1)`).
     * Yii mapea `tinyint` a entero, así que el ancho no aporta semántica.
     */
    private function normalizeIntegerDisplayWidths(): void
    {
        $rows = $this->db->createCommand(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND DATA_TYPE IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
                AND COLUMN_TYPE REGEXP '^(tinyint|smallint|mediumint|int|bigint)\\\\([0-9]+\\\\)'"
        )->queryAll();

        foreach ($rows as $row) {
            $baseType = strtoupper((string) preg_replace('/\([0-9]+\)/', '', strtolower($row['COLUMN_TYPE'])));
            $definition = $baseType;
            $nullable = strtoupper((string) $row['IS_NULLABLE']) === 'YES';
            $definition .= $nullable ? ' NULL' : ' NOT NULL';
            if ($row['COLUMN_DEFAULT'] !== null) {
                $definition .= ' DEFAULT ' . $this->db->quoteValue((string) $row['COLUMN_DEFAULT']);
            } elseif ($nullable) {
                $definition .= ' DEFAULT NULL';
            }
            if (stripos((string) $row['EXTRA'], 'auto_increment') !== false) {
                $definition .= ' AUTO_INCREMENT';
            }
            if ((string) $row['COLUMN_COMMENT'] !== '') {
                $definition .= ' COMMENT ' . $this->db->quoteValue((string) $row['COLUMN_COMMENT']);
            }

            $this->db->createCommand(
                'ALTER TABLE ' . $this->quoteRawTableName($row['TABLE_NAME'])
                . ' MODIFY ' . $this->db->quoteColumnName($row['COLUMN_NAME']) . ' ' . $definition
            )->execute();
        }
    }

    /**
     * Índices redundantes heredados del dump (mismo `NON_UNIQUE` y mismas columnas que otro
     * índice de la tabla); MySQL avisa "Duplicate index" al importar.
     */
    private function dropRedundantLegacyIndexes(): void
    {
        $redundant = [
            'old.rr_hh' => ['id_profesion_rrhh'],
            'profesional_salud' => ['id_profesion_rrhh'],
            'persona_domicilios_vinculo' => ['id_persona_id'],
            'persona_telefono' => ['id_persona_tel'],
            'turnos' => ['id_efector_t', 'id_servicio_t'],
        ];

        foreach ($redundant as $table => $indexes) {
            foreach ($indexes as $index) {
                if (!$this->indexExists($table, $index)) {
                    continue;
                }
                $this->db->createCommand(
                    'DROP INDEX ' . $this->db->quoteColumnName($index)
                    . ' ON ' . $this->quoteRawTableName($table)
                )->execute();
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index',
            [':table' => $table, ':index' => $index]
        )->queryScalar();
    }

    private function convertUtf8mb3ToUtf8mb4(): void
    {
        $rows = $this->db->createCommand(
            "SELECT TABLE_NAME, TABLE_COLLATION
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_TYPE = 'BASE TABLE'
                AND SUBSTRING_INDEX(TABLE_COLLATION, '_', 1) IN ('utf8', 'utf8mb3')"
        )->queryAll();
        if ($rows === []) {
            return;
        }

        $collations = [
            'utf8_general_ci' => 'utf8mb4_general_ci',
            'utf8mb3_general_ci' => 'utf8mb4_general_ci',
            'utf8_spanish_ci' => 'utf8mb4_spanish_ci',
            'utf8mb3_spanish_ci' => 'utf8mb4_spanish_ci',
        ];

        // Las tablas utf8mb3 se referencian por FK con columnas VARCHAR; hay que convertirlas
        // en bloque. MySQL valida la compatibilidad entre columnas al hacer CONVERT.
        $this->db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();
        try {
            foreach ($rows as $row) {
                $collation = $collations[$row['TABLE_COLLATION']] ?? 'utf8mb4_unicode_ci';
                $this->db->createCommand(
                    'ALTER TABLE ' . $this->db->quoteTableName($row['TABLE_NAME'])
                    . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . $collation
                )->execute();
            }
        } finally {
            $this->db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
        }
    }

    /**
     * @param list<string> $values
     */
    private function updateInvalidEnumRows(string $table, string $column, array $values, string $default): void
    {
        $quoted = array_map(fn (string $value): string => $this->db->quoteValue($value), $values);
        $this->db->createCommand(
            'UPDATE ' . $this->db->quoteTableName($table)
            . ' SET ' . $this->db->quoteColumnName($column) . ' = :default'
            . ' WHERE CAST(' . $this->db->quoteColumnName($column) . ' AS CHAR) NOT IN (' . implode(',', $quoted) . ')',
            [':default' => $default]
        )->execute();
    }

    private function isEnum(string $columnType): bool
    {
        return str_starts_with(strtolower(trim($columnType)), 'enum(');
    }

    /**
     * @param list<string> $values
     */
    private function sameEnumDefinition(string $columnType, array $values): bool
    {
        return $this->isEnum($columnType) && $this->enumValues($columnType) === array_values($values);
    }

    /**
     * @return list<string>
     */
    private function enumValues(string $columnType): array
    {
        if (!preg_match('/^enum\((.*)\)$/is', trim($columnType), $matches)) {
            return [];
        }
        preg_match_all("/'((?:[^']|'')*)'/", $matches[1], $valueMatches);

        return array_map(
            static fn (string $value): string => str_replace("''", "'", $value),
            $valueMatches[1]
        );
    }

    /**
     * @return array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: string|null, COLUMN_COMMENT: string}|null
     */
    private function columnInfo(string $table, string $column): ?array
    {
        $row = $this->db->createCommand(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            [':table' => $this->rawTableName($table), ':column' => $column]
        )->queryOne();

        return $row === false ? null : $row;
    }

    private function rawTableName(string $table): string
    {
        if (preg_match('/^\{\{%(.*)\}\}$/', $table, $matches)) {
            return $this->db->tablePrefix . $matches[1];
        }

        return $table;
    }

    private function quoteRawTableName(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
