<?php

namespace common\components\Services\SesionOperativa;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use common\models\ConsultasConfiguracion;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\components\Services\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use common\models\RrhhEfector;
use common\models\Servicio;
use common\models\User;
use webvimark\modules\UserManagement\components\AuthHelper;
use common\components\Assistant\UiActions\AllowedRoutesResolver;
use Firebase\JWT\JWT;

/**
 * Orquesta el establecimiento del "contexto operativo" en sesi?n (efector, RRHH, servicio y encounter class),
 * alineado con el flujo hist?rico de la web.
 *
 * La asignaci?n operativa can?nica es {@see ProfesionalEfectorServicio}; `id_rr_hh` / `id_rrhh_servicio` se
 * mantienen solo como compatibilidad cuando a?n existen filas legacy.
 */
class SesionOperativaService extends Component
{
    /**
     * Hidrata `servicioYhorarioDeTurno` y `idRrhhServicio` (si aplica) desde PES + agendas en el efector actual.
     */
    public static function aplicarAgendaDisponibleDesdeContextoUsuario(): void
    {
        (new self())->establecerAgendaDisponiblePorContextoSesion();
    }

    /**
     * @param array{efector_id:mixed, servicio_id:mixed, encounter_class:mixed} $body
     * @return array{
     *   efector: array{id:int,nombre:string},
     *   servicio: array{id:int,nombre:string,id_profesional_efector_servicio:int,id_rrhh_servicio:int},
     *   encounter_class: array{code:string,label:string},
     *   rrhh_id: int,
     *   redirect_url: string,
     *   context_token: string
     * } `servicio.id_rrhh_servicio` repite el id PES (alias legacy en payload); can?nico: `id_profesional_efector_servicio`.
     */
    public function establecer(array $body): array
    {
        $efectorId = $body['efector_id'] ?? null;
        $servicioId = $body['servicio_id'] ?? null;
        $encounterClass = $body['encounter_class'] ?? null;

        if (!$efectorId || !$servicioId || !$encounterClass) {
            throw new \InvalidArgumentException('Todos los par?metros son requeridos: efector_id, servicio_id, encounter_class');
        }

        $efectorId = (int) $efectorId;
        $servicioId = (int) $servicioId;
        $encounterClass = (string) $encounterClass;

        $validEncounterClasses = array_keys(ConsultasConfiguracion::ENCOUNTER_CLASS);
        if (!in_array($encounterClass, $validEncounterClasses, true)) {
            throw new \InvalidArgumentException('Encounter class inv?lido');
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio($idPersona, $efectorId, $servicioId);
        if ($pes === null) {
            try {
                $out = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
                    $idPersona,
                    $efectorId,
                    $servicioId
                );
                $pes = ProfesionalEfectorServicio::findOne(['id' => $out['id_profesional_efector_servicio'], 'deleted_at' => null]);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }
        if ($pes === null) {
            throw new \RuntimeException('No se encontr? asignaci?n profesional-efector-servicio para la persona autenticada');
        }

        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $efectorId,
                'id_persona' => $idPersona,
                'deleted_at' => null,
            ])
            ->one();
        $idRrhh = $rrhhEfector !== null ? (int) $rrhhEfector->id_rr_hh : 0;

        Yii::$app->user->setEncounterClass($encounterClass);
        Yii::$app->user->setServicioActual($servicioId);

        Yii::$app->user->setIdEfector($pes->id_efector);
        Yii::$app->user->setNombreEfector($pes->efector !== null ? (string) $pes->efector->nombre : '');
        Yii::$app->user->setIdRecursoHumano($idRrhh);
        Yii::$app->user->setIdProfesionalEfectorServicio((int) $pes->id);

