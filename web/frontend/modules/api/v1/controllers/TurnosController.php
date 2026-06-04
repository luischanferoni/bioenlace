<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;
use common\components\Clinical\Service\AppointmentReasonWindowService;
use common\components\Clinical\Service\EncounterLifecycleService;
use common\models\Clinical\Encounter;
use common\models\Turno;
use common\models\AgendaFeriados;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;
use common\models\ConsultaDerivaciones;
use common\models\Persona;
use common\components\Ui\UiDefinitionTemplateManager;
use common\components\Ui\UiScreenService;
use common\components\Scheduling\Service\TurnoSlotFinder;
use common\components\Scheduling\Service\TurnoSlotOfferService;
use common\components\Scheduling\Service\TurnoSlotOfferUiPresenter;
use common\components\Scheduling\Service\TurnoPersistService;
use common\components\Scheduling\Service\TurnoCreacionContext;
use common\components\Scheduling\Service\TurnoLifecycleService;
use common\components\Scheduling\Service\TurnoConfirmationService;
use common\components\Scheduling\Service\PolicyModeradaException;
use common\components\Scheduling\Service\AutogestionAnticipacionException;
use common\components\Scheduling\Service\TurnoAutogestionAnticipacionService;
use common\components\Scheduling\Service\TurnoCancellationPolicyService;
use common\components\Scheduling\Service\TurnoCancelacionRazones;
use common\components\Scheduling\Service\BulkCancelDayService;
use common\components\Scheduling\Service\SobreturnoService;
use common\components\Scheduling\Service\TurnoReservaSlotService;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaVersionService;
use common\components\Scheduling\Service\TurnoAgendaMetricsService;
use common\components\Scheduling\Service\ReservaTurnoTriageCatalogService;
use common\components\Scheduling\Service\TurnoResolucionService;
use common\components\Scheduling\Service\TurnoResolucionElecciones;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalContextResolver;
use common\models\TurnoResolucion;
use yii\web\ForbiddenHttpException;
use yii\web\ConflictHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\db\Expression;

/**
 * Turnos API v1.
 *
 * **Contrato de slot / asignación (PES-first):**
 * - Identidad canónica del cupo profesional: `id_profesional_efector_servicio` (>0).
 * - Donde aplique, se incluye `servicio` como objeto `{ id_servicio, nombre }` además del string `servicio` legible.
 *
 * {@see ProfesionalAgendaController} — GET /api/v1/profesional-agenda/dia (permiso /api/profesional-agenda/dia).
 */
