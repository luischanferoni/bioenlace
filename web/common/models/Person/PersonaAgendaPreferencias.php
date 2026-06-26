<?php

namespace common\models\Person;

use common\models\Scheduling\Turno;
use yii\db\ActiveRecord;

/**
 * Preferencias de agenda del paciente (auto-reserva en resolución, franjas, días).
 *
 * @property int $id_persona
 * @property bool $auto_reserva_resolucion
 * @property string|null $franjas_json
 * @property string|null $dias_semana_json
 * @property string|null $tipo_atencion_preferido
 * @property bool $mismo_pes_prioritario
 * @property string $created_at
 * @property string $updated_at
 */
class PersonaAgendaPreferencias extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%persona_agenda_preferencias}}';
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'created_at', 'updated_at'], 'required'],
            [['id_persona'], 'integer'],
            [['auto_reserva_resolucion', 'mismo_pes_prioritario'], 'boolean'],
            [['franjas_json', 'dias_semana_json'], 'string'],
            [['tipo_atencion_preferido'], 'in', 'range' => [
                Turno::TIPO_ATENCION_PRESENCIAL,
                Turno::TIPO_ATENCION_TELECONSULTA,
            ], 'skipOnEmpty' => true],
        ];
    }

    /**
     * @return list<string>
     */
    public function franjasList(): array
    {
        return self::decodeStringList($this->franjas_json);
    }

    /**
     * @return list<int>
     */
    public function diasSemanaList(): array
    {
        $raw = $this->dias_semana_json !== null && $this->dias_semana_json !== ''
            ? json_decode((string) $this->dias_semana_json, true)
            : [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $d) {
            $n = (int) $d;
            if ($n >= 1 && $n <= 7) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'auto_reserva_resolucion' => (bool) $this->auto_reserva_resolucion,
            'franjas' => $this->franjasList(),
            'dias_semana' => $this->diasSemanaList(),
            'tipo_atencion_preferido' => $this->tipo_atencion_preferido,
            'mismo_pes_prioritario' => (bool) $this->mismo_pes_prioritario,
        ];
    }

    /**
     * @return list<string>
     */
    private static function decodeStringList(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $raw = json_decode($json, true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            $s = strtoupper(trim((string) $item));
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }
}