        $pesEnEfector = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $efectorId,
                'deleted_at' => null,
            ])
            ->all();
        Yii::$app->user->setServicios(ArrayHelper::map(
            $pesEnEfector,
            'id_servicio',
            static function ($p) {
                return $p->servicio !== null ? (string) $p->servicio->nombre : '';
            }
        ));

        AuthHelper::updatePermissions(Yii::$app->user->identity);
        AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

        $this->establecerAgendaDisponiblePorContextoSesion();

        $redirectUrl = Yii::$app->urlManager->createUrl($this->getRedirectRouteForCurrentUser());

        // Token stateless con contexto operativo: permite que clientes m?viles operen sin cookie de sesi?n.
        $identity = Yii::$app->user->identity;
        $payload = [
            'user_id' => (int) ($identity->id ?? 0),
            'email' => (string) ($identity->email ?? ''),
            'id_persona' => $idPersona,
            'id_efector' => (int) $pes->id_efector,
            'id_rr_hh' => $idRrhh,
            'id_profesional_efector_servicio' => (int) $pes->id,
            'servicio_actual' => (int) $servicioId,
            'id_rrhh_servicio' => (int) $pes->id,
            'encounter_class' => (string) $encounterClass,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60),
        ];
        $contextToken = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');

        return [
            'efector' => [
                'id' => (int) $pes->id_efector,
                'nombre' => (string) ($pes->efector->nombre ?? ''),
            ],
            'servicio' => [
                'id' => (int) $servicioId,
                'nombre' => (string) ($pes->servicio->nombre ?? ''),
                'id_profesional_efector_servicio' => (int) $pes->id,
                'id_rrhh_servicio' => (int) $pes->id,
            ],
            'encounter_class' => [
                'code' => (string) $encounterClass,
                'label' => (string) ConsultasConfiguracion::ENCOUNTER_CLASS[$encounterClass],
            ],
            'rrhh_id' => $idRrhh,
            'redirect_url' => (string) $redirectUrl,
            'context_token' => (string) $contextToken,
        ];
    }

    /**
     * Replica SiteController::generarUrlUsurioEfectorAredireccionar().
     *
     * @return array
     */
    private function getRedirectRouteForCurrentUser(): array
    {
        if (User::hasRole(['Medico'])) {
            return ['/site/pacientes'];
        }
        if (User::hasRole(['Administrativo'])) {
            return ['/site/pacientes'];
        }
        if (User::hasRole(['AdminEfector'])) {
            return ['/site/pacientes'];
        }
        if (User::hasRole(['Enfermeria'])) {
            return ['/personas/buscar-persona'];
        }
        return ['/site/pacientes'];
    }

    /**
     * Hidrataci?n de agenda disponible a partir de todas las PES de la persona en el efector de sesi?n.
     */
    private function establecerAgendaDisponiblePorContextoSesion(): void
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idPersona <= 0 || $idEfector <= 0) {
            return;
        }

        $pesRows = ProfesionalEfectorServicio::find()
            ->where(['id_persona' => $idPersona, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->all();

        $servicioActual = Yii::$app->user->getServicioActual();
        $nroDiaDeSemana = (int) date('N') - 1;
        $nroDiaDeSemanaManiana = $nroDiaDeSemana === 6 ? 0 : $nroDiaDeSemana + 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];

        $idsPes = array_map(static function ($p) {
            return (int) $p->id;
        }, $pesRows);

        $agendas = $idsPes !== []
            ? array_values(ProfesionalEfectorServicioAgenda::findPorIdsProfesionalEfectorServicio($idsPes))
            : [];

        $servicios = [$nroDiaDeSemana => [], ($nroDiaDeSemana + 1) => []];
        foreach ($agendas as $agenda) {
            if (
                (($agenda->{$columnasAgenda[$nroDiaDeSemana]} ?? null) === null || $agenda->{$columnasAgenda[$nroDiaDeSemana]} === '')
                && (($agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} ?? null) === null || $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} === '')
            ) {
                continue;
            }

            $pes = $agenda->asignacion;
            if ($pes === null) {
                continue;
            }
            $servicioModel = Servicio::findOne($pes->id_servicio);
            $nombreServicio = $servicioModel !== null ? (string) $servicioModel->nombre : '';

            $horasDeAgendaHoy = explode(',', (string) $agenda->{$columnasAgenda[$nroDiaDeSemana]});
            $servicios[$nroDiaDeSemana] = [
                $pes->id_servicio => [
                    'nombreServicio' => $nombreServicio,
                    'horaInicial' => $horasDeAgendaHoy[0] ?? null,
                    'horaFinal' => $horasDeAgendaHoy[count($horasDeAgendaHoy) - 1] ?? null,
                ],
            ];

            $horasDeAgendaManiana = explode(',', (string) $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]});
            $servicios[$nroDiaDeSemana + 1] = [
                $pes->id_servicio => [
                    'nombreServicio' => $nombreServicio,
                    'horaInicial' => $horasDeAgendaManiana[0] ?? null,
                    'horaFinal' => $horasDeAgendaManiana[count($horasDeAgendaManiana) - 1] ?? null,
                ],
            ];
        }

        Yii::$app->user->setServicioYhorarioDeTurno($servicios);
    }
}
