<?php

use yii\db\Migration;

/**
 * Definiciones de campos editables por grupo (fuente única de esquema de formulario DataAccess).
 */
class m260608_180000_data_access_attribute_field_table extends Migration
{
    private const TABLE = '{{%data_access_attribute_field}}';

    public function safeUp()
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey()->unsigned(),
            'entity_group_key' => $this->string(128)->notNull(),
            'field_name' => $this->string(64)->notNull(),
            'field_type' => $this->string(32)->notNull()->comment('text, date, enum, hidden, custom_widget'),
            'label' => $this->string(255)->null(),
            'config_json' => $this->json()->null()->comment('options, layout, widget_id, value_fields, assets, form, source, …'),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'active' => $this->tinyInteger(1)->notNull()->defaultValue(1),
        ]);

        $this->createIndex(
            'uq_data_access_attribute_field_group_name',
            self::TABLE,
            ['entity_group_key', 'field_name'],
            true
        );
        $this->createIndex('idx_data_access_attribute_field_group', self::TABLE, 'entity_group_key');

        $this->seedCanonicalFields();
    }

    public function safeDown()
    {
        $this->dropTable(self::TABLE);
    }

    private function seedCanonicalFields(): void
    {
        $order = 0;
        foreach ($this->personaIdentidadBasicaFields() as $row) {
            $this->insertField('Persona.identidad_basica', $row, $order++);
        }

        $order = 0;
        foreach ($this->agendaHorariosFields() as $row) {
            $this->insertField('ProfesionalEfectorServicioAgenda.configuracion', $row, $order++);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertField(string $groupKey, array $row, int $sortOrder): void
    {
        $config = $row['config'] ?? null;
        unset($row['config']);
        $this->insert(self::TABLE, [
            'entity_group_key' => $groupKey,
            'field_name' => $row['name'],
            'field_type' => $row['type'],
            'label' => $row['label'] ?? null,
            'config_json' => $config === null ? null : json_encode($config, JSON_UNESCAPED_UNICODE),
            'sort_order' => $sortOrder,
            'active' => 1,
        ]);
    }

    /**
     * @return list<array{name: string, type: string, label?: string, config?: array<string, mixed>}>
     */
    private function personaIdentidadBasicaFields(): array
    {
        return [
            ['name' => 'nombre', 'type' => 'text', 'label' => 'Nombre', 'config' => ['required' => true]],
            ['name' => 'apellido', 'type' => 'text', 'label' => 'Apellido', 'config' => ['required' => true]],
            ['name' => 'otro_nombre', 'type' => 'text', 'label' => 'Otro nombre'],
            ['name' => 'otro_apellido', 'type' => 'text', 'label' => 'Otro apellido'],
        ];
    }

    /**
     * Campos alineados con configurar-agenda.json (+ contexto de edición dispersa).
     *
     * @return list<array{name: string, type: string, label?: string, config?: array<string, mixed>}>
     */
    private function agendaHorariosFields(): array
    {
        $jsonPath = dirname(__DIR__, 2)
            . '/frontend/modules/api/v1/views/json/scheduling/profesional-agenda/configurar-agenda.json';
        if (!is_file($jsonPath)) {
            throw new \RuntimeException('No se encontró configurar-agenda.json para seed de atributos.');
        }

        $parsed = json_decode((string) file_get_contents($jsonPath), true);
        $fields = null;
        foreach ($parsed['blocks'] ?? [] as $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                continue;
            }
            if (is_array($block['fields'] ?? null)) {
                $fields = $block['fields'];
                break;
            }
        }
        if (!is_array($fields)) {
            throw new \RuntimeException('configurar-agenda.json sin bloque fields.');
        }

        $out = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $uiType = trim((string) ($field['type'] ?? 'text'));
            $type = $uiType === 'select' ? 'enum' : $uiType;
            $config = $field;
            unset($config['name'], $config['label'], $config['type']);
            if ($type === 'hidden') {
                $config['source'] = 'context';
                $config['context_key'] = $name;
                if ($name !== 'id_efector') {
                    $config['include_in_submit'] = $config['include_in_submit'] ?? true;
                }
            }
            if ($type === 'custom_widget') {
                $config['include_in_submit'] = $config['include_in_submit'] ?? false;
            }
            $row = [
                'name' => $name,
                'type' => $type,
                'label' => trim((string) ($field['label'] ?? '')) ?: null,
            ];
            if ($config !== []) {
                $row['config'] = $config;
            }
            $out[] = $row;
        }

        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $dayField) {
            $out[] = [
                'name' => $dayField,
                'type' => 'text',
                'config' => ['form' => false],
            ];
        }

        return $out;
    }
}
