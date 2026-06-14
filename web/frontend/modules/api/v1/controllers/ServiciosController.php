<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Platform\Ui\UiScreenService;
use common\components\Domain\Organization\Service\Servicios\ServiciosEfectorAutogestionListadoService;
use common\components\Domain\Scheduling\Service\ReservaTriageServicioSugeridoService;

/**
 * API Servicios: views JSON embebibles (selección/autocomplete) para flujos conversacionales.
 */
class ServiciosController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * View embebible: elegir servicio para turnos.
     * Origen: {@see ServiciosEfector} (activos) + join {@see Servicio} con `acepta_turnos` = SI, distinct por `id_servicio`.
     *
     * GET|POST /api/v1/servicios/elegir-acepta-turnos
     *
     * @action_name Elegir servicio para turnos
     * @entity Servicios
     * @tags views, ui, servicio, turnos
     * @keywords elegir servicio turnos, reservar turno, servicio acepta agenda
     */
    public function actionElegirAceptaTurnos(): array
    {
        $req = Yii::$app->request;
        $ui = UiScreenService::handleScreen(
            'servicios',
            'elegir-acepta-turnos',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (isset($ui['kind']) && $ui['kind'] === 'ui_definition' && isset($ui['ui_type']) && $ui['ui_type'] === 'ui_json') {
            $params = array_merge($req->get(), $req->post());
            $triageDraft = ReservaTriageServicioSugeridoService::draftDesdeParamsTriage($params);
            $sugerido = new ReservaTriageServicioSugeridoService();
            $items = ServiciosEfectorAutogestionListadoService::uiJsonItemsServiciosDistintosAceptaTurnos();

            if (ReservaTriageServicioSugeridoService::esListadoPresencial($params)) {
                $draftCompleto = array_merge($triageDraft, ReservaTriageServicioSugeridoService::draftCarePlanDesdeParams($params));
                if ($sugerido->esSeguimientoConCarePlan($draftCompleto)) {
                    $items = $sugerido->filtrarItemsUiJson($items, $draftCompleto, false);
                    $intro = $sugerido->mensajeIntroCarePlanParaDraft($draftCompleto);
                } elseif ($sugerido->esMalestarNuevoConZona($draftCompleto)) {
                    $items = $sugerido->filtrarItemsUiJson($items, $draftCompleto, false);
                    $intro = $sugerido->mensajeIntroZonaParaDraft($draftCompleto);
                    if ($items === []) {
                        $ui = self::withListEmptyMessage($ui, $sugerido->mensajeListaVaciaParaDraft($draftCompleto, false));
                    }
                } else {
                    $items = $sugerido->priorizarItemsSegunTriage($items, $triageDraft);
                    $intro = $sugerido->mensajeIntroPresencialParaDraft($triageDraft);
                }
                if ($intro !== null && $items !== []) {
                    $ui = self::withListIntroMessage($ui, $intro);
                }
            } else {
                $modoHub = ReservaTriageServicioSugeridoService::esModoHubPaciente($params);
                if ($modoHub || $triageDraft !== []) {
                    $items = $sugerido->filtrarItemsUiJson($items, $triageDraft, false);
                }
                if ($items === [] && ($modoHub || $triageDraft !== [])) {
                    $ui = self::withListEmptyMessage($ui, $sugerido->mensajeListaVaciaParaDraft($triageDraft, false));
                } else {
                    $intro = $sugerido->mensajeIntroListaParaDraft($triageDraft, false);
                    if ($intro !== null) {
                        $ui = self::withListIntroMessage($ui, $intro);
                    }
                }
            }
            $ui = UiScreenService::withListBlockItems($ui, $items);
        }

        return $ui;
    }

    /**
     * @param array<string, mixed> $ui
     * @return array<string, mixed>
     */
    private static function withListEmptyMessage(array $ui, string $message): array
    {
        if (!isset($ui['blocks']) || !is_array($ui['blocks'])) {
            return $ui;
        }
        foreach ($ui['blocks'] as $i => $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'list') {
                continue;
            }
            $block['empty_message'] = $message;
            $ui['blocks'][$i] = $block;
            break;
        }

        return $ui;
    }

    /**
     * @param array<string, mixed> $ui
     * @return array<string, mixed>
     */
    private static function withListIntroMessage(array $ui, string $message): array
    {
        if (!isset($ui['blocks']) || !is_array($ui['blocks'])) {
            return $ui;
        }
        foreach ($ui['blocks'] as $i => $block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'list') {
                continue;
            }
            $block['intro'] = $message;
            $ui['blocks'][$i] = $block;
            break;
        }

        return $ui;
    }
}

