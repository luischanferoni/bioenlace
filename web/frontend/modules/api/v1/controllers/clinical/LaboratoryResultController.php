<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Laboratory\Service\LaboratoryIngestService;
use common\components\Clinical\Laboratory\Service\LaboratoryResultQueryService;
use common\components\Ui\UiScreenService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Resultados de laboratorio (ingesta pull + lectura).
 *
 * GET  /api/v1/clinical/laboratory-results/mis-resultados
 * GET|POST /api/v1/clinical/laboratory-results/mis-resultados-como-paciente (UI JSON)
 * POST /api/v1/clinical/laboratory-results/sincronizar
 * GET|POST /api/v1/clinical/laboratory-results/sincronizar-como-paciente (UI JSON)
 * GET  /api/v1/clinical/encounter/<encounterId>/laboratory-results
 */
class LaboratoryResultController extends BaseController
{
    use ClinicalAccessTrait;

    private LaboratoryResultQueryService $query;
    private LaboratoryIngestService $ingest;

    public function init()
    {
        parent::init();
        $this->query = new LaboratoryResultQueryService();
        $this->ingest = new LaboratoryIngestService();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * Listado de informes del paciente autenticado.
     */
    public function actionMisResultados(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        return [
            'success' => true,
            'message' => 'Resultados de laboratorio',
            'data' => [
                'reports' => $this->query->listForPersona($idPersona),
            ],
        ];
    }

    /**
     * Pull desde LIS configurado (paciente autenticado = su persona).
     */
    public function actionSincronizar(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $connectorKey = Yii::$app->request->post('connector')
            ?? Yii::$app->request->get('connector');

        try {
            $result = $this->ingest->syncForPersona($idPersona, is_string($connectorKey) ? $connectorKey : null);
        } catch (\Throwable $e) {
            Yii::error($e, 'laboratory-sync');

            return $this->clinicalError($e->getMessage(), null, 502);
        }

        return [
            'success' => true,
            'message' => 'Sincronización de laboratorio',
            'data' => $result,
        ];
    }

    /**
     * UI JSON: listado de informes del paciente (asistente / móvil).
     *
     * @tags clinical, laboratory, paciente, ui_json
     * @keywords mis resultados, laboratorio, análisis, estudios
     */
    public function actionMisResultadosComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        $out = UiScreenService::handleScreen(
            'laboratory-results',
            'mis-resultados-como-paciente',
            $req->get(),
            $req->post(),
            static function (): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $items = [];
            foreach ($this->query->listForPersona($idPersona) as $report) {
                $label = (string) ($report['display'] ?? 'Informe de laboratorio');
                $issued = (string) ($report['issuedAt'] ?? '');
                if ($issued !== '') {
                    $label .= ' · ' . $issued;
                }
                $obs = $report['observations'] ?? [];
                $subtitle = is_array($obs) && $obs !== [] ? count($obs) . ' analitos' : '';
                $items[] = [
                    'id' => (string) ($report['id'] ?? ''),
                    'name' => $label,
                    'label' => $label,
                    'subtitle' => $subtitle,
                ];
            }

            return UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * UI JSON: sincronizar resultados desde el LIS (asistente / móvil).
     *
     * @tags clinical, laboratory, paciente, ui_json
     * @keywords actualizar resultados, sincronizar laboratorio, traer análisis
     */
    public function actionSincronizarComoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError('Solo pacientes autenticados.', null, 400);
        }

        return UiScreenService::handleScreen(
            'laboratory-results',
            'sincronizar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($idPersona, $req): array {
                $connectorKey = $post['connector'] ?? $req->get('connector');
                try {
                    $result = $this->ingest->syncForPersona(
                        $idPersona,
                        is_string($connectorKey) && $connectorKey !== '' ? $connectorKey : null
                    );
                } catch (\Throwable $e) {
                    Yii::error($e, 'laboratory-sync');
                    throw new \RuntimeException($e->getMessage());
                }

                $msg = 'Se importaron ' . (int) ($result['imported'] ?? 0) . ' informe(s).';
                $errors = $result['errors'] ?? [];
                if (is_array($errors) && $errors !== []) {
                    $msg .= ' ' . implode(' ', array_map('strval', $errors));
                }

                return [
                    'data' => [
                        'success' => true,
                        'message' => $msg,
                        'sync' => $result,
                    ],
                ];
            }
        );
    }

    /**
     * Informes vinculados a un encounter (staff o paciente con acceso).
     */
    public function actionPorEncounter($encounterId): array
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        return [
            'success' => true,
            'message' => 'Laboratorio del encounter',
            'data' => [
                'encounterId' => (int) $encounter->id,
                'reports' => $this->query->listForEncounter((int) $encounter->id),
            ],
        ];
    }
}
