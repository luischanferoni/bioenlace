<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Tutela y delegación se hacen en Configuración → Representación.
 * El artículo de representación guía esa pantalla; estos intents solo la duplicaban.
 */
class m261001_140000_drop_representacion_paciente_intents extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    private const PERMISSION_TYPE = 2;

    /** @var list<string> */
    private const INTENT_IDS = [
        'personas.vincular-menor-flow',
        'personas.designar-representante-flow',
    ];

    /** @var array<string, string> */
    private const BODY_REPLACEMENTS = [
        'Decile al asistente "quiero vincular a mi hijo/a" o usá el botón "Vincular menor".' =>
            'En el celular entrá a Configuración → Representación y pedí vincular al menor.',
        'Decile al asistente "quiero designar un representante" o usá el botón correspondiente.' =>
            'En el celular entrá a Configuración → Representación y designá al representante.',
    ];

    public function safeUp(): void
    {
        $this->clearArticleCtas();
        $this->dropIntentPermissions();
    }

    public function safeDown(): void
    {
        $this->restoreArticleCtas();
        $this->restoreIntentPermissions();
    }

    private function clearArticleCtas(): void
    {
        $row = $this->productoRow();
        if ($row === null) {
            return;
        }

        $body = (string) ($row['body'] ?? '');
        foreach (self::BODY_REPLACEMENTS as $from => $to) {
            $body = str_replace($from, $to, $body);
        }

        $this->update(self::TABLE, [
            'intent_ids' => null,
            'body' => $body,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    private function restoreArticleCtas(): void
    {
        $row = $this->productoRow();
        if ($row === null) {
            return;
        }

        $body = (string) ($row['body'] ?? '');
        foreach (self::BODY_REPLACEMENTS as $from => $to) {
            $body = str_replace($to, $from, $body);
        }

        $this->update(self::TABLE, [
            'intent_ids' => implode(',', self::INTENT_IDS),
            'body' => $body,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $row['id']]);
    }

    private function dropIntentPermissions(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $this->db->createCommand()->delete($childTable, ['child' => self::INTENT_IDS])->execute();
        $this->db->createCommand()->delete($authItem, [
            'name' => self::INTENT_IDS,
            'type' => self::PERMISSION_TYPE,
        ])->execute();
    }

    private function restoreIntentPermissions(): void
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null
            || $this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        $now = time();
        $parentRoute = '/api/turnos/crear-como-paciente';
        $parents = (new Query())
            ->select('parent')
            ->from($childTable)
            ->where(['child' => $parentRoute])
            ->column($this->db);

        foreach (self::INTENT_IDS as $intentId) {
            if (!(new Query())->from($authItem)->where(['name' => $intentId])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $intentId,
                    'type' => self::PERMISSION_TYPE,
                    'description' => 'Intent ' . $intentId,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            foreach ($parents as $parentRole) {
                if (!is_string($parentRole) || $parentRole === '') {
                    continue;
                }
                if ((new Query())->from($childTable)->where([
                    'parent' => $parentRole,
                    'child' => $intentId,
                ])->exists($this->db)) {
                    continue;
                }
                $this->db->createCommand()->insert($childTable, [
                    'parent' => $parentRole,
                    'child' => $intentId,
                ])->execute();
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productoRow(): ?array
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return null;
        }

        $row = (new Query())
            ->from(self::TABLE)
            ->where(['topic' => 'representacion', 'scope' => 'producto'])
            ->one($this->db);

        return is_array($row) ? $row : null;
    }
}
