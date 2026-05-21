<?php

namespace common\models\Clinical;

use common\components\Clinical\Enum\EncounterStatus;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Turno;
use yii\db\ActiveRecord;

/**
 * FHIR Encounter — tabla `encounter`.
 *
 * @property int $id
 * @property int $subject_persona_id
 * @property int|null $appointment_id
 * @property string $encounter_class
 * @property string $status
 * @property string|null $period_start
 * @property string|null $period_end
 * @property int|null $service_id
 * @property int|null $efector_id
 * @property int|null $id_profesional_efector_servicio
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property int|null $workflow_step
 * @property string|null $reason_text
 * @property string|null $note
 */
class Encounter extends ActiveRecord
{
    use ClinicalRecordTrait;

    const ENCOUNTER_CLASS_IMP = 'IMP';
    const ENCOUNTER_CLASS_AMB = 'AMB';
    const ENCOUNTER_CLASS_OBSENC = 'OBSENC';
    const ENCOUNTER_CLASS_EMER = 'EMER';
    const ENCOUNTER_CLASS_VR = 'VR';
    const ENCOUNTER_CLASS_HH = 'HH';

    const PARENT_TURNO = 'TURNO';
    const PARENT_DERIVACION = 'DERIVACION';
    const PARENT_INTERNACION = 'INTERNACION';
    const PARENT_GENERICO_AMB = 'GENERICO_AMB';
    const PARENT_GENERICO_EMER = 'GENERICO_EMER';
    const PARENT_GUARDIA = 'GUARDIA';
    const PARENT_PASE_PREVIO = 'PASE_PREVIO';
    const PARENT_ENCUESTA_PARCHES = 'ENCUESTA_PARCHES';
    const PARENT_CIRUGIA = 'CIRUGIA';

    const PARENT_CLASSES = [
        self::PARENT_TURNO => '\common\models\Turno',
        self::PARENT_DERIVACION => '\common\models\ConsultaDerivaciones',
        self::PARENT_INTERNACION => '\common\models\SegNivelInternacion',
        self::PARENT_GENERICO_AMB => '\common\models\GenericoAMB',
        self::PARENT_GENERICO_EMER => '\common\models\GenericoEMER',
        self::PARENT_GUARDIA => '\common\models\Guardia',
        self::PARENT_PASE_PREVIO => '\common\models\ServiciosEfector',
        self::PARENT_ENCUESTA_PARCHES => '\common\models\EncuestaParchesMamarios',
        self::PARENT_CIRUGIA => '\common\models\Cirugia',
    ];

    public static function tableName(): string
    {
        return 'encounter';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'encounter_class', 'status'], 'required'],
            [
                [
                    'subject_persona_id',
                    'appointment_id',
                    'service_id',
                    'efector_id',
                    'id_profesional_efector_servicio',
                    'parent_id',
                    'workflow_step',
                ],
                'integer',
            ],
            [['encounter_class'], 'string', 'max' => 10],
            [['status'], 'string', 'max' => 32],
            [['parent_type'], 'string', 'max' => 128],
            [['period_start', 'period_end', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['reason_text', 'note'], 'string'],
        ];
    }

    public function getSubject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }

    public function getAppointment(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Turno::class, ['id_turnos' => 'appointment_id']);
    }

    public function getProfesionalEfectorServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    public function getConditions(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Condition::class, ['encounter_id' => 'id']);
    }

    public function getMedicationRequests(): \yii\db\ActiveQuery
    {
        return $this->hasMany(MedicationRequest::class, ['encounter_id' => 'id']);
    }

    public function getServiceRequests(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ServiceRequest::class, ['encounter_id' => 'id']);
    }

    public function getCarePlans(): \yii\db\ActiveQuery
    {
        return $this->hasMany(CarePlan::class, ['encounter_id' => 'id']);
    }

    public function isInProgress(): bool
    {
        return $this->status === EncounterStatus::IN_PROGRESS;
    }
}
