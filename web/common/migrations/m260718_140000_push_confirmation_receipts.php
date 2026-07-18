<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Correlación de notificaciones push + interacciones DELIVERED/OPENED.
 * Identidad de dispositivo push por `push_token` único (sin depender de device_id).
 */
class m260718_140000_push_confirmation_receipts extends Migration
{
    private const NOTIF_ROUTE = '/api/notificaciones/registrar-interaccion-push-propia';
    private const LISTAR_ROUTE = '/api/notificaciones/listar';

    public function safeUp()
    {
        $this->extendPersonaNotificacion();
        $this->createInteraccionTable();
        $this->normalizeUserDevicePushToken();
        $this->ensureReceiptRoute();
    }

    public function safeDown()
    {
        $this->removeReceiptRoute();
        $interaccion = '{{%persona_notificacion_interaccion}}';
        if ($this->db->schema->getTableSchema($interaccion, true) !== null) {
            $this->dropTable($interaccion);
        }
        $notif = '{{%persona_notificacion}}';
        $schema = $this->db->schema->getTableSchema($notif, true);
        if ($schema !== null) {
            foreach (['context_json', 'context_handler_id', 'idempotency_key', 'public_ref'] as $col) {
                if (isset($schema->columns[$col])) {
                    $this->dropColumn($notif, $col);
                }
            }
        }
        $devices = '{{%user_device}}';
        if ($this->indexExists($devices, 'uq_user_device_push_token')) {
            $this->dropIndex('uq_user_device_push_token', $devices);
        }
    }

    private function extendPersonaNotificacion(): void
    {
        $table = '{{%persona_notificacion}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null) {
            return;
        }
        $columns = [
            'public_ref' => $this->string(64)->null(),
            'idempotency_key' => $this->string(191)->null(),
            'context_handler_id' => $this->string(128)->null(),
            'context_json' => $this->json()->null(),
        ];
        foreach ($columns as $name => $definition) {
            if (!isset($schema->columns[$name])) {
                $this->addColumn($table, $name, $definition);
            }
        }
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema !== null && isset($schema->columns['public_ref'])) {
            $rows = (new Query())->from($table)->select(['id'])->where(['public_ref' => null])->all($this->db);
            foreach ($rows as $row) {
                $this->update($table, ['public_ref' => bin2hex(random_bytes(16))], ['id' => (int) $row['id']]);
            }
            if (!$this->indexExists($table, 'uq_persona_notificacion_public_ref')) {
                $this->createIndex('uq_persona_notificacion_public_ref', $table, 'public_ref', true);
            }
        }
        if ($schema !== null && isset($schema->columns['idempotency_key'])
            && !$this->indexExists($table, 'uq_persona_notificacion_idempotency')) {
            $this->createIndex('uq_persona_notificacion_idempotency', $table, 'idempotency_key', true);
        }
        if ($schema !== null && isset($schema->columns['context_handler_id'])
            && !$this->indexExists($table, 'ix_persona_notificacion_handler')) {
            $this->createIndex('ix_persona_notificacion_handler', $table, ['context_handler_id', 'id_persona']);
        }
    }

    private function createInteraccionTable(): void
    {
        $table = '{{%persona_notificacion_interaccion}}';
        if ($this->db->schema->getTableSchema($table, true) !== null) {
            return;
        }
        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'id_persona_notificacion' => $this->integer()->notNull(),
            'id_persona' => $this->integer()->notNull(),
            'interaction_type' => $this->string(32)->notNull(),
            'client_event_id' => $this->string(64)->notNull(),
            'source' => $this->string(64)->null(),
            'provider_message_id' => $this->string(191)->null(),
            'occurred_at' => $this->dateTime()->notNull(),
            'meta_json' => $this->json()->null(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex(
            'uq_persona_notif_interaccion_client',
            $table,
            ['id_persona_notificacion', 'interaction_type', 'client_event_id'],
            true
        );
        $this->createIndex(
            'ix_persona_notif_interaccion_persona',
            $table,
            ['id_persona', 'interaction_type', 'occurred_at']
        );
        $notif = $this->db->schema->getTableSchema('{{%persona_notificacion}}', true);
        if ($notif !== null) {
            $this->addForeignKey(
                'fk_persona_notif_interaccion_notif',
                $table,
                'id_persona_notificacion',
                '{{%persona_notificacion}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    private function normalizeUserDevicePushToken(): void
    {
        $table = '{{%user_device}}';
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['push_token'])) {
            return;
        }

        // Vaciar strings vacíos a NULL para permitir UNIQUE.
        $this->update($table, ['push_token' => null], ['push_token' => '']);

        $tokens = (new Query())
            ->from($table)
            ->select(['push_token'])
            ->where(['not', ['push_token' => null]])
            ->andWhere(['<>', 'push_token', ''])
            ->groupBy(['push_token'])
            ->having('COUNT(*) > 1')
            ->column($this->db);

        foreach ($tokens as $token) {
            $rows = (new Query())
                ->from($table)
                ->select(['id', 'is_active', 'updated_at', 'created_at'])
                ->where(['push_token' => $token])
                ->orderBy(['is_active' => SORT_DESC, 'id' => SORT_DESC])
                ->all($this->db);
            if (count($rows) < 2) {
                continue;
            }
            $keepId = (int) $rows[0]['id'];
            $dropIds = [];
            foreach (array_slice($rows, 1) as $row) {
                $dropIds[] = (int) $row['id'];
            }
            if ($dropIds !== []) {
                $this->delete($table, ['id' => $dropIds]);
            }
            $this->update($table, ['is_active' => 1], ['id' => $keepId]);
        }

        if (!$this->indexExists($table, 'uq_user_device_push_token')) {
            $this->createIndex('uq_user_device_push_token', $table, 'push_token', true);
        }
    }

    private function ensureReceiptRoute(): void
    {
        $items = '{{%auth_item}}';
        $children = '{{%auth_item_child}}';
        if ($this->db->schema->getTableSchema($items, true) === null
            || $this->db->schema->getTableSchema($children, true) === null) {
            return;
        }
        if (!(new Query())->from($items)->where(['name' => self::NOTIF_ROUTE])->exists($this->db)) {
            $now = time();
            $this->insert($items, [
                'name' => self::NOTIF_ROUTE,
                'type' => 3,
                'description' => 'Registrar interacción push propia (entrega/apertura)',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $parents = (new Query())->select('parent')->from($children)
            ->where(['child' => self::LISTAR_ROUTE])->column($this->db);
        foreach ($parents as $parent) {
            if (!(new Query())->from($children)->where([
                'parent' => $parent,
                'child' => self::NOTIF_ROUTE,
            ])->exists($this->db)) {
                $this->insert($children, ['parent' => $parent, 'child' => self::NOTIF_ROUTE]);
            }
        }
    }

    private function removeReceiptRoute(): void
    {
        if ($this->db->schema->getTableSchema('{{%auth_item}}', true) === null) {
            return;
        }
        $this->delete('{{%auth_item_child}}', ['child' => self::NOTIF_ROUTE]);
        $this->delete('{{%auth_item}}', ['name' => self::NOTIF_ROUTE]);
    }

    private function indexExists(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($raw, true);

        return isset($indexes[$name]);
    }
}
