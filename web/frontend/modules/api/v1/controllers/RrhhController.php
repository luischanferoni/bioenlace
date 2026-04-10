<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;
use common\components\Services\Rrhh\RrhhAgendaUiService;
use common\components\UiScreenService;
use common\models\Condiciones_laborales;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\ServiciosEfector;

/**
 * API Rrhh: autocomplete de RRHH por efector/servicio; servicios asignados al RRHH del usuario en un efector.
 * Autocomplete: migrado desde frontend\controllers\RrhhController::actionRrhhAutocomplete.
 * Servicios por RRHH: migrado desde frontend\controllers\RrhhEfectorController::actionServiciosPorRrhh.
 */
class RrhhController extends BaseController
{
    public static $authenticatorExcept = ['autocomplete'];

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * GET/POST /api/v1/rrhh/autocomplete
     * Parámetros: id_efector, id_servicio (requeridos); q, limit, sort_by, sort_order, efector_nombre, servicio_nombre (opcionales).
     */
    public function actionAutocomplete($q = null)
    {
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        $idServicio = $request->get('id_servicio') ?: $request->post('id_servicio');
        if (empty($idEfector) || empty($idServicio)) {
            throw new BadRequestHttpException('id_efector e id_servicio son requeridos');
        }
        $out = ['results' => ['id' => '', 'text' => '']];
        $q = $q ?? $request->get('q') ?? $request->post('q');
        $filters = [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
        ];
        if ($request->get('limit') || $request->post('limit')) {
            $filters['limit'] = $request->get('limit') ?: $request->post('limit');
        }
        if ($request->get('efector_nombre') || $request->post('efector_nombre')) {
            $filters['efector_nombre'] = $request->get('efector_nombre') ?: $request->post('efector_nombre');
        }
        if ($request->get('servicio_nombre') || $request->post('servicio_nombre')) {
            $filters['servicio_nombre'] = $request->get('servicio_nombre') ?: $request->post('servicio_nombre');
        }
        if ($request->get('sort_by') || $request->post('sort_by')) {
            $filters['sort_by'] = $request->get('sort_by') ?: $request->post('sort_by');
        }
        if ($request->get('sort_order') || $request->post('sort_order')) {
            $filters['sort_order'] = $request->get('sort_order') ?: $request->post('sort_order');
        }
        if ($q === null && count($filters) <= 2) {
            return $out;
        }
        $out['results'] = array_values(RrhhEfector::autocompleteRrhh($q, $filters));
        return $out;
    }

    /**
     * GET|POST /api/v1/rrhh/mis-servicios-en-efector
     *
     * Lista servicios asignados al RRHH de la persona autenticada, filtrados como en el flujo web
     * (servicio activo en el efector o servicio con item_name AdminEfector).
     *
     * Resolución del vínculo RRHH–efector:
     * - Si viene `id_efector` o `idEfector` (query/body): se usa la fila `rrhh_efector` de esa persona en ese efector
     *   (p. ej. wizard post-login antes de tener sesión operativa completa).
     * - Si no viene efector: se usa `id_rr_hh` de la sesión operativa (`getIdRecursoHumano`) y la fila correspondiente
     *   debe pertenecer a la misma persona.
     *
     * @return array{servicios: list<array{id_servicio: int, nombre: string}>}
     */
    public function actionMisServiciosEnEfector()
    {
        $request = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();

        $idEfectorRaw = $request->post('id_efector') ?: $request->post('idEfector')
            ?: $request->get('id_efector') ?: $request->get('idEfector');
        $tieneEfectorEnPedido = $idEfectorRaw !== null && $idEfectorRaw !== '';
        $idEfectorPedido = $tieneEfectorEnPedido ? (int) $idEfectorRaw : 0;

        $rrhhEfector = null;
        if ($idEfectorPedido > 0) {
            $rrhhEfector = RrhhEfector::findActive()
                ->where([
                    'id_efector' => $idEfectorPedido,
                    'id_persona' => $idPersona,
                ])
                ->one();
        } else {
            $idRrHhSesion = (int) Yii::$app->user->getIdRecursoHumano();
            if ($idRrHhSesion > 0) {
                $rrhhEfector = RrhhEfector::findActive()
                    ->where([
                        'id_rr_hh' => $idRrHhSesion,
                        'id_persona' => $idPersona,
                    ])
                    ->one();
            }
        }

        if ($rrhhEfector === null) {
            throw new BadRequestHttpException(
                'Indique id_efector o fije contexto operativo en sesión (recurso humano / efector) para listar servicios.'
            );
        }

        $idEfector = (int) $rrhhEfector->id_efector;
        $servicios = [];
        $rrhhServicios = $rrhhEfector->getRrhhServicio()->with('servicio')->all();
        foreach ($rrhhServicios as $rrhhServicio) {
            $servicioEfector = ServiciosEfector::findActive()
                ->where([
                    'id_efector' => $idEfector,
                    'id_servicio' => $rrhhServicio->id_servicio,
                ])
                ->one();

            $nombreServicio = $rrhhServicio->servicio !== null
                ? (string) $rrhhServicio->servicio->nombre
                : '';
            $esAdminEfector = $rrhhServicio->servicio !== null
                && (string) $rrhhServicio->servicio->item_name === 'AdminEfector';

            if (
                ($servicioEfector !== null && $servicioEfector->deleted_at === null)
                || $esAdminEfector
            ) {
                $servicios[] = [
                    'id_servicio' => (int) $rrhhServicio->id_servicio,
                    'nombre' => $nombreServicio,
                ];
            }
        }

        return ['servicios' => $servicios];
    }

