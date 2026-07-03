<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Domain\Scheduling\Service\ConsultasSeguimientoIntakeStepService;
use common\components\Platform\Ui\UiScreenService;

/**
 * Intake paciente: consulta general y seguimiento de tratamiento.
 *
 * RBAC ApiGhost: /api/consultas-seguimiento/&lt;action&gt;
 */
class ConsultasSeguimientoController extends BaseController
{
    /**
     * GET|POST /api/v1/consultas-seguimiento/paso
     *
     * @action_name Paso intake consultas y seguimiento
     * @entity ConsultasSeguimiento
     * @tags views, ui, paciente, consulta, seguimiento
     */
    public function actionPaso(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $step = isset($params['step']) ? trim((string) $params['step']) : '';
        if ($step === '') {
            throw new BadRequestHttpException(
                'step es obligatorio (tipo, necesidad, preferencia_turno).'
            );
        }

        $steps = new ConsultasSeguimientoIntakeStepService();
        $stepDef = $steps->stepDefinition($step);
        if ($stepDef === null) {
            throw new BadRequestHttpException('step no válido.');
        }

        $options = $steps->opcionesParaStep($step);

        $out = UiScreenService::handleScreen(
            'consultas-seguimiento',
            'paso',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($out['kind'], $out['ui_type']) && $out['kind'] === 'ui_definition' && $out['ui_type'] === 'ui_json') {
            $titleOverride = trim((string) ($params['step_title'] ?? ''));
            $out['title'] = $titleOverride !== '' ? $titleOverride : $stepDef['title'];
            $items = [];
            foreach ($options as $opt) {
                $items[] = [
                    'id' => $opt['code'],
                    'label' => $opt['label'],
                    'meta' => [
                        'urgency_band' => $opt['urgency_band'],
                        'halts_booking' => $opt['halts_booking'],
                    ],
                ];
            }
            $out = UiScreenService::withListBlockItems($out, $items, 'intake-opciones');
            if (isset($out['blocks']) && is_array($out['blocks'])) {
                foreach ($out['blocks'] as $i => $block) {
                    if (!is_array($block) || ($block['id'] ?? '') !== 'intake-opciones') {
                        continue;
                    }
                    $block['draft_field'] = $stepDef['draft_field'];
                    $out['blocks'][$i] = $block;
                    break;
                }
            }
        }

        return $out;
    }
}
