<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Inpatient\InternacionAltaEstructuradaService;
use common\components\Inpatient\InternacionCamaEstadoService;
use common\components\Inpatient\InternacionEfectorAccess;
use common\components\Inpatient\InternacionIndicadoresService;
use common\components\Inpatient\InternacionMapaCamasService;
use common\components\Ui\UiScreenService;
use common\models\Persona;
use common\models\SegNivelInternacion;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Internación: mapa de camas, indicadores y alta estructurada (staff).
 *
 * GET  /api/v1/clinical/internacion/mapa-camas
 * GET  /api/v1/clinical/internacion/indicadores-resumen
 * POST /api/v1/clinical/internacion/cama/<camaId>/marcar-estado
 * GET|POST /api/v1/clinical/internacion/<internacionId>/alta-formulario
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

    public function init(): void
    {
        parent::init();
        $this->mapa = new InternacionMapaCamasService();
        $this->indicadores = new InternacionIndicadoresService();
        $this->camaEstado = new InternacionCamaEstadoService();
        $this->alta = new InternacionAltaEstructuradaService();
    }

    public function actionMapaCamas(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? $req->post('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->mapa->mapa(
                $idEfector,
                (int) ($req->get('id_piso') ?? $req->post('id_piso') ?? 0) ?: null,
                (int) ($req->get('id_sala') ?? $req->post('id_sala') ?? 0) ?: null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
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
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->indicadores->resumen($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success($data, 'Indicadores de internación');
    }

    public function actionMarcarEstado(int $camaId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->post('id_efector') ?? $req->get('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
            $data = $this->camaEstado->marcar(
                $camaId,
                $idEfector,
                (string) ($req->post('estado_mapa') ?? $req->post('estado') ?? ''),
                $req->post('motivo') !== null ? (string) $req->post('motivo') : null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 500);
        }

        return $this->success($data, 'Estado de cama actualizado');
    }

    public function actionAltaFormulario(int $internacionId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? $req->post('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        $out = UiScreenService::handleScreen(
            'internacion',
            'alta-formulario',
            $req->get(),
            $req->post(),
            function (array $post) use ($internacionId, $idEfector): array {
                [, $err] = $this->requireInternacionStaffAccess($internacionId);
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

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet()) {
            [$internacion, $err] = $this->requireInternacionStaffAccess($internacionId);
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

    public function actionPlantillasEpicrisis(): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
            $plantillas = (new \common\components\Inpatient\InternacionEpicrisisPlantillaService())
                ->listar($idEfector, (int) ($req->get('id_servicio') ?? 0) ?: null);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success(['plantillas' => $plantillas], 'Plantillas de epicrisis');
    }

    public function actionPreviewPlantillaEpicrisis(int $internacionId): array
    {
        $req = Yii::$app->request;
        try {
            $idEfector = InternacionEfectorAccess::resolveIdEfector(
                (int) ($req->get('id_efector') ?? 0) ?: null
            );
            InternacionEfectorAccess::assertCanAccessEfector($idEfector);
            $plantillaId = (int) ($req->get('plantilla_id') ?? 0);
            if ($plantillaId <= 0) {
                throw new \InvalidArgumentException('Se requiere plantilla_id.');
            }
            $texto = $this->alta->previewPlantilla($plantillaId, $internacionId, $idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        }

        return $this->success(['epicrisis' => $texto], 'Vista previa de plantilla');
    }
}
