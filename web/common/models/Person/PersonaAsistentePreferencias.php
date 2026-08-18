<?php

namespace common\models\Person;

use yii\db\ActiveRecord;

/**
 * Preferencias del paciente sobre el asistente conversacional.
 *
 * @property int $id_persona
 * @property bool $usa_resumen_hc_en_asistente
 * @property string $created_at
 * @property string $updated_at
 */
class PersonaAsistentePreferencias extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%persona_asistente_preferencias}}';
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'created_at', 'updated_at'], 'required'],
            [['id_persona'], 'integer'],
            [['usa_resumen_hc_en_asistente'], 'boolean'],
        ];
    }

    /**
     * @return array{usa_resumen_hc_en_asistente: bool}
     */
    public function toApiArray(): array
    {
        return [
            'usa_resumen_hc_en_asistente' => (bool) $this->usa_resumen_hc_en_asistente,
        ];
    }
}
