<?php

namespace common\models\Clinical;

use common\components\Clinical\Enum\EncounterStatus;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Efector;
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

    /** Paso de workflow para encounter finalizado (legacy paso 999). */
    const WORKFLOW_STEP_FINALIZED = 999;

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

    /** Persona del profesional que atiende (vía PES), para nombre en UI legacy. */
    public function getProfesionalPes(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona'])
            ->viaTable(ProfesionalEfectorServicio::tableName(), ['id' => 'id_profesional_efector_servicio']);
    }

    /**
     * Padre operativo (turno u otro contexto) para vistas que usaban `Consulta::getParent()`.
     */
    public function getParent(): \yii\db\ActiveQuery
    {
        if ($this->appointment_id !== null && (int) $this->appointment_id > 0) {
            return $this->hasOne(Turno::class, ['id_turnos' => 'appointment_id']);
        }

        $class = self::PARENT_CLASSES[$this->parent_type] ?? null;
        if ($class === Turno::class || $class === '\common\models\Turno') {
            return $this->hasOne(Turno::class, ['id_turnos' => 'parent_id']);
        }
        if ($class !== null && class_exists($class)) {
            return $this->hasOne($class, ['id' => 'parent_id']);
        }

        return $this->hasOne(Turno::class, ['id_turnos' => 'parent_id']);
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

    public function getAutofacturacion(): \yii\db\ActiveQuery
    {
        $fk = \common\models\sumar\Autofacturacion::legacyConsultaFkAttribute();

        return $this->hasOne(\common\models\sumar\Autofacturacion::class, [$fk => 'id']);
    }

    /** Alias para vistas legacy de autofacturación. */
    public function getId_consulta(): int
    {
        return (int) $this->id;
    }

    /** Alias para vistas legacy de autofacturación. */
    public function getPaciente(): \yii\db\ActiveQuery
    {
        return $this->getSubject();
    }

    /**
     * Prácticas / pedidos asociados (reemplazo de `practicasPostDiagnostico` en consulta legacy).
     *
     * @return ServiceRequest[]
     */
    public function getPracticasPostDiagnostico(): array
    {
        return $this->getServiceRequests()->all();
    }

    /** @return Condition[] */
    public function getDiagnosticos(): array
    {
        return $this->getConditions()->all();
    }

    /** Motivos codificados legacy (`consultas_motivos.id_consulta` = encounter id). */
    public function getMotivoConsulta(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\common\models\ConsultaMotivos::class, ['id_consulta' => 'id']);
    }

    public function getAtencionEnfermeria(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\common\models\ConsultaAtencionesEnfermeria::class, ['encounter_id' => 'id']);
    }

    /** Medicación legacy activa vinculada por encounter id en `id_consulta`. */
    public function getMedicamentos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\common\models\ConsultaMedicamentos::class, ['id_consulta' => 'id'])
            ->andOnCondition(['estado' => 'ACTIVO']);
    }

    /** Diagnósticos legacy para reporte farmacia (previo a FHIR). */
    public function getDiagnosticoConsultasLegacy(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\common\models\DiagnosticoConsulta::class, ['id_consulta' => 'id']);
    }

    public function getOdontologiaDiagnosticos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\common\models\ConsultaOdontologiaDiagnosticos::class, ['id_consulta' => 'id']);
    }

    public function getOdontologiaPracticas(): \yii\db\ActiveQuery
    {
        return $this->hasMany(\common\models\ConsultaOdontologiaPracticas::class, ['id_consulta' => 'id']);
    }

    public function isInProgress(): bool
    {
        return $this->status === EncounterStatus::IN_PROGRESS;
    }

    public static function getEfectorNombreById(int $encounterId): string
    {
        $encounter = static::findOne($encounterId);
        if ($encounter === null || (int) $encounter->efector_id <= 0) {
            return '';
        }
        $efector = Efector::findOne((int) $encounter->efector_id);

        return $efector !== null ? (string) $efector->nombre : '';
    }

    /**
     * Encounter de pase previo para servicio/efector (reemplazo de {@see Consulta::existeConsultaPasePrevio}).
     */
    public static function findPasePrevioEncounter(int $parentId, int $serviceId): ?self
    {
        $row = self::find()
            ->where([
                'parent_type' => self::PARENT_PASE_PREVIO,
                'parent_id' => $parentId,
                'service_id' => $serviceId,
            ])
            ->one();

        return $row instanceof self ? $row : null;
    }
}
