<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Inpatient\Service\InternacionAltaEstructuradaService;
use common\components\Clinical\Inpatient\Service\InternacionCambioCamaService;
use common\components\Clinical\Inpatient\Service\InternacionIngresoService;
use common\components\Clinical\Inpatient\Service\InternacionCamaEstadoService;
use common\components\Clinical\Inpatient\Service\InternacionIndicadoresService;
use common\components\Clinical\Inpatient\Service\InternacionMapaCamasService;
use common\components\Ui\UiScreenService;
use common\models\Person\Persona;
use common\models\SegNivelInternacion;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Internación: mapa de camas, indicadores y alta estructurada (staff).
 *
 * GET  /api/v1/clinical/internacion/mapa-camas
 * GET  /api/v1/clinical/internacion/indicadores-resumen
 * POST /api/v1/clinical/internacion/cama/<camaId>/marcar-estado
 * GET|POST /api/v1/clinical/internacion/<internacionId>/alta-formulario
 * GET|POST /api/v1/clinical/internacion/<internacionId>/cambio-cama-formulario
 * GET|POST /api/v1/clinical/internacion/ingreso-formulario
 * GET  /api/v1/clinical/internacion/plantillas-epicrisis
 * GET  /api/v1/clinical/internacion/<internacionId>/preview-plantilla-epicrisis
 */
class InternacionController extends BaseController
{
    use ClinicalAccessTrait;

    private InternacionMapaCamasService $mapa;
    private InternacionIndicadoresService $indicadores;
    private InternacionCamaEstadoService $camaEstado;
    private InternacionAltaEstructuradaService $alta;
    private InternacionCambioCamaService $cambioCama;
    private InternacionIngresoService $ingreso;

    public function init(): void
    {
        parent::init();
        $this->mapa = new InternacionMapaCamasService();
        $this->indicadores = new InternacionIndicadoresService();
        $this->camaEstado = new InternacionCamaEstadoService();
        $this->alta = new InternacionAltaEstructuradaService();
        $this->cambioCama = new InternacionCambioCamaService();
        $this->ingreso = new InternacionIngresoService();
    }

