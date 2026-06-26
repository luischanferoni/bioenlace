<?php

namespace common\models\Person;

use common\models\Provincia;
use yii\db\ActiveRecord;

/**
 * Contexto operativo persistente del paciente (app móvil / web paciente).
 *
 * @property int $id_persona
 * @property string $sector_salud PUBLICO|PRIVADO
 * @property int|null $id_provincia_contexto
 * @property string $domicilio_estado PENDIENTE|VERIFICADO|REQUIERE_PROVINCIA_MANUAL
 * @property string $domicilio_verificacion_inicio
 * @property string|null $domicilio_ultimo_intento
 * @property int $domicilio_intentos
 * @property bool $provincia_contexto_manual
 * @property string $created_at
 * @property string $updated_at
 */
class PersonaPacienteContexto extends ActiveRecord
{
    public const SECTOR_SALUD_PUBLICO = 'PUBLICO';

    public const SECTOR_SALUD_PRIVADO = 'PRIVADO';

    public const DOMICILIO_PENDIENTE = 'PENDIENTE';

    public const DOMICILIO_VERIFICADO = 'VERIFICADO';

    public const DOMICILIO_REQUIERE_PROVINCIA_MANUAL = 'REQUIERE_PROVINCIA_MANUAL';

    public static function tableName(): string
    {
        return '{{%persona_paciente_contexto}}';
    }

    /**
     * @return list<string>
     */
    public static function sectorSaludValues(): array
    {
        return [self::SECTOR_SALUD_PUBLICO, self::SECTOR_SALUD_PRIVADO];
    }

    /**
     * @return list<string>
     */
    public static function domicilioEstadoValues(): array
    {
        return [
            self::DOMICILIO_PENDIENTE,
            self::DOMICILIO_VERIFICADO,
            self::DOMICILIO_REQUIERE_PROVINCIA_MANUAL,
        ];
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'sector_salud', 'domicilio_estado', 'domicilio_verificacion_inicio', 'created_at', 'updated_at'], 'required'],
            [['id_persona', 'id_provincia_contexto', 'domicilio_intentos'], 'integer'],
            [['domicilio_verificacion_inicio', 'domicilio_ultimo_intento', 'created_at', 'updated_at'], 'safe'],
            [['provincia_contexto_manual'], 'boolean'],
            [['sector_salud'], 'in', 'range' => self::sectorSaludValues()],
            [['domicilio_estado'], 'in', 'range' => self::domicilioEstadoValues()],
        ];
    }

    public function getProvinciaContexto()
    {
        return $this->hasOne(Provincia::class, ['id_provincia' => 'id_provincia_contexto']);
    }

    public function tieneProvinciaOperativa(): bool
    {
        return $this->id_provincia_contexto !== null && (int) $this->id_provincia_contexto > 0;
    }

    public function puedeOperarApp(): bool
    {
        return $this->tieneProvinciaOperativa();
    }
}
