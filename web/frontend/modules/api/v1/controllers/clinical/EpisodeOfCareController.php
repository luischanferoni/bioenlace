<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Service\EpisodeOfCareService;
use common\components\Clinical\Specialty\Inpatient\InpatientClinicalContext;
use common\components\Clinical\Specialty\Inpatient\InpatientClinicalQuery;
use common\models\Clinical\EpisodeOfCare;
use common\models\SegNivelInternacion;
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
    private EpisodeOfCareService $episodes;

    public function init()
    {
        parent::init();
        $this->query = new InpatientClinicalQuery();
        $this->episodes = new EpisodeOfCareService();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    public function actionByInternacion($internacionId)
    {
        $internacion = SegNivelInternacion::findOne((int) $internacionId);
        if ($internacion === null) {
            Yii::$app->response->statusCode = 404;

            return $this->clinicalError('Internación no encontrada', null, 404);
        }
        if (!$this->canAccessInternacion($internacion)) {
            Yii::$app->response->statusCode = 403;

            return $this->clinicalError('No tiene permiso para acceder a esta internación', null, 403);
        }

        $episode = $this->episodes->findActiveForInternacion((int) $internacion->id);
        if ($episode === null) {
            $episode = EpisodeOfCare::find()
                ->andWhere(['internacion_id' => (int) $internacion->id, 'deleted_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->one();
        }
        if ($episode === null) {
            return [
                'success' => true,
                'message' => 'Sin episodio clínico para la internación',
                'data' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'EpisodeOfCare de internación',
            'data' => $this->episodeSummary($episode),
        ];
    }

    public function actionClinicalBundle($id)
    {
        $episode = EpisodeOfCare::findOne((int) $id);
        if ($episode === null || $episode->deleted_at !== null) {
            Yii::$app->response->statusCode = 404;

            return $this->clinicalError('EpisodeOfCare no encontrado', null, 404);
        }
        if (!$this->canAccessEpisode($episode)) {
            Yii::$app->response->statusCode = 403;

            return $this->clinicalError('No tiene permiso para acceder a este episodio', null, 403);
        }

        $internacion = $episode->internacion_id
            ? SegNivelInternacion::findOne((int) $episode->internacion_id)
            : null;

        return [
            'success' => true,
            'message' => 'Bundle clínico de internación',
            'data' => $this->query->bundleForEpisode($episode, $internacion),
        ];
    }

    private function canAccessInternacion(SegNivelInternacion $internacion): bool
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
            $internacion = SegNivelInternacion::findOne((int) $episode->internacion_id);
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
