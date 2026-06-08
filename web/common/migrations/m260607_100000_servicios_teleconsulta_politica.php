<?php

use common\components\Infra\Migration\MigrationEnumColumn;
use common\models\Servicio;
use yii\db\Migration;

/**
 * Política de teleconsulta por servicio (especialidad) y allowlist de casos de triage.
 */
class m260607_100000_servicios_teleconsulta_politica extends Migration
{
    private const FK_CASO_SERVICIO = 'fk_servicio_teleconsulta_caso_servicio';
    private const UX_CASO_SERVICIO = 'ux_servicio_teleconsulta_caso';

    public function safeUp()
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema !== null && !isset($schema->columns['teleconsulta_politica'])) {
            $this->addColumn(
                $servicios,
                'teleconsulta_politica',
                MigrationEnumColumn::mysqlEnum(
                    Servicio::teleconsultaPoliticaValues(),
                    Servicio::TELECONSULTA_POLITICA_NINGUNA,
                    true,
                    'NINGUNA|TODAS|ALGUNAS'
                )
            );
        }

        $casos = '{{%servicio_teleconsulta_caso}}';
        $casosSchema = $this->db->schema->getTableSchema($casos, true);
        if ($casosSchema === null) {
            $this->createTable($casos, [
                'id' => $this->primaryKey(),
                // Debe coincidir con servicios.id_servicio (INT UNSIGNED); integer() firmado falla el FK (errno 150).
                'id_servicio' => $this->integer()->unsigned()->notNull(),
                'caso_codigo' => $this->string(64)->notNull()->comment('Código del catálogo reserva_triage (ej. control_cronico)'),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            ]);
        } elseif (!$casosSchema->columns['id_servicio']->unsigned) {
            $this->alterColumn($casos, 'id_servicio', $this->integer()->unsigned()->notNull());
        }

        if (!$this->indexExists($casos, self::UX_CASO_SERVICIO)) {
            $this->createIndex(self::UX_CASO_SERVICIO, $casos, ['id_servicio', 'caso_codigo'], true);
        }

        $this->addForeignKeyIfNotExists(
            self::FK_CASO_SERVICIO,
            $casos,
            'id_servicio',
            $servicios,
            'id_servicio',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $casos = '{{%servicio_teleconsulta_caso}}';
        if ($this->db->schema->getTableSchema($casos, true) !== null) {
            $this->dropForeignKeyIfExists(self::FK_CASO_SERVICIO, $casos);
            $this->dropTable($casos);
        }

        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema !== null && isset($schema->columns['teleconsulta_politica'])) {
            $this->dropColumn($servicios, 'teleconsulta_politica');
        }
    }

    private function addForeignKeyIfNotExists(
        string $name,
        string $table,
        $columns,
        string $refTable,
        $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): void {
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $cnt = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND CONSTRAINT_NAME = :n
               AND CONSTRAINT_TYPE = :type',
            [':t' => $raw, ':n' => $name, ':type' => 'FOREIGN KEY']
        )->queryScalar();

        return $cnt > 0;
    }

    private function indexExists(string $table, string $name): bool
    {
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return false;
        }
        $rawName = $this->db->schema->getRawTableName($table);
        $cnt = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND INDEX_NAME = :n',
            [':t' => $rawName, ':n' => $name]
        )->queryScalar();

        return $cnt > 0;
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        if ($this->foreignKeyExists($table, $name)) {
            $this->dropForeignKey($name, $table);
        }
    }
}