    public function actionMapaCamas(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Internacion.view_map');
            $data = $this->mapa->mapa(
                $idEfector,
                (int) ($req->get('id_piso') ?? $req->post('id_piso') ?? 0) ?: null,
                (int) ($req->get('id_sala') ?? $req->post('id_sala') ?? 0) ?: null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        if ($req->isPost && ($req->post('ui_submit') ?? '') === '') {
            return $this->success($data, 'Mapa de camas');
        }

        $items = [];
        foreach ($data['pisos'] as $piso) {
            foreach ($piso['salas'] as $sala) {
                foreach ($sala['camas'] as $cama) {
                    $estado = (string) ($cama['estado_mapa'] ?? '');
                    $nombre = $cama['paciente_nombre'] ?? null;
                    $label = 'Cama ' . ($cama['nro_cama'] ?? '') . ' · ' . $estado;
                    if ($nombre) {
                        $label .= ' — ' . $nombre;
                    }
                    $items[] = [
                        'id' => (string) ($cama['id'] ?? ''),
                        'name' => $label,
                        'label' => $label,
                        'subtitle' => ($piso['descripcion'] ?? '') . ' / ' . ($sala['descripcion'] ?? ''),
                        'meta' => $cama,
                    ];
                }
            }
        }

        $params = array_merge($req->get(), [
            'resumen_texto' => (string) ($data['resumen_texto'] ?? ''),
        ]);
        $out = UiScreenService::renderUiDefinition('internacion', 'mapa-camas', $params, null);
        $out['success'] = true;
        $out['data'] = $data;

        return UiScreenService::withListBlockItems($out, $items, 'camas');
    }

    public function actionIndicadoresResumen(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
            $data = $this->indicadores->resumen($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success($data, 'Indicadores de internación');
    }

    public function actionMarcarEstado(int $camaId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
            $data = $this->camaEstado->marcar(
                $camaId,
                $idEfector,
                (string) ($req->post('estado_mapa') ?? $req->post('estado') ?? ''),
                $req->post('motivo') !== null ? (string) $req->post('motivo') : null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Estado de cama actualizado');
    }

    public function actionAltaFormulario(int $internacionId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $out = UiScreenService::handleScreen(
            'internacion',
            'alta-formulario',
            $req->get(),
            $req->post(),
            function (array $post) use ($internacionId, $idEfector): array {
                [, $err] = $this->requireInternacionStaffAccess($internacionId, 'Internacion.discharge');
                if ($err !== null) {
                    throw new \InvalidArgumentException((string) ($err['message'] ?? 'Sin permiso'));
                }
                $data = $this->alta->registrarAlta($internacionId, $idEfector, $post);

                return [
                    'data' => $data,
                    'message' => (string) ($data['message'] ?? 'Alta registrada'),
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            [$internacion, $err] = $this->requireInternacionStaffAccess($internacionId, 'Internacion.discharge');
            if ($err !== null) {
                return $err;
            }

            $ctx = $this->alta->contextoAlta($internacion, $idEfector);
            $tipoOptions = array_map(static fn (array $t): array => [
                'value' => (string) $t['id'],
                'label' => (string) $t['label'],
            ], $ctx['tipos_alta']);
            $plantillaOptions = array_map(static fn (array $p): array => [
                'value' => (string) $p['id'],
                'label' => (string) $p['nombre'],
            ], $ctx['plantillas']);

            $params = array_merge($req->get(), [
                'internacion_id' => (string) $internacionId,
                'fecha_fin' => date('Y-m-d'),
                'hora_fin' => date('H:i'),
                'resumen_texto' => 'Alta de ' . ($ctx['paciente_nombre'] ?? 'paciente')
                    . " (internación #{$internacionId})",
                'responsable_alta' => (string) ($ctx['responsable_nombre'] ?? ''),
                'id_profesional_responsable' => $ctx['responsable_pes_id'] !== null
                    ? (string) $ctx['responsable_pes_id']
                    : '',
            ]);
            $out = UiScreenService::renderUiDefinition('internacion', 'alta-formulario', $params, $params);
            $out['data'] = $ctx;

            foreach ($out['blocks'] ?? [] as $idx => $block) {
                if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                    continue;
                }
                foreach ($block['fields'] ?? [] as $fIdx => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $name = (string) ($field['name'] ?? '');
                    if ($name === 'id_tipo_alta') {
                        $field['options'] = $tipoOptions;
                    }
                    if ($name === 'plantilla_id') {
                        $field['options'] = array_merge(
                            [['value' => '', 'label' => '— Sin plantilla —']],
                            $plantillaOptions
                        );
                    }
                    $block['fields'][$fIdx] = $field;
                }
                $out['blocks'][$idx] = $block;
            }
        }

        return $out;
    }

    public function actionCambioCamaFormulario(int $internacionId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $out = UiScreenService::handleScreen(
            'internacion',
            'cambio-cama-formulario',
            $req->get(),
            $req->post(),
            function (array $post) use ($internacionId, $idEfector): array {
                [, $err] = $this->requireInternacionStaffAccess($internacionId, 'Internacion.change_bed');
                if ($err !== null) {
                    throw new \InvalidArgumentException((string) ($err['message'] ?? 'Sin permiso'));
                }
                $data = $this->cambioCama->registrarCambioCama($internacionId, $idEfector, $post);

                return [
                    'data' => $data,
                    'message' => (string) ($data['message'] ?? 'Cambio de cama registrado'),
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            [$internacion, $err] = $this->requireInternacionStaffAccess($internacionId, 'Internacion.change_bed');
            if ($err !== null) {
                return $err;
            }

            try {
                $ctx = $this->cambioCama->contextoCambioCama($internacion, $idEfector);
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage(), null, 400);
            }

            $camaOptions = $ctx['camas_disponibles'] ?? [];

            $params = array_merge($req->get(), [
                'internacion_id' => (string) $internacionId,
                'resumen_texto' => 'Cambio de cama — ' . ($ctx['paciente_nombre'] ?? 'paciente')
                    . " (internación #{$internacionId})",
                'cama_actual_label' => (string) ($ctx['cama_actual_label'] ?? ''),
            ]);
            $out = UiScreenService::renderUiDefinition('internacion', 'cambio-cama-formulario', $params, $params);
            $out['data'] = $ctx;

            foreach ($out['blocks'] ?? [] as $idx => $block) {
                if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                    continue;
                }
                foreach ($block['fields'] ?? [] as $fIdx => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    if ((string) ($field['name'] ?? '') === 'id_cama') {
                        $field['options'] = array_merge(
                            [['value' => '', 'label' => '— Elegir cama —']],
                            $camaOptions
                        );
                    }
                    $block['fields'][$fIdx] = $field;
                }
                $out['blocks'][$idx] = $block;
            }
        }

        return $out;
    }

    public function actionIngresoFormulario(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Internacion.create');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $out = UiScreenService::handleScreen(
            'internacion',
            'ingreso-formulario',
            $req->get(),
            $req->post(),
            function (array $post) use ($idEfector): array {
                $data = $this->ingreso->registrarIngreso($idEfector, $post);

                return [
                    'data' => $data,
                    'message' => (string) ($data['message'] ?? 'Ingreso registrado'),
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->getIsGet()) {
            $idPersona = (int) ($req->get('id_persona') ?? 0);
            if ($idPersona <= 0) {
                return $this->error('Se requiere id_persona.', null, 400);
            }
            try {
                $ctx = $this->ingreso->contextoIngreso(
                    $idPersona,
                    $idEfector,
                    (int) ($req->get('id_cama') ?? 0) ?: null,
                    (int) ($req->get('id_guardia') ?? 0) ?: null
                );
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage(), null, 400);
            }

            $params = array_merge($req->get(), [
                'id_persona' => (string) $idPersona,
                'resumen_texto' => 'Ingreso — ' . ($ctx['paciente_nombre'] ?? 'paciente'),
                'cama_label' => (string) ($ctx['cama_label'] ?? ''),
                'fecha_inicio' => (string) ($ctx['fecha_inicio'] ?? date('Y-m-d')),
                'hora_inicio' => (string) ($ctx['hora_inicio'] ?? date('H:i')),
                'obra_social' => $ctx['obra_social_default'] !== null
                    ? (string) $ctx['obra_social_default']
                    : '',
                'id_tipo_ingreso' => $ctx['id_tipo_ingreso_default'] !== null
                    ? (string) $ctx['id_tipo_ingreso_default']
                    : '',
                'id_cama' => $ctx['id_cama'] !== null ? (string) $ctx['id_cama'] : '',
                'id_guardia' => $ctx['id_guardia'] !== null ? (string) $ctx['id_guardia'] : '',
            ]);
            $out = UiScreenService::renderUiDefinition('internacion', 'ingreso-formulario', $params, $params);
            $out['data'] = $ctx;

            $optionMap = [
                'id_profesional_efector_servicio' => $ctx['profesionales'] ?? [],
                'id_cama' => $ctx['camas_disponibles'] ?? [],
                'obra_social' => $ctx['coberturas'] ?? [],
                'id_efector_origen' => $ctx['efectores_origen'] ?? [],
                'id_tipo_ingreso' => $ctx['tipos_ingreso'] ?? [],
                'ingresa_en' => $ctx['ingresa_en'] ?? [],
                'ingresa_con' => $ctx['ingresa_con'] ?? [],
            ];

            foreach ($out['blocks'] ?? [] as $idx => $block) {
                if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                    continue;
                }
                foreach ($block['fields'] ?? [] as $fIdx => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $name = (string) ($field['name'] ?? '');
                    if (isset($optionMap[$name])) {
                        $opts = $optionMap[$name];
                        if ($name === 'id_cama' && empty($params['id_cama'])) {
                            $field['options'] = array_merge(
                                [['value' => '', 'label' => '— Elegir cama —']],
                                $opts
                            );
                        } elseif ($name === 'obra_social') {
                            $field['options'] = array_merge(
                                [['value' => '', 'label' => '— Cobertura —']],
                                $opts
                            );
                        } elseif ($name === 'id_efector_origen') {
                            $field['options'] = array_merge(
                                [['value' => '', 'label' => '— Efector origen —']],
                                $opts
                            );
                        } else {
                            $field['options'] = $opts;
                        }
                    }
                    if ($name === 'id_cama' && !empty($params['id_cama'])) {
                        $field['readonly'] = true;
                    }
                    $block['fields'][$fIdx] = $field;
                }
                $out['blocks'][$idx] = $block;
            }
        }

        return $out;
    }

    public function actionPlantillasEpicrisis(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
            $plantillas = (new \common\components\Clinical\Inpatient\Service\InternacionEpicrisisPlantillaService())
                ->listar($idEfector, (int) ($req->get('id_servicio') ?? 0) ?: null);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['plantillas' => $plantillas], 'Plantillas de epicrisis');
    }

    public function actionPreviewPlantillaEpicrisis(int $internacionId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = $this->resolveIdEfectorForDomainOperation('Clinical.staff_efector');
            $plantillaId = (int) ($req->get('plantilla_id') ?? 0);
            if ($plantillaId <= 0) {
                throw new \InvalidArgumentException('Se requiere plantilla_id.');
            }
            $texto = $this->alta->previewPlantilla($plantillaId, $internacionId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (ForbiddenHttpException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(['epicrisis' => $texto], 'Vista previa de plantilla');
    }
}