    /**
     * GET/POST /api/v1/rrhh/listar-por-efector
     * RRHH del efector (staff). Parámetros: id_efector (opcional, default sesión), q, limit.
     *
     * @return array{results: list<array{id: int, text: string}>}
     */
    public function actionListarPorEfector($q = null)
    {
        $request = Yii::$app->request;
        $idEfector = $request->get('id_efector') ?: $request->post('id_efector');
        if ($idEfector === null || $idEfector === '') {
            $idEfector = Yii::$app->user->getIdEfector();
        }
        $idEfector = (int) $idEfector;
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('id_efector es requerido');
        }

        $q = $q ?? $request->get('q') ?? $request->post('q');
        $limit = (int) ($request->get('limit') ?: $request->post('limit') ?: 50);
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $query = RrhhEfector::find()
            ->alias('re')
            ->with('persona')
            ->where(['re.id_efector' => $idEfector])
            ->andWhere(['re.deleted_at' => null]);

        if ($q !== null && trim((string) $q) !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $q)) . '%';
            $query->joinWith('persona p')
                ->andWhere([
                    'or',
                    ['like', 'p.apellido', $term, false],
                    ['like', 'p.nombre', $term, false],
                    ['like', 'p.documento', $term, false],
                ]);
        }

        $rows = $query->orderBy(['re.id_rr_hh' => SORT_ASC])->limit($limit)->all();

        $results = [];
        foreach ($rows as $re) {
            $nombre = $re->persona !== null ? $re->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : ('RRHH #' . $re->id_rr_hh);
            $results[] = [
                'id' => (int) $re->id_rr_hh,
                'text' => $nombre,
            ];
        }

        return ['results' => $results];
    }

    /**
     * GET/POST /api/v1/rrhh/servicios-asignados
     * Servicios ya asignados al RRHH (para edición de agenda). Requiere id_rr_hh.
     *
     * @return array{results: list<array{id: int, text: string, meta: array<string, int}>}
     */
    public function actionServiciosAsignados()
    {
        $request = Yii::$app->request;
        $idRrHh = $request->get('id_rr_hh') ?: $request->post('id_rr_hh');
        if ($idRrHh === null || $idRrHh === '') {
            throw new BadRequestHttpException('id_rr_hh es requerido');
        }
        $idRrHh = (int) $idRrHh;

        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            throw new BadRequestHttpException('No hay efector en sesión.');
        }

        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrHh, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            throw new BadRequestHttpException('RRHH no válido para este efector.');
        }

        $servicios = RrhhServicio::find()
            ->where(['id_rr_hh' => $idRrHh, 'deleted_at' => null])
            ->with('servicio')
            ->orderBy(['id_servicio' => SORT_ASC])
            ->all();

        $results = [];
        foreach ($servicios as $rs) {
            if ((int) $rs->id_servicio === 62) {
                continue;
            }
            $nombre = $rs->servicio !== null ? (string) $rs->servicio->nombre : ('Servicio #' . $rs->id_servicio);
            $results[] = [
                'id' => (int) $rs->id_servicio,
                'text' => $nombre,
                'meta' => [
                    'id_rrhh_servicio' => (int) $rs->id,
                ],
            ];
        }

        return ['results' => $results];
    }

    /**
     * GET /api/v1/rrhh/condiciones-laborales-catalogo
     *
     * @return array{results: list<array{id: int, text: string}>}
     */
    public function actionCondicionesLaboralesCatalogo()
    {
        $rows = Condiciones_laborales::find()
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row->id_condicion_laboral,
                'text' => (string) $row->nombre,
            ];
        }

        return ['results' => $results];
    }

    /**
     * UI JSON: wizard RRHH → agenda por servicio → condición laboral.
     *
     * GET|POST /api/v1/ui/rrhh/editar-agenda
     *
     * @action_name Editar agenda y condición laboral (RRHH)
     * @entity Rrhh
     * @tags rrhh, agenda, servicios, condiciones laborales, staff
     * @keywords editar agenda profesional, horarios por servicio, condición laboral
     * @spa_presentation fullscreen
     */
    public function actionEditarAgenda(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();

        $fromClient = array_merge($req->get(), $req->isPost ? $req->post() : []);
        $defaults = RrhhAgendaUiService::buildFieldValuesForGet($idEfector, $fromClient);
        $paramsForRender = array_merge($defaults, $fromClient);

        return UiScreenService::handleScreen(
            'rrhh',
            'editar-agenda',
            $paramsForRender,
            $req->post(),
            function (array $post) use ($idEfector): array {
                return RrhhAgendaUiService::submit($idEfector, $post);
            }
        );
    }
}