class TurnosController extends BaseController
{
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update']);
        return $actions;
    }

    /**
     * UI JSON (screen) + submit unificado para autogestión.
     *
     * GET  /api/v1/turnos/crear-como-paciente => descriptor UI JSON
     * POST /api/v1/turnos/crear-como-paciente => submit; si falla devuelve UI + errors
     *
     * @action_name Reservar turno
     * @entity Turnos
     * @tags turnos, paciente, reserva, cita, autogestión
     * @keywords reservar turno, agendar cita, sacar turno, pedir turno
     */
    public function actionCrearComoPaciente(): array
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'crear-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $model = new Turno();
                $model->load($post, '');
                $model->id_persona = (int) Yii::$app->user->getIdPersona();

                return $this->ejecutarCreacionTurno($model);
            }
        );
    }

    /**
     * Catálogo declarativo de triage al reservar (JSON para clientes nativos).
     *
     * GET /api/v1/turnos/reserva-triage-catalogo
     *
     * @action_name Catálogo triage reserva turno
     * @entity Turnos
     * @tags turnos, paciente, triage, reserva
     */
    public function actionReservaTriageCatalogo(): array
    {
        $catalog = new ReservaTurnoTriageCatalogService();
        $manifest = $catalog->getManifest();
        unset($manifest['nodes']);

        return [
            'success' => true,
            'version' => $catalog->getVersion(),
            'halt_message_band_a' => $catalog->getHaltMessageBandA(),
            'steps' => $manifest['steps'] ?? [],
        ];
    }

    /**
     * Paso embebible del triage de reserva (lista de opciones del catálogo).
     *
     * GET|POST /api/v1/turnos/reserva-triage-paso
     * Query/body: `step` (raiz|alarmas|zona|detalle|evolucion); para zona/detalle también los códigos previos
     * (`triage_raiz`, `triage_zona`) o `parent_code`.
     *
     * @action_name Paso triage reserva turno
     * @entity Turnos
     * @tags views, ui, turnos, paciente, triage
     */
    public function actionReservaTriagePaso(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $step = isset($params['step']) ? trim((string) $params['step']) : '';
        if ($step === '') {
            throw new BadRequestHttpException(
                'step es obligatorio (raiz, alarmas, zona, detalle, evolucion). '
                . 'En flujos del asistente debe venir en query desde open_ui.params del subintent.'
            );
        }

        $catalog = new ReservaTurnoTriageCatalogService();
        $stepDef = $catalog->getStep($step);
        if ($stepDef === null) {
            throw new BadRequestHttpException('step no válido');
        }

        $parentCode = $this->reservaTriageParentForStep($step, $params);
        $options = $catalog->getOptionsForStep($step, $parentCode !== '' ? $parentCode : null);

        $out = UiScreenService::handleScreen(
            'turnos',
            'reserva-triage-paso',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($out['kind'], $out['ui_type']) && $out['kind'] === 'ui_definition' && $out['ui_type'] === 'ui_json') {
            $out['title'] = $stepDef['title'];
            $items = [];
            foreach ($options as $opt) {
                $items[] = [
                    'id' => $opt['code'],
                    'label' => $opt['label'],
                    'meta' => [
                        'urgency_band' => $opt['urgency_band'],
                        'halts_booking' => $opt['halts_booking'],
                        'suggests_tipo_atencion' => $opt['suggests_tipo_atencion'],
                    ],
                ];
            }
            $out = UiScreenService::withListBlockItems($out, $items, 'triage-opciones');
            if (isset($out['blocks']) && is_array($out['blocks'])) {
                foreach ($out['blocks'] as $i => $block) {
                    if (!is_array($block) || ($block['id'] ?? '') !== 'triage-opciones') {
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

    /**
     * Pantalla terminal: derivación a urgencia (banda A).
     *
     * GET|POST /api/v1/turnos/reserva-triage-urgencia
     *
     * @action_name Alerta urgencia triage reserva
     * @entity Turnos
     * @tags views, ui, turnos, paciente, triage, urgencia
     */
    public function actionReservaTriageUrgencia(): array
    {
        $req = Yii::$app->request;
        $catalog = new ReservaTurnoTriageCatalogService();
        $msg = $catalog->getHaltMessageBandA();

        $out = UiScreenService::handleScreen(
            'turnos',
            'reserva-triage-urgencia',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        if (isset($out['blocks']) && is_array($out['blocks'])) {
            foreach ($out['blocks'] as $i => $block) {
                if (!is_array($block) || ($block['id'] ?? '') !== 'urgencia') {
                    continue;
                }
                $block['body'] = $msg;
                $out['blocks'][$i] = $block;
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function reservaTriageParentForStep(string $step, array $params): string
    {
        if ($step === 'zona') {
            return trim((string) ($params['triage_raiz'] ?? $params['parent_code'] ?? ''));
        }
        if ($step === 'detalle') {
            return trim((string) ($params['triage_zona'] ?? $params['parent_code'] ?? ''));
        }

        return trim((string) ($params['parent_code'] ?? ''));
    }

    /**
     * Turnos donde el usuario es paciente. GET|POST /api/v1/turnos/listar-como-paciente.
     *
     * Sin `alcance`: compatibilidad (pendientes por rango de fecha, como antes) con `fecha_desde` / `fecha_hasta`.
     * Con `alcance=pendientes`: activos, estado PENDIENTE, fecha del turno &gt;= hoy (zona producto); incluye el resto del día aunque la hora ya pasó (alineado a agenda del profesional). Paginación: `limit`, `offset`.
     * Con `alcance=pasados`: historial con inicio &lt; ahora (cualquier estado); misma paginación.
     *
     * @action_name Mis turnos y citas (paciente)
     * @entity Turnos
     * @tags turnos, paciente, citas, calendario, autogestión
     * @keywords mis citas como paciente, calendario de turnos, próximos turnos, ver mis turnos pendientes
     * @spa_presentation inline
     */
    public function actionListarComoPaciente()
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'listar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($req): array {
                $params = array_merge($req->get(), $post);

                return ['data' => $this->listarComoPacienteData($params)];
            }
        );
    }

    /**
     * Lista embebible (`ui_json`) de turnos pendientes del paciente para elegir uno (cancelar / reprogramar).
     *
     * GET|POST /api/v1/turnos/elegir-pendiente-como-paciente
     * Parámetros opcionales: fecha_desde, fecha_hasta (mismo criterio que {@see self::listarComoPacienteData}).
     *
     * @action_name Elegir turno pendiente (paciente)
     * @entity Turnos
     * @tags views, ui, turnos, paciente, autogestión
     * @keywords elegir mi turno, cancelar turno, cambiar turno, reprogramar
     */
    public function actionElegirPendienteComoPaciente(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'elegir-pendiente-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && isset($out['ui_type']) && $out['ui_type'] === 'ui_json') {
            $params = array_merge($req->get(), $req->post());
            $params['alcance'] = 'pendientes';
            if (!isset($params['limit']) || $params['limit'] === '') {
                $params['limit'] = 200;
            }
            if (!isset($params['offset']) || $params['offset'] === '') {
                $params['offset'] = 0;
            }
            $data = $this->listarComoPacienteData($params);
            $items = [];
            foreach ($data['turnos'] as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $id = isset($t['id']) ? (int) $t['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $fecha = isset($t['fecha']) ? (string) $t['fecha'] : '';
                $hora = isset($t['hora']) ? (string) $t['hora'] : '';
                $svc = isset($t['servicio']) ? (string) $t['servicio'] : '';
                $prof = isset($t['profesional']) ? (string) $t['profesional'] : '';
                $fechaAmigable = $fecha !== '' ? TurnoSlotOfferUiPresenter::friendlyDayHeading($fecha) : '';
                $horaCorta = $this->formatHoraTurnoPacienteCorta($hora);
                $cuando = '';
                if ($fechaAmigable !== '' && $horaCorta !== '') {
                    $cuando = $fechaAmigable . ' · ' . $horaCorta;
                } elseif ($fechaAmigable !== '') {
                    $cuando = $fechaAmigable;
                } elseif ($horaCorta !== '') {
                    $cuando = $horaCorta;
                }
                $parts = array_filter([$cuando, $svc, $prof]);
                $label = implode(' · ', $parts);
                if ($label === '') {
                    $label = 'Turno #' . $id;
                }
                if (!empty($t['en_resolucion'])) {
                    $label = '⚠ ' . $label;
                }
                $items[] = [
                    'id' => (string) $id,
                    'name' => $label,
                ];
            }
            $out = UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * Lista embebible: turnos del paciente con conflicto de agenda pendiente.
     *
     * GET|POST /api/v1/turnos/elegir-conflicto-agenda-como-paciente
     *
     * @action_name Elegir turno con conflicto de agenda (paciente)
     * @entity Turnos
     * @tags views, ui, turnos, paciente, autogestión, agenda
     */
    public function actionElegirConflictoAgendaComoPaciente(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'elegir-conflicto-agenda-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && ($out['ui_type'] ?? '') === 'ui_json') {
            $idPersona = (int) Yii::$app->user->getIdPersona();
            $rows = TurnoResolucionService::listarEnResolucionParaPaciente(
                $idPersona,
                TurnoResolucion::ORIGEN_CAMBIO_AGENDA
            );
            $items = [];
            foreach ($rows as $row) {
                $items[] = TurnoResolucionService::toListPickerItem($row);
            }
            $out = UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * Mini-UI: elegir resolución de conflicto (antes / después / cancelar). No persiste; el POST final es {@see self::actionResolverConflictoAgendaComoPaciente()}.
     *
     * GET|POST /api/v1/turnos/elegir-resolucion-conflicto-agenda-como-paciente
     * Query/body: `id` (id_turnos). POST además `eleccion`.
     *
     * @action_name Resolución conflicto agenda (paciente)
     * @entity Turnos
     * @tags views, ui, turnos, paciente, agenda
     */
    public function actionElegirResolucionConflictoAgendaComoPaciente(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        $tid = $this->resolveTurnoId(null, $params, $req);
        if (!$req->isPost) {
            if (!$tid) {
                throw new BadRequestHttpException('id del turno requerido');
            }
            $res = TurnoResolucionElecciones::requireResolucionPendienteParaTurno(
                (int) $tid,
                (int) Yii::$app->user->getIdPersona()
            );
            $def = UiScreenService::renderUiDefinition(
                'turnos',
                'elegir-resolucion-conflicto-agenda-como-paciente',
                $params,
                null
            );

            return TurnoResolucionElecciones::aplicarOpcionesEleccionEnDefinicionUiJson($def, $res);
        }

        return UiScreenService::handleScreen(
            'turnos',
            'elegir-resolucion-conflicto-agenda-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($req): array {
                $tid = $this->resolveTurnoId(null, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $eleccion = trim((string) ($post['eleccion'] ?? ''));
                if ($eleccion === '' || !TurnoResolucionElecciones::esEleccionValida($eleccion)) {
                    throw new BadRequestHttpException('eleccion requerida (antes, despues o cancelar).');
                }
                TurnoResolucionElecciones::requireResolucionPendienteParaTurno(
                    (int) $tid,
                    (int) Yii::$app->user->getIdPersona()
                );

                return ['data' => ['ok' => true, 'id' => (int) $tid, 'eleccion' => strtolower($eleccion)]];
            }
        );
    }

    /**
     * Lista embebible: turnos del paciente en EN_RESOLUCION.
     *
     * GET|POST /api/v1/turnos/elegir-en-resolucion-como-paciente
     */
    public function actionElegirEnResolucionComoPaciente(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'elegir-en-resolucion-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );
        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && ($out['ui_type'] ?? '') === 'ui_json') {
            $idPersona = (int) Yii::$app->user->getIdPersona();
            $rows = TurnoResolucionService::listarEnResolucionParaPaciente($idPersona);
            $items = [];
            foreach ($rows as $row) {
                $items[] = TurnoResolucionService::toListPickerItem($row);
            }
            $out = UiScreenService::withListBlockItems($out, $items);
        }

        return $out;
    }

    /**
     * Mini-UI (`ui_json`): elegir motivo de cancelación (paciente). No persiste el turno; solo valida y cierra el paso del flujo.
     *
     * GET|POST /api/v1/turnos/elegir-motivo-cancelacion-como-paciente
     * Query/body: `id` (id_turnos). POST además `razon_cancelacion` (código PAC_*).
     *
     * @action_name Motivo de cancelación (paciente)
     * @entity Turnos
     * @tags views, ui, turnos, paciente, autogestión
     */
    public function actionElegirMotivoCancelacionComoPaciente(): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            $params = array_merge($req->get(), $req->post());
            $def = UiScreenService::renderUiDefinition('turnos', 'elegir-motivo-cancelacion-como-paciente', $params, null);

            return TurnoCancelacionRazones::aplicarOpcionesRazonEnDefinicionUiJson($def);
        }

        return UiScreenService::handleScreen(
            'turnos',
            'elegir-motivo-cancelacion-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($req): array {
                $tid = $this->resolveTurnoId(null, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $turno = Turno::findActive()->andWhere(['id_turnos' => $tid])->one();
                if (!$turno) {
                    throw new NotFoundHttpException('Turno no encontrado');
                }
                $idPersona = Yii::$app->user->getIdPersona();
                if ((int) $turno->id_persona !== (int) $idPersona) {
                    throw new ForbiddenHttpException('No autorizado');
                }
                $razon = isset($post['razon_cancelacion']) ? trim((string) $post['razon_cancelacion']) : '';
                if ($razon === '' || !TurnoCancelacionRazones::esCodigoPacienteAppValido($razon)) {
                    throw new BadRequestHttpException('Indicá un motivo de cancelación válido (razon_cancelacion).');
                }

                return [
                    'data' => [
                        'ok' => true,
                        'mensaje' => 'Motivo registrado. Podés confirmar la cancelación.',
                        'razon_cancelacion' => $razon,
                    ],
                ];
            }
        );
    }

    /**
     * Horarios alternativos para reprogramar, presentados como bloques `list` (misma UX que slots-disponibles).
     *
     * GET|POST /api/v1/turnos/slots-reprogramar-como-paciente
     * Obligatorio: id (id_turnos del turno a mover). Opcionales: limit, max_dias, mismo_profesional, franja_tarde_desde.
     *
     * @action_name Elegir nuevo horario (reprogramar paciente)
     * @entity Turnos
     * @tags views, ui, turnos, paciente, reprogramar
     * @keywords cambiar horario turno, mover cita, reprogramar turno
     */
    public function actionSlotsReprogramarComoPaciente(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'slots-reprogramar-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        $raw = $req->get('raw') ?: $req->post('raw');
        $wantsRaw = $raw === '1' || $raw === 1 || $raw === true;

        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && isset($out['ui_type']) && $out['ui_type'] === 'ui_json') {
            $params = array_merge($req->get(), $req->post());
            $tid = $this->resolveTurnoId(null, $params, $req);
            if (!$tid) {
                throw new BadRequestHttpException('id del turno requerido');
            }
            $payload = $this->buildSlotsAlternativosPayload($tid, $params);
            if ($wantsRaw) {
                return array_merge(['success' => true], $payload);
            }
            $turno = Turno::findOne($tid);
            $idServicio = $turno ? (int) ($turno->id_servicio_asignado ?? 0) : 0;
            $defaults = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
            $p = Yii::$app->params['turnosPaciente'] ?? [];
            $maxCliente = max(1, (int) ($p['slots_oferta_max_cliente'] ?? 60));
            $limiteRaw = $req->get('limite') ?: $req->post('limite');
            $limite = $limiteRaw !== null && $limiteRaw !== '' ? (int) $limiteRaw : $defaults['limite'];
            $limite = max(1, min($maxCliente, $limite));
            $franjaRaw = $req->get('franja_tarde_desde') ?: $req->post('franja_tarde_desde');
            $franja = $franjaRaw !== null && $franjaRaw !== '' ? (string) $franjaRaw : $defaults['franja_tarde_desde'];
            if (!preg_match('/^\d{2}:\d{2}$/', $franja)) {
                $franja = $defaults['franja_tarde_desde'];
            }
            $plano = isset($payload['slots']) && is_array($payload['slots']) ? $payload['slots'] : [];
            $grouped = TurnoSlotOfferService::buildOfferFromPlano($plano, $franja, $limite, (int) $defaults['max_dias']);
            $blocks = TurnoSlotOfferUiPresenter::buildSlotListBlocks($grouped, $idServicio);
            if ($blocks !== []) {
                $out['blocks'] = $blocks;
            } else {
                $out = UiScreenService::withListBlockItems($out, []);
            }
        }

        return $out;
    }

    /**
     * Respuesta JSON agenda del día (profesional). Usado por {@see ProfesionalAgendaController::actionDia}.
     *
     * @return array<string, mixed>
     */
    public static function agendaDiaResponse(): array
    {
        $request = Yii::$app->request;
        $params = array_merge($request->get(), $request->post());

        return self::buildAgendaDiaPayload($params);
    }

    /**
     * @param array<string, mixed> $params fecha, id_profesional_efector_servicio (opc.)
     * @return array<string, mixed>
     */
    public static function buildAgendaDiaPayload(array $params): array
    {
        $fecha = isset($params['fecha']) && $params['fecha'] !== ''
            ? (string) $params['fecha']
            : date('Y-m-d');
        $pesOverride = $params['id_profesional_efector_servicio'] ?? null;
        $pesId = ($pesOverride !== null && $pesOverride !== '') ? (int) $pesOverride : null;
        if ($pesId !== null && $pesId <= 0) {
            $pesId = null;
        }

        $pesSesion = Yii::$app->user->getIdProfesionalEfectorServicio();
        $pesEfectivo = $pesId ?? ($pesSesion !== null && $pesSesion !== '' ? (int) $pesSesion : 0);

        $idProfQuery = ($pesOverride !== null && $pesOverride !== '') ? (int) $pesOverride : 0;
        $idContextoProfesional = $idProfQuery > 0
            ? $idProfQuery
            : (int) (Yii::$app->user->getIdProfesionalEfectorServicio() ?: 0);

        if ($idContextoProfesional <= 0 && $pesEfectivo <= 0) {
            throw new BadRequestHttpException(
                'Indique id_profesional_efector_servicio o fije sesión operativa con contexto profesional.'
            );
        }
        try {
            return PacientesController::agendaAmbulatorioJson($fecha, $idContextoProfesional, true, $pesId);
        } catch (\Throwable $e) {
            Yii::error('TurnosController::buildAgendaDiaPayload: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api-turnos');
            throw new ServerErrorHttpException('Error al obtener turnos: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Detalle de un turno por id. GET /api/v1/turnos/{id}. RBAC: /api/turnos/ver-turno
     */
    public function actionVerTurno($id = null)
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'ver-turno',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);

                return ['data' => $this->buildVerTurnoPayload($tid)];
            }
        );
    }

    // (actionCrearComoPaciente unificado como screen UI JSON + submit; ver arriba)

    /**
     * Alta de turno en gestión operativa: el beneficiario es el paciente indicado en el cuerpo (id_persona obligatorio).
     *
     * HTTP: POST /api/v1/turnos/para-paciente. RBAC: /api/turnos/crear-para-paciente (no asignar al rol paciente).
     */
    public function actionCrearParaPaciente()
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'crear-para-paciente',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $model = new Turno();
                $model->load($post, '');

                return $this->ejecutarCreacionTurno($model);
            }
        );
    }

    /**
     * Delega en {@see TurnoPersistService}; traduce excepciones de dominio a HTTP.
     *
     * @return array<string, mixed> ver {@see TurnoPersistService::crear}
     */
    protected function ejecutarCreacionTurno(Turno $model): array
    {
        try {
            return (new TurnoPersistService())->crear($model, TurnoCreacionContext::fromCurrentUser());
        } catch (PolicyModeradaException $e) {
            throw new ConflictHttpException($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * Actualizar turno existente. PUT/PATCH /api/v1/turnos/{id}. RBAC: /api/turnos/actualizar-turno
     */
    public function actionActualizarTurno($id = null)
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'actualizar-turno',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $turno = Turno::findOne($tid);
                if (!$turno) {
                    throw new NotFoundHttpException('Turno no encontrado');
                }
                $oldTipo = $turno->tipo_atencion;
                $turno->load($post, '');
                try {
                    (new TurnoPersistService())->validateUpdateTeleconsultaTransition($turno, $oldTipo);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
                if (!$turno->save()) {
                    return $this->error('Validación fallida.', $turno->errors, 422);
                }

                return [
                    'data' => [
                        'id' => $turno->id_turnos,
                        'estado' => $turno->estado,
                        'tipo_atencion' => $turno->tipo_atencion,
                        'id_profesional_efector_servicio' => (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
                        'servicio_detalle' => $turno->getServicioEmbebidoParaApi(),
                    ],
                ];
            }
        );
    }

    /**
     * Política de autogestión (cancelaciones) para el paciente autenticado y efector actual.
     * GET /api/v1/turnos/politica-como-paciente. RBAC: /api/turnos/politica-como-paciente
     */
    public function actionPoliticaComoPaciente()
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'politica-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($req): array {
                $idEfector = $post['id_efector'] ?? $req->get('id_efector');
                if ($idEfector === null || $idEfector === '') {
                    $idEfector = Yii::$app->user->getIdEfector();
                }
                if (!$idEfector) {
                    throw new BadRequestHttpException(
                        'Indicá id_efector en el formulario o establecé sesión operativa (sesion-operativa/establecer).'
                    );
                }
                $idPersona = (int) Yii::$app->user->getIdPersona();
                $svc = new \common\components\Scheduling\Service\TurnoCancellationPolicyService();

                return ['data' => array_merge(['success' => true], $svc->evaluarAutogestion($idPersona, (int) $idEfector))];
            }
        );
    }

    /**
     * Cancelar turno propio (autogestión). Solo POST: body `id`, `razon_cancelacion`, `canal` (opcional).
     * El motivo se elige en {@see self::actionElegirMotivoCancelacionComoPaciente()} dentro del flujo del asistente.
     * El turno queda con estado_motivo CANCELADO_X_PACIENTE; la razón va en auditoría (`meta_json`).
     * RBAC: /api/turnos/cancelar-como-paciente
     */
    public function actionCancelarComoPaciente($id = null)
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'La cancelación se confirma por POST con id y razon_cancelacion.');
        }

        $out = UiScreenService::handleScreen(
            'turnos',
            'cancelar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }

                return ['data' => $this->cancelarComoPacienteCore($tid, $post)];
            }
        );
        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && ($out['success'] ?? true) === false) {
            return TurnoCancelacionRazones::aplicarOpcionesRazonEnDefinicionUiJson($out);
        }

        return $out;
    }

    /**
     * Cancelar turno en gestión operativa (staff). POST /api/v1/turnos/{id}/cancelar-operativo.
     * Body: estado_motivo (opcional), canal (opcional).
     * RBAC: /api/turnos/cancelar-operativo
     */
    public function actionCancelarOperativo($id = null)
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            $def = UiScreenService::renderUiDefinition(
                'turnos',
                'cancelar-operativo',
                array_merge($req->get(), $req->post()),
                null
            );

            return TurnoCancelacionRazones::aplicarOpcionesRazonMedicoEnDefinicionUiJson($def);
        }

        return UiScreenService::handleScreen(
            'turnos',
            'cancelar-operativo',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $turno = Turno::findActive()->andWhere(['id_turnos' => $tid])->one();
                if (!$turno) {
                    throw new NotFoundHttpException('Turno no encontrado');
                }
                $idEfector = Yii::$app->user->getIdEfector();
                if ($idEfector && (int) $turno->id_efector !== (int) $idEfector) {
                    throw new ForbiddenHttpException('No autorizado');
                }
                $razon = trim((string) ($post['razon_cancelacion'] ?? ''));
                if ($razon === '') {
                    throw new BadRequestHttpException('razon_cancelacion requerida.');
                }
                $canal = $post['canal'] ?? 'web';
                $result = TurnoResolucionService::gestionarCancelacionStaff(
                    $turno,
                    $razon,
                    (string) $canal,
                    Yii::$app->user->id ?? null
                );

                return ['data' => array_merge(['success' => true], $result)];
            }
        );
    }

    /**
     * Slots alternativos para reprogramar turno propio. GET …/turnos/{id}/slots-alternativos.
     * RBAC: /api/turnos/slots-alternativos-como-paciente
     */
    public function actionSlotsAlternativosComoPaciente($id = null)
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'slots-alternativos-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $params = array_merge($req->get(), $post);

                return ['data' => $this->buildSlotsAlternativosPayload($tid, $params)];
            }
        );
    }

    /**
     * Confirmar asistencia al turno propio. POST …/turnos/{id}/confirmar-asistencia (body token opcional).
     * RBAC: /api/turnos/confirmar-asistencia-como-paciente
     */
    public function actionConfirmarAsistenciaComoPaciente($id = null)
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'confirmar-asistencia-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $turno = Turno::findActive()->andWhere(['id_turnos' => $tid])->one();
                if (!$turno) {
                    throw new NotFoundHttpException('Turno no encontrado');
                }
                $idPersona = Yii::$app->user->getIdPersona();
                if ((int) $turno->id_persona !== (int) $idPersona) {
                    throw new ForbiddenHttpException('No autorizado');
                }
                $token = $post['token'] ?? null;
                if ($token && $turno->confirmacion_token && !hash_equals((string) $turno->confirmacion_token, (string) $token)) {
                    throw new BadRequestHttpException('Token inválido');
                }
                (new TurnoConfirmationService())->confirmarAsistencia($turno, Yii::$app->user->id ?? null);

                return ['data' => ['success' => true, 'message' => 'Asistencia confirmada']];
            }
        );
    }

    /**
     * Reprogramar turno propio. Solo POST: body `id`, `slot_id` (o `fecha`/`hora`/`id_profesional_efector_servicio`).
     * El nuevo horario se elige en {@see self::actionSlotsReprogramarComoPaciente()} dentro del flujo del asistente.
     * RBAC: /api/turnos/reprogramar-como-paciente
     */
    /**
     * Resuelve conflicto de agenda (cambio de intervalo del profesional).
     * POST /api/v1/turnos/resolver-conflicto-agenda-como-paciente
     * Body: id (id_turnos), eleccion: antes|despues|cancelar
     *
     * RBAC: /api/turnos/resolver-conflicto-agenda-como-paciente
     */
    public function actionResolverConflictoAgendaComoPaciente($id = null): array
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'Solo POST con id y eleccion (antes|despues|cancelar).');
        }
        $post = array_merge($req->get(), $req->post());
        $tid = $this->resolveTurnoId($id, $post, $req);
        if (!$tid) {
            throw new BadRequestHttpException('id del turno requerido');
        }
        $eleccion = trim((string) ($post['eleccion'] ?? ''));
        if ($eleccion === '') {
            throw new BadRequestHttpException('eleccion requerida (antes, despues o cancelar).');
        }

        return [
            'success' => true,
            'data' => TurnoResolucionService::resolverEleccionVecina(
                (int) $tid,
                (int) Yii::$app->user->getIdPersona(),
                $eleccion
            ),
        ];
    }

    public function actionReprogramarComoPaciente($id = null)
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'La reprogramación se confirma por POST con id y slot_id (o fecha, hora e id_profesional_efector_servicio).');
        }

        return UiScreenService::handleScreen(
            'turnos',
            'reprogramar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }

                return ['data' => $this->reprogramarComoPacienteCore($tid, $post, true)];
            }
        );
    }

    /**
     * Horarios para reubicar un turno EN_RESOLUCION (puede cambiar PES/efector vía query).
     *
     * GET|POST /api/v1/turnos/slots-reubicar-como-paciente
     */
    public function actionSlotsReubicarComoPaciente(): array
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'slots-reubicar-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        $raw = $req->get('raw') ?: $req->post('raw');
        $wantsRaw = $raw === '1' || $raw === 1 || $raw === true;

        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && ($out['ui_type'] ?? '') === 'ui_json') {
            $params = array_merge($req->get(), $req->post());
            $tid = $this->resolveTurnoId(null, $params, $req);
            if (!$tid) {
                throw new BadRequestHttpException('id del turno requerido');
            }
            $payload = $this->buildSlotsAlternativosPayload($tid, $params);
            if ($wantsRaw) {
                return array_merge(['success' => true], $payload);
            }
            $turno = Turno::findOne($tid);
            $idServicio = isset($params['id_servicio_asignado']) && (int) $params['id_servicio_asignado'] > 0
                ? (int) $params['id_servicio_asignado']
                : ($turno ? (int) ($turno->id_servicio_asignado ?? 0) : 0);
            $defaults = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
            $p = Yii::$app->params['turnosPaciente'] ?? [];
            $maxCliente = max(1, (int) ($p['slots_oferta_max_cliente'] ?? 60));
            $limiteRaw = $req->get('limite') ?: $req->post('limite');
            $limite = $limiteRaw !== null && $limiteRaw !== '' ? (int) $limiteRaw : $defaults['limite'];
            $limite = max(1, min($maxCliente, $limite));
            $franjaRaw = $req->get('franja_tarde_desde') ?: $req->post('franja_tarde_desde');
            $franja = $franjaRaw !== null && $franjaRaw !== '' ? (string) $franjaRaw : $defaults['franja_tarde_desde'];
            if (!preg_match('/^\d{2}:\d{2}$/', $franja)) {
                $franja = $defaults['franja_tarde_desde'];
            }
            $plano = isset($payload['slots']) && is_array($payload['slots']) ? $payload['slots'] : [];
            $grouped = TurnoSlotOfferService::buildOfferFromPlano($plano, $franja, $limite, (int) $defaults['max_dias']);
            $blocks = TurnoSlotOfferUiPresenter::buildSlotListBlocks($grouped, $idServicio);
            if ($blocks !== []) {
                $out['blocks'] = $blocks;
            } else {
                $out = UiScreenService::withListBlockItems($out, []);
            }
        }

        return $out;
    }

    /**
     * Reubicar turno EN_RESOLUCION (otro horario y opcionalmente otro profesional/efector). Solo POST.
     *
     * POST /api/v1/turnos/reubicar-como-paciente
     */
    public function actionReubicarComoPaciente($id = null)
    {
        $req = Yii::$app->request;
        if (!$req->isPost) {
            throw new MethodNotAllowedHttpException(['POST'], 'La reubicación se confirma por POST con id y slot_id.');
        }

        return UiScreenService::handleScreen(
            'turnos',
            'reubicar-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }

                return [
                    'data' => TurnoResolucionService::reubicarComoPaciente(
                        (int) $tid,
                        (int) Yii::$app->user->getIdPersona(),
                        $post
                    ),
                ];
            }
        );
    }

    /**
     * Marcar "no se presentó" en gestión operativa (staff). POST /api/v1/turnos/{id}/no-se-presento.
     * RBAC: /api/turnos/no-se-presento
     */
    public function actionNoSePresento($id = null)
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'no-se-presento',
            $req->get(),
            $req->post(),
            function (array $post) use ($id, $req): array {
                $tid = $this->resolveTurnoId($id, $post, $req);
                if (!$tid) {
                    throw new BadRequestHttpException('id del turno requerido');
                }
                $this->noSePresentoCore($tid);

                return ['data' => ['success' => true, 'message' => 'El paciente no se presentó']];
            }
        );
    }

    /**
     * Cancelación masiva del día en el efector (AdminEfector). POST …/cancelar-dia-efector.
     * RBAC: /api/turnos/cancelar-dia-efector
     */
    public function actionCancelarDiaEfector()
    {
        if (!\common\models\User::hasRole('AdminEfector')) {
            throw new ForbiddenHttpException('Solo administrador de efector');
        }
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'cancelar-dia-efector',
            $req->get(),
            $req->post(),
            function (array $post): array {
                $fecha = $post['fecha'] ?? null;
                if (!$fecha) {
                    throw new BadRequestHttpException('fecha requerida');
                }
                $idEfector = Yii::$app->user->getIdEfector();
                $idPesRaw = $post['id_profesional_efector_servicio'] ?? null;
                $idPes = $idPesRaw !== null && $idPesRaw !== '' ? (int) $idPesRaw : null;
                if ($idPes !== null && $idPes <= 0) {
                    $idPes = null;
                }
                try {
                    $n = (new BulkCancelDayService())->cancelarDia($idEfector, $fecha, null, Yii::$app->user->id, $idPes);
                } catch (\InvalidArgumentException $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }

                return ['data' => ['success' => true, 'cancelados' => $n]];
            }
        );
    }

    /**
     * Sobreturno urgente en gestión operativa (staff). POST /api/v1/turnos/crear-sobreturno.
     * Body: id_persona, fecha, hora, id_profesional_efector_servicio, id_servicio_asignado, (id_efector opcional).
     * RBAC: /api/turnos/crear-sobreturno
     */
    public function actionCrearSobreturno()
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'crear-sobreturno',
            $req->get(),
            $req->post(),
            function (array $post): array {
                return ['data' => $this->crearSobreturnoCore($post)];
            }
        );
    }

    /**
     * Vista embebible: listar slots disponibles (autogestión paciente) como `ui_definition` (`ui_json`).
     *
     * GET|POST /api/v1/turnos/slots-disponibles-como-paciente
     *
     * Parámetros (query/body):
     * - id_servicio (obligatorio)
     * - id_efector (opcional; si falta, usa sesión)
     * - id_profesional_efector_servicio (opcional)
     * - limite, franja_tarde_desde (opcionales; defaults `turnosPaciente`)
     * - fecha (opcional, `Y-m-d`): solo horarios de ese día (paso 2 del flujo asistente)
     * - restricciones (JSON array; mismo formato que {@see TurnoSlotFinder::findAvailableSlots})
     *
     * Sin `fecha`: búsqueda desde hoy y `max_dias` de configuración. Con `fecha`: un solo día.
     *
     * @action_name Listar horarios disponibles (paciente)
     * @entity Turnos
     * @tags views, ui, slot, horario, turnos, paciente
     * @keywords horarios disponibles, slots libres, próximo turno disponible, elegir horario
     * {@see TurnoSlotOfferService}
     */
    public function actionSlotsDisponiblesComoPaciente()
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'slots-disponibles-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        $raw = $req->get('raw') ?: $req->post('raw');
        $wantsRaw = $raw === '1' || $raw === 1 || $raw === true;

        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && isset($out['ui_type']) && $out['ui_type'] === 'ui_json') {
            $ctx = $this->groupedSlotsDisponiblesPacienteFromRequest($req, false);
            if ($wantsRaw) {
                return array_merge(['success' => true], $ctx['grouped']);
            }

            $blocks = TurnoSlotOfferUiPresenter::buildSlotListBlocks($ctx['grouped'], $ctx['id_servicio']);
            if ($blocks !== []) {
                $out['blocks'] = $this->appendPoliticaAutogestionDespuesDeBloques($blocks, $ctx['id_efector']);
            } else {
                $out = UiScreenService::withListBlockItems($out, []);
            }
        }

        return $out;
    }

    /**
     * Paso 1 autogestión: días con al menos un horario libre (lista corta).
     *
     * GET|POST /api/v1/turnos/slots-dias-disponibles-como-paciente
     * Mismos parámetros base que {@see actionSlotsDisponiblesComoPaciente} (sin `fecha`).
     *
     * @action_name Elegir día con turnos (paciente)
     * @entity Turnos
     * @tags views, ui, slot, turnos, paciente
     * @keywords elegir día turno, días disponibles, calendario turnos
     */
    public function actionSlotsDiasDisponiblesComoPaciente()
    {
        $req = Yii::$app->request;
        $out = UiScreenService::handleScreen(
            'turnos',
            'slots-dias-disponibles-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                return ['data' => ['ok' => true]];
            }
        );

        $raw = $req->get('raw') ?: $req->post('raw');
        $wantsRaw = $raw === '1' || $raw === 1 || $raw === true;

        if (isset($out['kind']) && $out['kind'] === 'ui_definition' && isset($out['ui_type']) && $out['ui_type'] === 'ui_json') {
            $ctx = $this->groupedSlotsDisponiblesPacienteFromRequest($req, true);
            if ($wantsRaw) {
                return array_merge(['success' => true], $ctx['grouped']);
            }

            $blocks = TurnoSlotOfferUiPresenter::buildDayPickerBlocks($ctx['grouped']);
            if ($blocks !== []) {
                $out['blocks'] = $blocks;
            } else {
                $out = UiScreenService::withListBlockItems($out, []);
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   grouped: array<string, mixed>,
     *   id_servicio: int,
     *   id_efector: int
     * }
     */
    private function groupedSlotsDisponiblesPacienteFromRequest(\yii\web\Request $req, bool $ampliarLimiteParaDias): array
    {
        $idServicio = $req->get('id_servicio') ?: $req->post('id_servicio');
        if (!$idServicio) {
            throw new BadRequestHttpException('id_servicio es obligatorio');
        }
        $idEfector = $req->get('id_efector') ?: $req->post('id_efector');
        if (!$idEfector) {
            $idEfector = Yii::$app->user->getIdEfector();
        }
        if (!$idEfector) {
            throw new BadRequestHttpException('No se pudo determinar id_efector');
        }

        $defaults = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
        $criteria = [
            'id_servicio' => (int) $idServicio,
            'id_efector' => (int) $idEfector,
            'fecha_desde' => date('Y-m-d'),
            'min_minutos_desde_ahora' => $defaults['min_minutos_desde_ahora'],
        ];

        $fechaFiltro = trim((string) ($req->get('fecha') ?: $req->post('fecha') ?: ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro) === 1) {
            $criteria['fecha_desde'] = $fechaFiltro;
        }

        $idPesReq = (int) ($req->get('id_profesional_efector_servicio') ?: $req->post('id_profesional_efector_servicio') ?: 0);
        if ($idPesReq > 0) {
            $criteria['id_profesional_efector_servicio'] = $idPesReq;
        }

        $restr = $req->get('restricciones') ?: $req->post('restricciones');
        if (is_string($restr) && $restr !== '') {
            $decoded = json_decode($restr, true);
            if (is_array($decoded)) {
                $criteria['restricciones'] = $decoded;
            }
        } elseif (is_array($restr)) {
            $criteria['restricciones'] = $restr;
        }

        $p = Yii::$app->params['turnosPaciente'] ?? [];
        $maxCliente = max(1, (int) ($p['slots_oferta_max_cliente'] ?? 60));

        $limiteRaw = $req->get('limite') ?: $req->post('limite');
        $limite = $limiteRaw !== null && $limiteRaw !== '' ? (int) $limiteRaw : $defaults['limite'];
        $limite = max(1, min($maxCliente, $limite));

        $maxDias = (int) $defaults['max_dias'];
        $maxDias = max(1, min(90, $maxDias));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFiltro) === 1) {
            $maxDias = 1;
        }

        if ($ampliarLimiteParaDias) {
            $limite = min($maxCliente, max($limite, $maxDias * 24));
        }

        $franjaRaw = $req->get('franja_tarde_desde') ?: $req->post('franja_tarde_desde');
        $franja = $franjaRaw !== null && $franjaRaw !== '' ? (string) $franjaRaw : $defaults['franja_tarde_desde'];
        if (!preg_match('/^\d{2}:\d{2}$/', $franja)) {
            $franja = $defaults['franja_tarde_desde'];
        }

        try {
            $grouped = TurnoSlotOfferService::buildGrouped($criteria, $limite, $maxDias, $franja);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return [
            'grouped' => $grouped,
            'id_servicio' => (int) $idServicio,
            'id_efector' => (int) $idEfector,
        ];
    }

    /**
     * Leyenda de política solo en el paso de horarios, debajo de los listados (SPA ordena por display_order).
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function appendPoliticaAutogestionDespuesDeBloques(array $blocks, int $idEfector): array
    {
        return array_merge($blocks, [$this->bloqueLeyendaPoliticaAutogestionPaciente($idEfector)]);
    }

    /** @return array<string, mixed> */
    private function bloqueLeyendaPoliticaAutogestionPaciente(int $idEfector): array
    {
        $anticipSvc = new TurnoAutogestionAnticipacionService();
        $leyenda = $anticipSvc->textoLeyendaPoliticaAutogestionApp($idEfector);

        return [
            'kind' => 'fields',
            'id' => 'politica_autogestion',
            'display_order' => 10000,
            'title' => 'Política de cancelación y reprogramación',
            'hide_submit' => true,
            'fields' => [
                [
                    'name' => '_leyenda_politica_autogestion',
                    'type' => 'textarea',
                    'label' => '',
                    'rows' => 4,
                    'required' => false,
                    'readonly' => true,
                    'value' => $leyenda,
                    'include_in_submit' => false,
                ],
            ],
        ];
    }

    /**
     * Ocupación de agenda por día (horarios tomados / eventos calendario). GET|POST …/turnos/eventos.
     * RBAC: /api/turnos/consultar-ocupacion-dia
     */
    public function actionConsultarOcupacionDia()
    {
        $req = Yii::$app->request;
        return UiScreenService::handleScreen(
            'turnos',
            'consultar-ocupacion-dia',
            $req->get(),
            $req->post(),
            function (array $post) use ($req): array {
                $params = array_merge($req->get(), $post);

                return ['data' => $this->buildConsultarOcupacionDiaPayload($params)];
            }
        );
    }

    /**
     * @param int|string|null $routeId id desde regla REST
     * @param array<string, mixed> $post
     * @return int|null
     */
    /**
     * @param mixed $value
     */
    protected function isTruthyQueryParam($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $v = strtolower(trim($value));

        return in_array($v, ['1', 'true', 'yes', 'si', 'sí'], true);
    }

    /**
     * @param int|string|null $routeId id desde regla REST
     * @param array<string, mixed> $post
     * @return int|null
     */
    protected function resolveTurnoId($routeId, array $post, $req = null)
    {
        if ($routeId !== null && $routeId !== '') {
            return (int) $routeId;
        }
        if ($req === null) {
            $req = Yii::$app->request;
        }
        $v = $post['id'] ?? $req->get('id');

        return $v !== null && $v !== '' ? (int) $v : null;
    }

    /**
     * @param array<string, mixed> $params query o post mezclados
     * @return array<string, mixed>
     */
    protected function listarComoPacienteData(array $params): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $alcance = isset($params['alcance']) ? (string) $params['alcance'] : '';

        if ($alcance === 'pendientes' || $alcance === 'pasados' || $alcance === 'en_resolucion') {
            $limit = isset($params['limit']) && $params['limit'] !== '' ? (int) $params['limit'] : 20;
            $limit = max(1, min(100, $limit));
            $offset = isset($params['offset']) && $params['offset'] !== '' ? (int) $params['offset'] : 0;
            $offset = max(0, $offset);

            $ahoraLocal = $this->ahoraLocalParaComparacionTurno();
            $hoyProducto = $this->hoyProductoParaTurnos();

            if ($alcance === 'en_resolucion') {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['t.estado' => Turno::ESTADO_EN_RESOLUCION])
                    ->andWhere(['>=', 't.fecha', $hoyProducto])
                    ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);
            } elseif ($alcance === 'pendientes') {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['t.estado' => Turno::ESTADO_PENDIENTE])
                    ->andWhere(['>=', 't.fecha', $hoyProducto]);

                if (isset($params['fecha_hasta']) && $params['fecha_hasta'] !== '') {
                    $turnosQ->andWhere(['<=', 't.fecha', $params['fecha_hasta']]);
                } else {
                    $turnosQ->andWhere(['<=', 't.fecha', date('Y-m-d', strtotime($hoyProducto . ' +3 months'))]);
                }
                $turnosQ->orderBy(['t.fecha' => SORT_ASC, 't.hora' => SORT_ASC]);
            } else {
                $turnosQ = Turno::findActive()->alias('t')
                    ->where(['t.id_persona' => $idPersona])
                    ->andWhere(['<', new Expression('TIMESTAMP(t.fecha, t.hora)'), $ahoraLocal])
                    ->andWhere([
                        'or',
                        ['<', 't.fecha', $hoyProducto],
                        ['not', ['t.estado' => Turno::ESTADO_PENDIENTE]],
                        ['not', ['t.estado' => Turno::ESTADO_EN_RESOLUCION]],
                    ]);

                if (isset($params['fecha_hasta']) && $params['fecha_hasta'] !== '') {
                    $turnosQ->andWhere(['<=', 't.fecha', $params['fecha_hasta']]);
                }
                if (isset($params['fecha_desde']) && $params['fecha_desde'] !== '') {
                    $turnosQ->andWhere(['>=', 't.fecha', $params['fecha_desde']]);
                }
                $turnosQ->orderBy(['t.fecha' => SORT_DESC, 't.hora' => SORT_DESC]);
            }

            $total = (int) (clone $turnosQ)->count('*');
            $turnos = $turnosQ->limit($limit)->offset($offset)->all();

            $policySvc = new TurnoCancellationPolicyService();
            $anticipSvc = new TurnoAutogestionAnticipacionService();
            $policyOkPorEfector = [];

            $formattedTurnos = [];
            foreach ($turnos as $turno) {
                $row = $this->formatTurnoPacienteListadoRow($turno);
                if (
                    ($alcance === 'pendientes' && $turno->estado === Turno::ESTADO_PENDIENTE)
                    || ($alcance === 'en_resolucion' && $turno->estado === Turno::ESTADO_EN_RESOLUCION)
                ) {
                    if ($alcance === 'en_resolucion') {
                        $row['puede_cancelar_autogestion_app'] = true;
                        $row['puede_reprogramar_autogestion_app'] = true;
                        $formattedTurnos[] = $row;
                        continue;
                    }
                    $idEf = (int) ($turno->id_efector ?? 0);
                    if (!array_key_exists($idEf, $policyOkPorEfector)) {
                        $policyOkPorEfector[$idEf] = $idEf <= 0 || !$policySvc->autogestionBloqueada($idPersona, $idEf);
                    }
                    $hC = $anticipSvc->minHorasAntesCancelarParaEfector($idEf);
                    $hR = $anticipSvc->minHorasAntesReprogramarParaEfector($idEf);
                    $row['puede_cancelar_autogestion_app'] = $policyOkPorEfector[$idEf]
                        && $anticipSvc->ahoraEsAntesDeLimite($turno, $hC);
                    $row['puede_reprogramar_autogestion_app'] = $policyOkPorEfector[$idEf]
                        && $anticipSvc->ahoraEsAntesDeLimite($turno, $hR);
                } else {
                    $row['puede_cancelar_autogestion_app'] = false;
                    $row['puede_reprogramar_autogestion_app'] = false;
                }
                $formattedTurnos[] = $row;
            }

            return [
                'turnos' => $formattedTurnos,
                'total' => $total,
                'alcance' => $alcance,
                'limit' => $limit,
                'offset' => $offset,
            ];
        }

        $fechaDesde = isset($params['fecha_desde']) && $params['fecha_desde'] !== ''
            ? $params['fecha_desde'] : date('Y-m-d');
        $fechaHasta = isset($params['fecha_hasta']) && $params['fecha_hasta'] !== ''
            ? $params['fecha_hasta'] : date('Y-m-d', strtotime('+3 months'));

        $turnosQ = Turno::findActive()->where(['id_persona' => $idPersona])
            ->andWhere(['>=', 'fecha', $fechaDesde])
            ->andWhere(['<=', 'fecha', $fechaHasta])
            ->andWhere(['estado' => Turno::ESTADO_PENDIENTE])
            ->orderBy(['fecha' => SORT_ASC, 'hora' => SORT_ASC]);
        $turnos = $turnosQ->all();

        $formattedTurnos = [];
        foreach ($turnos as $turno) {
            $formattedTurnos[] = $this->formatTurnoPacienteListadoRow($turno);
        }

        return [
            'turnos' => $formattedTurnos,
            'total' => count($formattedTurnos),
        ];
    }

    /**
     * Instante actual en {@see Yii::$app->timeZone} para comparar con TIMESTAMP(fecha, hora).
     * `fecha`/`hora` del turno son hora local del producto; MySQL NOW() suele estar en UTC y desplaza el filtro.
     */
    protected function ahoraLocalParaComparacionTurno(): string
    {
        try {
            $tz = new \DateTimeZone(Yii::$app->timeZone);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    }

    /**
     * Fecha calendario de hoy en zona producto (`Y-m-d`), para listados paciente por día de turno.
     */
    protected function hoyProductoParaTurnos(): string
    {
        try {
            $tz = new \DateTimeZone(Yii::$app->timeZone);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    /**
     * Hora legible en listados paciente (HH:mm, sin segundos).
     */
    protected function formatHoraTurnoPacienteCorta(?string $hora): string
    {
        if ($hora === null || trim($hora) === '') {
            return '';
        }
        $t = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        if (strlen($t) >= 5) {
            return substr($t, 0, 5);
        }

        return $t;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTurnoPacienteListadoRow(Turno $turno): array
    {
        $servicioNombre = $turno->getNombreServicioParaDisplay();
        $servicioObj = $turno->getServicioEmbebidoParaApi();
        $encounter = Encounter::findOne(['appointment_id' => $turno->id_turnos]);
        $encounterId = $encounter ? (int) $encounter->id : null;
        $profPersona = $turno->getProfesionalPersonaParaDisplay();
        $profesional = $profPersona
            ? $profPersona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D)
            : null;
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $idEf = $turno->id_efector !== null && (int) $turno->id_efector > 0 ? (int) $turno->id_efector : null;

        $resolucion = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);

        return [
            'id' => $turno->id_turnos,
            'id_persona' => $turno->id_persona,
            'fecha' => $turno->fecha,
            'hora' => $this->formatHoraTurnoPacienteCorta($turno->hora),
            'servicio' => $servicioNombre,
            'servicio_detalle' => $servicioObj,
            'id_servicio_asignado' => $turno->id_servicio_asignado,
            'id_profesional_efector_servicio' => $idPes > 0 ? $idPes : null,
            'id_efector' => $idEf,
            'estado' => $turno->estado,
            'estado_label' => Turno::ESTADOS[$turno->estado] ?? 'Sin estado',
            'tipo_atencion' => isset($turno->tipo_atencion) ? $turno->tipo_atencion : Turno::TIPO_ATENCION_PRESENCIAL,
            'encounter_id' => $encounterId,
            'id_consulta' => $encounterId,
            'motivos_input_abierto' => $encounterId !== null
                && AppointmentReasonWindowService::isInputOpen($encounterId),
            'motivos_cierre_minutos' => AppointmentReasonWindowService::minutesBeforeClose(),
            'profesional' => $profesional,
            'created_at' => $turno->created_at,
            'en_resolucion' => $turno->estado === Turno::ESTADO_EN_RESOLUCION,
            'turno_resolucion' => $resolucion !== null ? $resolucion->toPacienteApiArray() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildVerTurnoPayload($id)
    {
        if (!$id) {
            throw new BadRequestHttpException('id del turno requerido');
        }
        $turno = Turno::findOne($id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $paciente = $turno->paciente;
        $servicioNombre = $turno->getNombreServicioParaDisplay();
        $servicioObj = $turno->getServicioEmbebidoParaApi();
        return [
            'id' => $turno->id_turnos,
            'id_persona' => $turno->id_persona,
            'paciente' => [
                'id' => $paciente ? $paciente->id_persona : null,
                'nombre_completo' => $paciente ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D) : 'Sin paciente',
                'documento' => $paciente ? $paciente->documento : null,
                'fecha_nacimiento' => $paciente ? $paciente->fecha_nacimiento : null,
                'edad' => $paciente ? $paciente->edad : null,
            ],
            'fecha' => $turno->fecha,
            'hora' => $turno->hora,
            'servicio' => $servicioNombre,
            'servicio_detalle' => $servicioObj,
            'id_servicio_asignado' => $turno->id_servicio_asignado,
            'id_profesional_efector_servicio' => (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
            'estado' => $turno->estado,
            'estado_label' => $turno->estado ? (Turno::ESTADOS[$turno->estado] ?? 'Sin estado') : 'Sin estado',
            'estado_motivo' => $turno->estado_motivo,
            'atendido' => $turno->atendido,
            'id_efector' => $turno->id_efector,
            'parent_class' => $turno->parent_class,
            'parent_id' => $turno->parent_id,
            'created_at' => $turno->created_at,
            'updated_at' => $turno->updated_at,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, slots: mixed}
     */
    protected function buildSlotsAlternativosPayload($id, array $params)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        if (
            $turno->estado !== Turno::ESTADO_PENDIENTE
            && $turno->estado !== Turno::ESTADO_EN_RESOLUCION
        ) {
            throw new BadRequestHttpException('El turno no admite cambio de horario.');
        }
        $enResolucion = $turno->estado === Turno::ESTADO_EN_RESOLUCION;
        if (!$enResolucion) {
            try {
                (new TurnoAutogestionAnticipacionService())->assertPuedeReprogramarPorApp($turno);
            } catch (AutogestionAnticipacionException $e) {
                throw new ConflictHttpException($e->getMessage());
            }
        }
        $defaultsSlots = TurnoSlotOfferService::leerDefaultsTurnosPaciente();
        $limit = isset($params['limit']) && $params['limit'] !== '' ? (int) $params['limit'] : $defaultsSlots['limite'];
        $mismoDefault = $enResolucion ? '0' : '1';
        $mismoRaw = $params['mismo_profesional'] ?? $mismoDefault;
        $mismoProf = $mismoRaw === '1' || $mismoRaw === 1 || $mismoRaw === true;
        $idEfector = isset($params['id_efector']) && (int) $params['id_efector'] > 0
            ? (int) $params['id_efector']
            : (int) $turno->id_efector;
        $idServicio = isset($params['id_servicio_asignado']) && (int) $params['id_servicio_asignado'] > 0
            ? (int) $params['id_servicio_asignado']
            : (int) $turno->id_servicio_asignado;
        $criteria = [
            'id_servicio' => $idServicio,
            'id_efector' => $idEfector,
            'fecha_desde' => date('Y-m-d'),
            'max_dias' => isset($params['max_dias']) && $params['max_dias'] !== ''
                ? (int) $params['max_dias']
                : $defaultsSlots['max_dias'],
            'min_minutos_desde_ahora' => $defaultsSlots['min_minutos_desde_ahora'],
        ];
        $idPesParam = isset($params['id_profesional_efector_servicio']) ? (int) $params['id_profesional_efector_servicio'] : 0;
        if ($idPesParam > 0) {
            $criteria['id_profesional_efector_servicio'] = $idPesParam;
        } elseif ($mismoProf && (int) $turno->id_profesional_efector_servicio > 0) {
            $criteria['id_profesional_efector_servicio'] = (int) $turno->id_profesional_efector_servicio;
        }
        $slots = TurnoSlotFinder::findAvailableSlots($criteria, max(1, $limit));
        return ['success' => true, 'slots' => $slots];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    protected function cancelarComoPacienteCore($id, array $post)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        $razon = isset($post['razon_cancelacion']) ? trim((string) $post['razon_cancelacion']) : '';
        if ($razon === '' && (($post['estado_motivo'] ?? '') === Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE)) {
            $razon = TurnoCancelacionRazones::COD_PAC_OTRO;
        }
        if ($razon === '' || !TurnoCancelacionRazones::esCodigoPacienteAppValido($razon)) {
            throw new BadRequestHttpException('Indicá un motivo de cancelación válido (razon_cancelacion).');
        }
        $canal = $post['canal'] ?? 'app';
        $life = new TurnoLifecycleService();
        try {
            if ($turno->estado !== Turno::ESTADO_EN_RESOLUCION) {
                (new TurnoAutogestionAnticipacionService())->assertPuedeCancelarPorApp($turno);
            }
            $life->cancelar(
                $turno,
                Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE,
                $canal,
                Yii::$app->user->id ?? null,
                [
                    'razon_cancelacion' => $razon,
                    'razon_cancelacion_label' => TurnoCancelacionRazones::etiquetaPacienteApp($razon),
                ],
                false
            );
        } catch (PolicyModeradaException $e) {
            throw $e;
        } catch (AutogestionAnticipacionException $e) {
            throw new ConflictHttpException($e->getMessage());
        }
        return ['success' => true, 'message' => 'Turno cancelado'];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    protected function reprogramarComoPacienteCore($id, array $post, $forUiSubmit = false)
    {
        $turno = Turno::findActive()->andWhere(['id_turnos' => $id])->one();
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }
        $idPersona = Yii::$app->user->getIdPersona();
        if ((int) $turno->id_persona !== (int) $idPersona) {
            throw new ForbiddenHttpException('No autorizado');
        }
        if ($turno->estado === Turno::ESTADO_EN_RESOLUCION) {
            throw new BadRequestHttpException('Este turno está en resolución: usá el flujo de reubicación.');
        }
        $policy = new \common\components\Scheduling\Service\TurnoCancellationPolicyService();
        if ($policy->autogestionBloqueada($idPersona, (int) $turno->id_efector)) {
            if ($forUiSubmit) {
                throw new PolicyModeradaException('Reprogramación por app no disponible: acercate al efector o llamá.');
            }
            Yii::$app->response->statusCode = 409;
            return [
                'success' => false,
                'code' => 'REPROGRAM_POLICY_MODERADA',
                'message' => 'Reprogramación por app no disponible: acercate al efector o llamá.',
            ];
        }
        try {
            (new TurnoAutogestionAnticipacionService())->assertPuedeReprogramarPorApp($turno);
        } catch (AutogestionAnticipacionException $e) {
            if ($forUiSubmit) {
                throw new ConflictHttpException($e->getMessage());
            }
            Yii::$app->response->statusCode = 409;
            return [
                'success' => false,
                'code' => 'REPROGRAM_ANTICIPACION',
                'message' => $e->getMessage(),
            ];
        }
        $fecha = $post['fecha'] ?? null;
        $hora = $post['hora'] ?? null;
        $idPesPost = isset($post['id_profesional_efector_servicio']) ? (int) $post['id_profesional_efector_servicio'] : 0;
        if (!$fecha || !$hora) {
            throw new BadRequestHttpException('fecha y hora requeridos');
        }
        $idEfectorTurno = (int) $turno->id_efector;
        if ($idEfectorTurno <= 0) {
            $idEfectorTurno = (int) Yii::$app->user->getIdEfector();
        }
        if ($idPesPost <= 0) {
            throw new BadRequestHttpException('id_profesional_efector_servicio es requerido para reprogramar.');
        }
        $pesPost = ProfesionalEfectorServicio::findOne(['id' => $idPesPost, 'deleted_at' => null]);
        if (
            $pesPost === null
            || (int) $pesPost->id_efector !== $idEfectorTurno
        ) {
            throw new BadRequestHttpException('id_profesional_efector_servicio inválido para este turno');
        }
        $turno->id_profesional_efector_servicio = $idPesPost;
        $turno->fecha = $fecha;
        $turno->hora = $hora;
        try {
            TurnoReservaSlotService::aplicarCamposReserva($turno, (int) $turno->id_turnos);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        if (!$turno->save()) {
            throw new BadRequestHttpException('No se pudo guardar el turno.');
        }
        \common\models\TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);
        try {
            $conf = new TurnoConfirmationService();
            $conf->ensureConfirmacionToken($turno);
            $conf->programarNotificaciones($turno);
        } catch (\Throwable $e) {
            Yii::warning('reprogramar notif: ' . $e->getMessage(), 'api-turnos');
        }
        return ['success' => true, 'id' => $turno->id_turnos, 'fecha' => $turno->fecha, 'hora' => $turno->hora];
    }

    protected function noSePresentoCore($id)
    {
        $turno = Turno::findOne((int) $id);
        if (!$turno) {
            throw new NotFoundHttpException('Turno no encontrado');
        }

        $idServicio = Yii::$app->user->getServicioActual();
        $idPesSesion = (int) Yii::$app->user->getIdProfesionalEfectorServicio();

        if (
            $idPesSesion > 0
            && (int) $turno->id_profesional_efector_servicio === $idPesSesion
        ) {
            Turno::NoSePresento($turno->id_turnos);
            return;
        }

        if (
            ((int) $turno->id_profesional_efector_servicio === 0 || $turno->id_profesional_efector_servicio === null)
            && $idServicio
            && (int) $turno->id_servicio_asignado === (int) $idServicio
        ) {
            Turno::NoSePresento($turno->id_turnos);
            return;
        }

        throw new ForbiddenHttpException('No autorizado');
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    protected function crearSobreturnoCore(array $post)
    {
        $model = new Turno();
        $model->load($post, '');

        if (!$model->id_efector) {
            $model->id_efector = Yii::$app->user->getIdEfector();
        }
        if (!$model->id_servicio) {
            $model->id_servicio = Yii::$app->user->getServicioActual();
        }
        if (!$model->id_profesional_efector_servicio) {
            $idPesSess = Yii::$app->user->getIdProfesionalEfectorServicio();
            if ($idPesSess) {
                $model->id_profesional_efector_servicio = (int) $idPesSess;
            } else {
                $idServicioParaPes = (int) ($model->id_servicio_asignado ?: $model->id_servicio);
                if ($model->id_efector && $idServicioParaPes && Yii::$app->user->getIdPersona()) {
                    $idFound = ProfesionalEfectorServicio::findIdByPersonaEfectorServicio(
                        (int) Yii::$app->user->getIdPersona(),
                        (int) $model->id_efector,
                        $idServicioParaPes
                    );
                    if ($idFound) {
                        $model->id_profesional_efector_servicio = $idFound;
                    }
                }
            }
        }

        $model->es_sobreturno = true;

        $cpsQ = ConsultaDerivaciones::getDerivacionesPorPersona(
            $model->id_persona,
            $model->id_efector,
            $model->id_servicio_asignado,
            ConsultaDerivaciones::ESTADO_EN_ESPERA
        );
        $cps = $cpsQ instanceof \yii\db\ActiveQuery ? $cpsQ->all() : (is_array($cpsQ) ? $cpsQ : []);
        if (count($cps) > 0) {
            $parent_id = null;
            foreach ($cps as $cp) {
                \common\components\Clinical\Service\ReferralRequestService::markBooked($cp);
                $parent_id = $cp->id;
            }
            $model->parent_class = Encounter::PARENT_DERIVACION;
            $model->parent_id = $parent_id;
        }

        /** @var \yii\db\ActiveQuery $seQ */
        $seQ = ServiciosEfector::find();
        $seQ->where(['id_servicio' => $model->id_servicio_asignado])
            ->andWhere(['id_efector' => $model->id_efector])
            ->andWhere(['deleted_at' => null]);
        $servicioEfector = $seQ->one();
        if ($servicioEfector) {
            if ($servicioEfector->formas_atencion == ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS) {
                $model->scenario = ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS;
            } elseif ($servicioEfector->formas_atencion == ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL) {
                $model->scenario = ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL;
            }
        }

        if (!$model->save()) {
            throw new BadRequestHttpException('No se pudo guardar el turno.');
        }
        (new EncounterLifecycleService())->ensureFromTurno($model);
        try {
            (new SobreturnoService())->notificarRetrasoPorSobreturno($model);
            (new TurnoLifecycleService())->afterTurnoCreado($model);
        } catch (\Throwable $e) {
            Yii::warning('sobreturno post: ' . $e->getMessage(), 'api-turnos');
        }
        return ['success' => true, 'id_turno' => $model->id_turnos];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function buildConsultarOcupacionDiaPayload(array $params)
    {
        $dia = $params['dia'] ?? date('Y-m-d');
        $id_servicio = $params['id_servicio'] ?? null;
        $id_efector = Yii::$app->user->getIdEfector();

        $idPesParam = isset($params['id_profesional_efector_servicio']) ? (int) $params['id_profesional_efector_servicio'] : 0;
        if ($idPesParam <= 0) {
            $sPes = Yii::$app->user->getIdProfesionalEfectorServicio();
            $idPesParam = $sPes !== null && $sPes !== '' ? (int) $sPes : 0;
        }
        $formatoSlots = isset($params['formato']) && $params['formato'] === 'slots';

        $turnosQuery = Turno::findActive();
        if ($idPesParam > 0) {
            $turnosQuery->andWhere(['id_profesional_efector_servicio' => $idPesParam]);
        } else {
            $turnosQuery->andWhere(['id_efector' => $id_efector])
                ->andWhere(['id_servicio_asignado' => $id_servicio]);
        }

        $turnos = $turnosQuery->andWhere(['fecha' => $dia])
            ->andWhere(['estado' => Turno::ESTADOS_PARA_DESHABILITAR])
            ->orderBy('hora')
            ->all();

        $feriado = AgendaFeriados::getFeriadosPorFecha($dia);
        $mensajeFeriado = '';

        if ($feriado != null) {
            $mensajeFeriado = '<h5 class="ps-5"><u><strong>No se pueden asignar turnos para un dia feriado.</strong></u></h5>';
        }

        $horasTomadas = [];
        foreach ($turnos as $turno) {
            $horasTomadas[] = $turno->hora;
        }

        if ($formatoSlots) {
            return [
                'dia' => $dia,
                'id_profesional_efector_servicio' => $idPesParam > 0 ? $idPesParam : null,
                'id_servicio' => $id_servicio,
                'horas_tomadas' => $horasTomadas,
                'mensaje_feriado' => $mensajeFeriado,
            ];
        }

        $eventos = [];
        foreach ($turnos as $turno) {
            $eventos[] = [
                'title' => 'Ocupado',
                'start' => $turno->fecha . 'T' . $turno->hora,
                'allDay' => false,
            ];
        }

        return [
            'eventos' => $eventos,
            'mensaje_feriado' => $mensajeFeriado,
        ];
    }

    /**
     * KPIs de agenda: no-show y días hasta la cita (staff). GET|POST /api/v1/turnos/indicadores-agenda.
     * RBAC: /api/turnos/indicadores-agenda
     *
     * @tags turnos, staff, indicadores, ui_json
     */
    public function actionIndicadoresAgenda(): array
    {
        $req = Yii::$app->request;
        $metrics = new TurnoAgendaMetricsService();

        if ($req->isPost) {
            try {
                $params = array_merge($req->get(), $req->post());
                $idEfector = (int) Yii::$app->user->getIdEfector();
                if ($idEfector <= 0) {
                    $idEfector = (int) ($params['id_efector'] ?? 0);
                }
                if ($idEfector <= 0) {
                    throw new \InvalidArgumentException('Se requiere id_efector (sesión operativa o parámetro).');
                }
                $params['id_efector'] = $idEfector;
                $data = $metrics->resumen($params);
                $values = array_merge($params, [
                    'resumen_texto' => (string) ($data['resumen_texto'] ?? ''),
                ]);
                $ui = UiScreenService::renderUiDefinition('turnos', 'indicadores-agenda', $req->get(), $values);
                $ui['success'] = true;
                $ui['data'] = $data;

                return $ui;
            } catch (\Throwable $e) {
                $values = array_merge($req->get(), $req->post());
                $ui = UiScreenService::renderUiDefinition('turnos', 'indicadores-agenda', $req->get(), $values);
                $ui['success'] = false;
                $ui['errors'] = ['_error' => [$e->getMessage()]];
                $ui['values'] = $values;
                $ui['action_id'] = 'turnos.indicadores-agenda';

                return $ui;
            }
        }

        $params = $req->get();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector > 0) {
            $params['id_efector'] = (string) $idEfector;
        }
        if (!isset($params['fecha_hasta']) || $params['fecha_hasta'] === '') {
            $params['fecha_hasta'] = date('Y-m-d');
        }
        if (!isset($params['fecha_desde']) || $params['fecha_desde'] === '') {
            $params['fecha_desde'] = date('Y-m-d', strtotime((string) $params['fecha_hasta'] . ' -30 days'));
        }

        return UiScreenService::renderUiDefinition('turnos', 'indicadores-agenda', $params, null);
    }
}
