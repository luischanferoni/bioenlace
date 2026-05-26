<?php

namespace common\models;

use common\components\Clinical\Enum\RequestStatus;
use common\components\Clinical\Service\ReferralRequestService;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\snomed\SnomedProcedimientos;
use yii\data\ActiveDataProvider;
use Yii;

/**
 * Derivación / interconsulta — filas en `service_request` con `category = referral`.
 *
 * @property int $id
 * @property int $encounter_id Encounter solicitante (legacy `id_consulta_solicitante`)
 * @property int|null $target_efector_id Legacy `id_efector`
 * @property int|null $target_service_id Legacy `id_servicio`
 * @property string|null $referral_status Legacy `estado`
 * @property int|null $responded_encounter_id Legacy `id_respondido`
 * @property string|null $referral_kind Legacy `tipo`
 * @property string|null $request_kind Legacy `tipo_solicitud`
 * @property string|null $code Legacy `codigo`
 * @property string|null $note Legacy `indicaciones` (también en `display`)
 *
 * @property-read Encounter|null $encounter
 */
class ConsultaDerivaciones extends ServiceRequest
{
    public const CATEGORY = 'referral';

    public const ESTADO_EN_ESPERA = 'EN_ESPERA';
    public const ESTADO_CON_TURNO = 'CON_TURNO';
    public const ESTADO_RECHAZADA = 'RECHAZADA';
    public const ESTADO_RESUELTA = 'RESUELTA';

    public const PRACTICA = 'PRACTICA';
    public const INTERCONSULTA = 'INTERCONSULTA';

    public $select2_codigo;

