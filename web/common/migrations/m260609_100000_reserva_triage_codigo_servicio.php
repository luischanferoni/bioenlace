<?php

use common\components\Infra\Migration\MigrationEnumColumn;
use common\models\Servicio;
use yii\db\Migration;

/**
 * Triage de reserva → filas concretas de {@see servicios} (global, N:M por triage_codigo).
 *
 * Reemplaza la resolución legacy vía `servicio_rol` + patrones YAML.
 * para el flujo presencial/autogestión. La tabla {@see reserva_triage_codigo_servicio_rol}
 * permanece por compatibilidad; el seed se genera desde sus filas si existe.
 */
class m260609_100000_reserva_triage_codigo_servicio extends Migration
{
    private const TABLE = '{{%reserva_triage_codigo_servicio}}';
    private const TABLE_ROL = '{{%reserva_triage_codigo_servicio_rol}}';
    private const FK_SERVICIO = 'fk_reserva_triage_codigo_servicio_servicio';
    private const UX_CODIGO_SERVICIO = 'ux_reserva_triage_codigo_servicio_codigo_servicio';

    public function safeUp(): void
    {
        $this->ensureServiciosAutogestionColumn();
        $this->createMappingTable();
        $this->seedHubAutogestionEnServicios();
        $this->seedMappingsDesdeRolLegacy();
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            if ($this->foreignKeyExists(self::TABLE, self::FK_SERVICIO)) {
                $this->dropForeignKey(self::FK_SERVICIO, self::TABLE);
            }
            $this->dropTable(self::TABLE);
        }

        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema !== null && isset($schema->columns['reserva_autogestion_paciente'])) {
            $this->dropColumn($servicios, 'reserva_autogestion_paciente');
        }
    }

    private function ensureServiciosAutogestionColumn(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null || isset($schema->columns['reserva_autogestion_paciente'])) {
            return;
        }

        $this->addColumn(
            $servicios,
            'reserva_autogestion_paciente',
            MigrationEnumColumn::mysqlEnum(
                Servicio::reservaAutogestionPacienteValues(),
                Servicio::RESERVA_AUTOGESTION_PACIENTE_NO,
                true,
                'SI = paciente puede reservar turno directo (hub clínica); NO = solo con derivación/staff'
            )
        );
    }

    private function createMappingTable(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) !== null) {
            return;
        }

        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'triage_codigo' => $this->string(64)->notNull()->comment('Código interno del catálogo triage'),
            'id_servicio' => $this->integer()->unsigned()->notNull(),
            'prioridad' => $this->smallInteger()->notNull()->defaultValue(100),
            'notas' => $this->text()->null(),
        ]);
        $this->createIndex('idx_reserva_triage_codigo_servicio_codigo', self::TABLE, 'triage_codigo');
        $this->createIndex(self::UX_CODIGO_SERVICIO, self::TABLE, ['triage_codigo', 'id_servicio'], true);
        $this->addForeignKey(
            self::FK_SERVICIO,
            self::TABLE,
            'id_servicio',
            '{{%servicios}}',
            'id_servicio',
            'CASCADE',
            'CASCADE'
        );
    }

    private function seedHubAutogestionEnServicios(): void
    {
        $patterns = $this->patronesHubAutogestion();
        $rows = $this->db->createCommand(
            'SELECT id_servicio, nombre FROM {{%servicios}} WHERE acepta_turnos = :si',
            [':si' => 'SI']
        )->queryAll();

        foreach ($rows as $row) {
            $nombre = mb_strtolower(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                $p = mb_strtolower(trim($pattern), 'UTF-8');
                if ($p !== '' && str_contains($nombre, $p)) {
                    $this->db->createCommand()->update(
                        '{{%servicios}}',
                        ['reserva_autogestion_paciente' => Servicio::RESERVA_AUTOGESTION_PACIENTE_SI],
                        ['id_servicio' => (int) $row['id_servicio']]
                    )->execute();
                    break;
                }
            }
        }
    }

    private function seedMappingsDesdeRolLegacy(): void
    {
        $reglas = $this->reglasTriageCodigoRol();
        if ($this->db->schema->getTableSchema(self::TABLE_ROL, true) !== null) {
            $dbRows = $this->db->createCommand(
                'SELECT triage_codigo, servicio_rol, prioridad, notas FROM ' . self::TABLE_ROL
            )->queryAll();
            if ($dbRows !== []) {
                $reglas = [];
                foreach ($dbRows as $row) {
                    $reglas[] = [
                        trim((string) ($row['triage_codigo'] ?? '')),
                        trim((string) ($row['servicio_rol'] ?? '')),
                        (int) ($row['prioridad'] ?? 100),
                        $row['notas'] ?? null,
                    ];
                }
            }
        }

        foreach ($reglas as [$codigo, $rol, $prioridad, $notas]) {
            $codigo = trim($codigo);
            $rol = trim($rol);
            if ($codigo === '' || $rol === '') {
                continue;
            }
            $ids = $this->idsServicioParaRol($rol);
            foreach ($ids as $idServicio) {
                $this->insertMapping($codigo, $idServicio, (int) $prioridad, $notas);
            }
        }
    }

    private function insertMapping(string $codigo, int $idServicio, int $prioridad, ?string $notas): void
    {
        if ($idServicio <= 0) {
            return;
        }
        $exists = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE triage_codigo = :c AND id_servicio = :s',
            [':c' => $codigo, ':s' => $idServicio]
        )->queryScalar();
        if ($exists > 0) {
            return;
        }

        $this->insert(self::TABLE, [
            'triage_codigo' => $codigo,
            'id_servicio' => $idServicio,
            'prioridad' => $prioridad,
            'notas' => $notas,
        ]);
    }

    /**
     * @return list<int>
     */
    private function idsServicioParaRol(string $rol): array
    {
        $patterns = $this->patronesPorRolEspecialidad()[$rol] ?? [];
        if ($patterns === []) {
            return [];
        }

        $rows = $this->db->createCommand(
            'SELECT id_servicio, nombre FROM {{%servicios}} WHERE acepta_turnos = :si',
            [':si' => 'SI']
        )->queryAll();

        $ids = [];
        foreach ($rows as $row) {
            $nombre = mb_strtolower(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            if ($nombre === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                $p = mb_strtolower(trim($pattern), 'UTF-8');
                if ($p !== '' && str_contains($nombre, $p)) {
                    $ids[] = (int) $row['id_servicio'];
                    break;
                }
            }
        }

        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: string|null}>
     */
    private function reglasTriageCodigoRol(): array
    {
        return [
            ['sintoma_nuevo', 'medicina_clinica', 10, 'Default síntoma nuevo'],
            ['control_cronico', 'medicina_clinica', 10, null],
            ['tramite_admin', 'medicina_clinica', 10, null],
            ['zona_cabeza_cuello', 'medicina_clinica', 20, null],
            ['zona_pecho', 'medicina_clinica', 20, null],
            ['zona_abdomen', 'gastroenterologia', 20, null],
            ['zona_espalda', 'traumatologia', 20, null],
            ['zona_brazo_mano', 'traumatologia', 20, null],
            ['zona_pierna_pie', 'traumatologia', 20, null],
            ['zona_piel', 'dermatologia', 20, null],
            ['zona_general', 'medicina_clinica', 20, null],
            ['det_cabeza_dolor', 'medicina_clinica', 50, null],
            ['det_cabeza_mareo', 'neurologia', 50, null],
            ['det_pecho_dolor', 'cardiologia', 50, null],
            ['det_pecho_tos', 'neumonologia', 50, null],
            ['det_abd_dolor', 'gastroenterologia', 50, null],
            ['det_abd_nauseas', 'gastroenterologia', 50, null],
            ['det_espalda_dolor', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo_brazo', 'traumatologia', 50, null],
            ['det_musculo_esfuerzo_pierna', 'traumatologia', 50, null],
            ['det_extremidad_hinchazon', 'medicina_clinica', 50, null],
            ['det_piel_erupcion', 'dermatologia', 50, null],
            ['det_general_fiebre', 'medicina_clinica', 50, null],
            ['det_general_otro', 'medicina_clinica', 50, null],
        ];
    }

    /**
     * @return list<string>
     */
    private function patronesHubAutogestion(): array
    {
        return [
            'med clinica',
            'med clínica',
            'med general',
            'med familiar',
            'medicina clínica',
            'medicina clinica',
            'medicina general',
            'medicina de familia',
        ];
    }

    /**
     * Patrones para seed inicial desde filas legacy de servicio_rol.
     *
     * @return array<string, list<string>>
     */
    private function patronesPorRolEspecialidad(): array
    {
        return [
            'medicina_clinica' => $this->patronesHubAutogestion(),
            'tramite_admin' => array_merge($this->patronesHubAutogestion(), ['tramite', 'trámite']),
            'oftalmologia' => ['oftalmolog'],
            'dermatologia' => ['dermatolog'],
            'traumatologia' => ['traumatolog', 'ortoped'],
            'ginecologia' => ['ginecolog', 'obstetr'],
            'pediatria' => ['pediatr'],
            'cardiologia' => ['cardiolog'],
            'neumonologia' => ['neumonolog'],
            'neurologia' => ['neurolog'],
            'gastroenterologia' => ['gastroenterolog', 'gastro'],
        ];
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
}
