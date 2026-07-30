<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Tipifica `servicios` como línea asistencial; catálogo `actos_clinicos` (SNOMED/LOINC)
 * y puente `linea_acto` para PedidoAtencion.
 */
class m260730_100000_pedido_atencion_linea_acto extends Migration
{
    private const SNOMED = 'http://snomed.info/sct';

    public function safeUp(): void
    {
        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            echo "    > servicios no existe; omitida tipificación.\n";
        } else {
            if (!isset($schema->columns['tipo'])) {
                $this->addColumn(
                    $servicios,
                    'tipo',
                    "ENUM('consulta','diagnostico','laboratorio','procedimiento','soporte') NOT NULL DEFAULT 'consulta'"
                );
            }
            if (!isset($schema->columns['specialty_code'])) {
                $this->addColumn($servicios, 'specialty_code', $this->string(64)->null());
            }
            if (!isset($schema->columns['specialty_system'])) {
                $this->addColumn($servicios, 'specialty_system', $this->string(128)->null());
            }
            $this->seedServicioTipologia();
        }

        if ($this->db->schema->getTableSchema('{{%actos_clinicos}}', true) === null) {
            $this->createTable('{{%actos_clinicos}}', [
                'id' => $this->primaryKey(),
                'code' => $this->string(64)->notNull(),
                'code_system' => $this->string(128)->notNull(),
                'display' => $this->string(512)->notNull(),
                'fhir_category' => $this->string(64)->notNull()->defaultValue('procedure'),
                'created_at' => $this->dateTime()->null(),
                'updated_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex('ux_actos_clinicos_system_code', '{{%actos_clinicos}}', ['code_system', 'code'], true);
        }

        if ($this->db->schema->getTableSchema('{{%linea_acto}}', true) === null) {
            $this->createTable('{{%linea_acto}}', [
                'id' => $this->primaryKey(),
                'id_servicio' => $this->integer()->unsigned()->notNull(),
                'id_acto' => $this->integer()->notNull(),
                'id_efector' => $this->integer()->null(),
                'preferente' => $this->boolean()->notNull()->defaultValue(false),
            ]);
            $this->createIndex(
                'ux_linea_acto_scope',
                '{{%linea_acto}}',
                ['id_servicio', 'id_acto', 'id_efector'],
                true
            );
            $this->addForeignKey(
                'fk_linea_acto_servicio',
                '{{%linea_acto}}',
                'id_servicio',
                '{{%servicios}}',
                'id_servicio',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_linea_acto_acto',
                '{{%linea_acto}}',
                'id_acto',
                '{{%actos_clinicos}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $this->seedActosAndPuentes();
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema('{{%linea_acto}}', true) !== null) {
            $this->dropTable('{{%linea_acto}}');
        }
        if ($this->db->schema->getTableSchema('{{%actos_clinicos}}', true) !== null) {
            $this->dropTable('{{%actos_clinicos}}');
        }

        $servicios = '{{%servicios}}';
        $schema = $this->db->schema->getTableSchema($servicios, true);
        if ($schema === null) {
            return;
        }
        if (isset($schema->columns['specialty_system'])) {
            $this->dropColumn($servicios, 'specialty_system');
        }
        if (isset($schema->columns['specialty_code'])) {
            $this->dropColumn($servicios, 'specialty_code');
        }
        if (isset($schema->columns['tipo'])) {
            $this->dropColumn($servicios, 'tipo');
        }
    }

    private function seedServicioTipologia(): void
    {
        $rows = (new Query())->from('{{%servicios}}')->select(['id_servicio', 'nombre'])->all($this->db);
        foreach ($rows as $row) {
            $nombre = mb_strtoupper(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
            $tipo = 'consulta';
            $specialtyCode = null;
            $specialtySystem = null;

            if (in_array($nombre, ['ADMINISTRACION', 'LIMPIEZA Y MANTENIMIENTO', 'NO SE ESPECIFICA', 'EDUCACION SANITARIA'], true)
                || str_contains($nombre, 'ADMINISTR')
            ) {
                $tipo = 'soporte';
            } elseif ($nombre === 'LABORATORIO' || str_contains($nombre, 'LABORATOR')) {
                $tipo = 'laboratorio';
                $specialtyCode = '261904005'; // Laboratory service (qualifier value) — prefer procedure link via actos
                $specialtySystem = self::SNOMED;
            } elseif (in_array($nombre, ['ECOGRAFIA', 'RADIOLOGIA', 'MAMOGRAFIA'], true)
                || str_contains($nombre, 'ECOGRAF')
                || str_contains($nombre, 'RADIOLOG')
                || str_contains($nombre, 'MAMOGRAF')
            ) {
                $tipo = 'diagnostico';
                $specialtyCode = '394914008'; // Radiology
                $specialtySystem = self::SNOMED;
            } elseif (str_contains($nombre, 'QUIR') || str_contains($nombre, 'CIRUG')) {
                $tipo = 'procedimiento';
            } else {
                $map = $this->specialtyByNombre($nombre);
                if ($map !== null) {
                    $specialtyCode = $map;
                    $specialtySystem = self::SNOMED;
                }
            }

            $this->update(
                '{{%servicios}}',
                [
                    'tipo' => $tipo,
                    'specialty_code' => $specialtyCode,
                    'specialty_system' => $specialtySystem,
                ],
                ['id_servicio' => (int) $row['id_servicio']]
            );
        }
    }

    /**
     * @return string|null SCTID
     */
    private function specialtyByNombre(string $nombre): ?string
    {
        $map = [
            'KINESIOLOGIA' => '394602003', // Rehabilitation medicine
            'PEDIATRIA' => '394537008',
            'GINECOLOGIA' => '394585009',
            'OBSTETRICIA' => '394585009',
            'CARDIOLOGIA' => '394579002',
            'NEUROLOGIA' => '394591006',
            'OFTALMOLOGIA' => '394594003',
            'TRAUMATOLOGIA' => '394801008', // Trauma surgery / orthopedics-ish; Orthopedics 394801008?
            'PSICOLOGIA' => '394587001',
            'ODONTOLOGIA' => '394812008', // Dental medicine? 394812008 Dental surgery - use 394812008
            'NUTRICION' => '722164000', // Dietetics / nutrition — approx
            'MED FAMILIAR' => '419772000', // Family practice
            'MED GENERAL' => '394814009', // General practice
            'MED CLINICA' => '394807007', // Internal medicine
            'ENFERMERIA' => '722142008', // Nursing
            'APS' => '394814009',
        ];

        return $map[$nombre] ?? null;
    }

    private function seedActosAndPuentes(): void
    {
        if ($this->db->schema->getTableSchema('{{%actos_clinicos}}', true) === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $actos = [
            ['16310003', self::SNOMED, 'Diagnostic ultrasonography', 'imaging'],
            ['363680008', self::SNOMED, 'Radiographic imaging procedure', 'imaging'],
            ['71651007', self::SNOMED, 'Mammography', 'imaging'],
            ['15220000', self::SNOMED, 'Laboratory test', 'laboratory'],
            ['11429006', self::SNOMED, 'Consultation', 'consultation'],
            ['183515008', self::SNOMED, 'Referral to physician', 'referral'],
            ['91251008', self::SNOMED, 'Physical therapy procedure', 'therapy'],
        ];

        $actoIds = [];
        foreach ($actos as [$code, $system, $display, $category]) {
            $existing = (new Query())
                ->from('{{%actos_clinicos}}')
                ->where(['code' => $code, 'code_system' => $system])
                ->one($this->db);
            if ($existing) {
                $actoIds[$code] = (int) $existing['id'];
                continue;
            }
            $this->insert('{{%actos_clinicos}}', [
                'code' => $code,
                'code_system' => $system,
                'display' => $display,
                'fhir_category' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $actoIds[$code] = (int) $this->db->getLastInsertID();
        }

        if ($this->db->schema->getTableSchema('{{%linea_acto}}', true) === null
            || $this->db->schema->getTableSchema('{{%servicios}}', true) === null
        ) {
            return;
        }

        $servicios = (new Query())
            ->from('{{%servicios}}')
            ->select(['id_servicio', 'nombre', 'tipo'])
            ->all($this->db);

        foreach ($servicios as $s) {
            $idServicio = (int) $s['id_servicio'];
            $nombre = mb_strtoupper(trim((string) $s['nombre']), 'UTF-8');
            $tipo = (string) ($s['tipo'] ?? 'consulta');

            $links = [];
            if ($tipo === 'diagnostico' || str_contains($nombre, 'ECOGRAF')) {
                $links[] = ['16310003', str_contains($nombre, 'ECOGRAF')];
            }
            if ($tipo === 'diagnostico' || str_contains($nombre, 'RADIOLOG')) {
                $links[] = ['363680008', str_contains($nombre, 'RADIOLOG')];
            }
            if ($tipo === 'diagnostico' || str_contains($nombre, 'MAMOGRAF')) {
                $links[] = ['71651007', str_contains($nombre, 'MAMOGRAF')];
            }
            if ($tipo === 'laboratorio' || str_contains($nombre, 'LABORATOR')) {
                $links[] = ['15220000', true];
            }
            if (str_contains($nombre, 'KINESIO')) {
                $links[] = ['91251008', true];
            }
            if ($tipo === 'consulta') {
                $links[] = ['11429006', true];
                $links[] = ['183515008', false];
            }

            foreach ($links as [$code, $preferente]) {
                $idActo = $actoIds[$code] ?? null;
                if ($idActo === null) {
                    continue;
                }
                $exists = (new Query())
                    ->from('{{%linea_acto}}')
                    ->where([
                        'id_servicio' => $idServicio,
                        'id_acto' => $idActo,
                        'id_efector' => null,
                    ])
                    ->exists($this->db);
                if ($exists) {
                    continue;
                }
                $this->insert('{{%linea_acto}}', [
                    'id_servicio' => $idServicio,
                    'id_acto' => $idActo,
                    'id_efector' => null,
                    'preferente' => $preferente ? 1 : 0,
                ]);
            }
        }
    }
}
