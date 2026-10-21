<?php

namespace common\models\Clinical;

use common\models\Organization\Efector;
use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Organization\Servicio;
use common\models\Person\Persona;

use common\components\Domain\Clinical\Encounter\Domain\RequestStatus;
use common\components\Domain\Clinical\CarePlan\Application\UseCase\CreateReferralRequest;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Terminology\Application\SnomedProcedimientos;
use yii\data\ActiveDataProvider;
use Yii;

/**
 * Referral requests stored in `service_request` with `category = referral`.
 *
 * @property int $id
 * @property int $encounter_id Encounter solicitante
 * @property int|null $target_efector_id
 * @property int|null $target_service_id
 * @property string|null $referral_status
 * @property int|null $responded_encounter_id
 * @property string|null $referral_kind
 * @property string|null $request_kind
 * @property string|null $code
 * @property string|null $note
 *
 * @property-read Encounter|null $encounter
 */
class EncounterReferralRequest extends ServiceRequest
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
        return \common\models\Clinical\Input\DerivacionInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: \common\models\Clinical\Input\DerivacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = \common\components\Domain\Clinical\Capture\Domain\RowContract\DerivacionRowContract::assess(
            $row,
            'Derivaciones',
            0,
            new \common\components\Domain\Clinical\Capture\Infrastructure\CareRequest\YiiDerivacionRowSupportAdapter()
        );

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => \common\models\Clinical\Input\DerivacionInput::fromExtractedRow($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return \common\components\Domain\Clinical\Capture\Domain\RowContract\DerivacionRowContract::applyResolution(
            $row,
            $field,
            $value,
            new \common\components\Domain\Clinical\Capture\Infrastructure\CareRequest\YiiDerivacionRowSupportAdapter()
        );
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

    public function getPracticasSolicitadasPorEncounter(int $encounterId): array
    {
        return self::findAll(['encounter_id' => $encounterId]);
    }

    public static function getPracticaSolicitadaPorEncounterId(int $encounterId): ?self
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
        return CreateReferralRequest::findPendingForPersonEfectorServices($personaId, $serviceIds, $efectorId);
    }
}
