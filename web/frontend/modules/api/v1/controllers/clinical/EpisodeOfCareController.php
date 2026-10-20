<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Encounter\Application\UseCase\ManageEpisodeOfCare;
use common\components\Domain\Clinical\Inpatient\Domain\InpatientClinicalContext;
use common\components\Domain\Clinical\Inpatient\Domain\InpatientClinicalQuery;
use common\models\Clinical\EpisodeOfCare;
use common\models\Clinical\InpatientStay;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Episodio de internación (EpisodeOfCare) y bundle clínico inpatient.
 *
 * GET /api/v1/clinical/episode-of-care/by-internacion/<internacionId>
 * GET /api/v1/clinical/episode-of-care/<id>/clinical-bundle
 */
class EpisodeOfCareController extends BaseController
{
    use ClinicalAccessTrait;

    private InpatientClinicalQuery $query;
    private ManageEpisodeOfCare $episodes;

    public function init()
    {
        parent::init();
        $this->query = new InpatientClinicalQuery();
        $this->episodes = new ManageEpisodeOfCare();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    public function actionByInternacion($internacionId)
    {
        $internacion = InpatientStay::findOne((int) $internacionId);
        if ($internacion === null) {
            return $this->fail('inpatient_not_found');
        }
        if (!$this->canAccessInternacion($internacion)) {
            return $this->fail('inpatient_forbidden');
        }

        $episode = $this->episodes->findActiveForInpatientStay((int) $internacion->id);
        if ($episode === null) {
            $episode = EpisodeOfCare::find()
                ->andWhere(['internacion_id' => (int) $internacion->id, 'deleted_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->one();
        }
        if ($episode === null) {
            return $this->ok(null);
        }

        return $this->ok($this->episodeSummary($episode));
    }

    public function actionClinicalBundle($id)
    {
        $episode = EpisodeOfCare::findOne((int) $id);
        if ($episode === null || $episode->deleted_at !== null) {
            return $this->fail('episode_not_found');
        }
        if (!$this->canAccessEpisode($episode)) {
            return $this->fail('episode_forbidden');
        }

        $internacion = $episode->internacion_id
            ? InpatientStay::findOne((int) $episode->internacion_id)
            : null;

        return $this->ok($this->query->bundleForEpisode($episode, $internacion));
    }

    private function canAccessInternacion(InpatientStay $internacion): bool
    {
        return $this->staffCanAccessInternacion($internacion);
    }

    private function canAccessEpisode(EpisodeOfCare $episode): bool
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $episode->subject_persona_id === $idPersona) {
            return true;
        }
        if ($episode->internacion_id) {
            $internacion = InpatientStay::findOne((int) $episode->internacion_id);
            if ($internacion !== null) {
                return $this->canAccessInternacion($internacion);
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function episodeSummary(EpisodeOfCare $episode): array
    {
        $encounter = $episode->internacion_id
            ? InpatientClinicalContext::findOpenInpatientEncounter((int) $episode->internacion_id)
            : null;

        return [
            'resourceType' => 'EpisodeOfCare',
            'id' => (int) $episode->id,
            'subjectPersonaId' => (int) $episode->subject_persona_id,
            'status' => $episode->status,
            'typeCode' => $episode->type_code,
            'internacionId' => $episode->internacion_id !== null ? (int) $episode->internacion_id : null,
            'encounterId' => $encounter ? (int) $encounter->id : null,
            'periodStart' => $episode->period_start,
            'periodEnd' => $episode->period_end,
            'title' => $episode->title,
        ];
    }
}
