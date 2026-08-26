<?php

use yii\db\Migration;

/**
 * CTA de artículos informativos: lista de intent_id (comma-separated).
 * Visibilidad paciente ≈ RBAC de al menos un intent; overrides provincia/efector heredan del producto.
 */
class m260825_120000_info_content_intent_ids extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $this->addColumn(self::TABLE, 'intent_ids', $this->string(500)->null()->after('keywords')
            ->comment('intent_id separados por coma (CTA); vacío = sin botón / hereda producto'));
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }
        $this->dropColumn(self::TABLE, 'intent_ids');
    }
}