    public static function find(): \yii\db\ActiveQuery
    {
        return parent::find()->andWhere([self::tableName() . '.category' => self::CATEGORY]);
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'target_efector_id', 'target_service_id'], 'required'],
            ['select2_codigo', 'each', 'rule' => ['string']],
            [['id_profesional_efector_servicio', 'responded_encounter_id'], 'integer'],
            [['referral_kind', 'request_kind', 'code', 'display', 'note', 'referral_status'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'target_service_id' => 'Servicio',
            'target_efector_id' => 'Efector',
            'code' => 'Código',
        ];
    }

    public function requeridosPrompt(): array
    {
        return ['Servicio'];
    }

    /** @deprecated Alias de {@see $encounter_id} */
    public function getId_consulta_solicitante(): ?int
    {
        return $this->encounter_id !== null ? (int) $this->encounter_id : null;
    }

    /** @deprecated Alias de {@see $encounter_id} */
    public function setId_consulta_solicitante($value): void
    {
        $this->encounter_id = $value !== null && $value !== '' ? (int) $value : null;
    }

    public function getId_efector(): ?int
    {
        return $this->target_efector_id !== null ? (int) $this->target_efector_id : null;
    }

    public function setId_efector($value): void
    {
        $this->target_efector_id = $value !== null && $value !== '' ? (int) $value : null;
    }

    public function getId_servicio(): ?int
    {
        return $this->target_service_id !== null ? (int) $this->target_service_id : null;
    }

    public function setId_servicio($value): void
    {
        $this->target_service_id = $value !== null && $value !== '' ? (int) $value : null;
    }

    public function getEstado(): ?string
    {
        return $this->referral_status;
    }

    public function setEstado($value): void
    {
        $this->referral_status = $value !== null && $value !== '' ? (string) $value : null;
    }

    public function getId_respondido(): ?int
    {
        return $this->responded_encounter_id !== null ? (int) $this->responded_encounter_id : null;
    }

    public function setId_respondido($value): void
    {
        $this->responded_encounter_id = $value !== null && $value !== '' ? (int) $value : null;
    }

    public function getTipo(): ?string
    {
        return $this->referral_kind;
    }

    public function setTipo($value): void
    {
        $this->referral_kind = $value !== null && $value !== '' ? (string) $value : null;
    }

    public function getTipo_solicitud(): ?string
    {
        return $this->request_kind;
    }

    public function setTipo_solicitud($value): void
    {
        $this->request_kind = $value !== null && $value !== '' ? (string) $value : null;
    }

    public function getCodigo(): ?string
    {
        return $this->code;
    }

    public function setCodigo($value): void
    {
        $this->code = $value !== null && $value !== '' ? (string) $value : null;
    }

    public function getIndicaciones(): ?string
    {
        return $this->note ?? $this->display;
    }

    public function setIndicaciones($value): void
    {
        $this->note = $value !== null && $value !== '' ? (string) $value : null;
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->category = self::CATEGORY;
        if (empty($this->status)) {
            $this->status = RequestStatus::ACTIVE;
        }
        if (empty($this->intent)) {
            $this->intent = 'order';
        }
        if ($insert && empty($this->referral_status)) {
            $this->referral_status = self::ESTADO_EN_ESPERA;
        }
        if ((int) ($this->subject_persona_id ?? 0) <= 0 && (int) ($this->encounter_id ?? 0) > 0) {
            $enc = Encounter::findOne((int) $this->encounter_id);
            if ($enc !== null) {
                $this->subject_persona_id = (int) $enc->subject_persona_id;
            }
        }

        return true;
    }

    public function getServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Servicio::class, ['id_servicio' => 'target_service_id']);
    }

    public function getEfector(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'target_efector_id']);
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    /** @deprecated use {@see getEncounter()} */
    public function getConsulta(): \yii\db\ActiveQuery
    {
        return $this->getEncounter();
    }

    public static function getDerivacionesPorPersona(
        int $personaId,
        int $efectorId,
        int $serviceId,
        string $estado
    ): array {
        return self::find()
            ->alias('sr')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = sr.encounter_id')
            ->where([
                'sr.referral_status' => $estado,
                'sr.target_efector_id' => $efectorId,
                'sr.target_service_id' => $serviceId,
                'enc.subject_persona_id' => $personaId,
            ])
            ->andWhere(['sr.deleted_at' => null])
            ->all();
    }

    public static function getDerivacionesRechazadaPorPersona(
        int $encounterId,
        int $personaId,
        int $efectorId,
        int $serviceId,
        string $estado
    ): array {
        return self::find()
            ->alias('sr')
            ->innerJoin(['enc' => Encounter::tableName()], 'enc.id = sr.encounter_id')
            ->where([
                'sr.referral_status' => $estado,
                'sr.target_efector_id' => $efectorId,
                'sr.target_service_id' => $serviceId,
                'sr.responded_encounter_id' => $encounterId,
                'enc.subject_persona_id' => $personaId,
            ])
            ->andWhere(['sr.deleted_at' => null])
            ->all();
    }

    public function getPracticasSolicitadasPorConsulta(int $encounterId): array
    {
        return self::findAll(['encounter_id' => $encounterId]);
    }

    public static function getPracticaSolicitadasPorIdConsultaSolicitada(int $encounterId): ?self
    {
        return self::findOne(['encounter_id' => $encounterId]);
    }

    public function getCodigoSnomed(): \yii\db\ActiveQuery
    {
        return $this->hasOne(SnomedProcedimientos::class, ['conceptId' => 'code']);
    }

    public function getProfesionalPersona(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona'])
            ->viaTable(ProfesionalEfectorServicio::tableName(), ['id' => 'id_profesional_efector_servicio']);
    }

    public function getProfesionalEfectorServicio(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ProfesionalEfectorServicio::class, ['id' => 'id_profesional_efector_servicio']);
    }

    public static function hardDeleteGrupo(int $encounterId, array $ids): void
    {
        if ($ids === [] || $encounterId <= 0) {
            return;
        }
        self::hardDeleteAll([
            'and',
            ['in', 'id', $ids],
            ['encounter_id' => $encounterId],
            ['category' => self::CATEGORY],
        ]);
    }

    public function porEfectorPorServicio(int $efectorId, $serviceId): ActiveDataProvider
    {
        $query = self::find()
            ->where([
                'target_efector_id' => $efectorId,
                'referral_status' => self::ESTADO_EN_ESPERA,
            ])
            ->andWhere(['deleted_at' => null]);

        if ($serviceId) {
            $query->andWhere(['target_service_id' => (int) $serviceId]);
        }

        return new ActiveDataProvider(['query' => $query]);
    }

    public function porReferencia(int $efectorId): array
    {
        return self::find()
            ->where([
                'target_efector_id' => $efectorId,
                'referral_status' => self::ESTADO_EN_ESPERA,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['target_service_id' => SORT_ASC])
            ->all();
    }

    /**
     * @param int[] $serviceIds
     * @return self[]
     */
    public static function getDerivacionesActivasPorPacientePorServiciosPorEfector(
        int $personaId,
        array $serviceIds,
        int $efectorId
    ): array {
        return ReferralRequestService::findPendingForPersonEfectorServices($personaId, $serviceIds, $efectorId);
    }
}
