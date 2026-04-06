<?php

namespace common\components\Services\SesionOperativa;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use common\models\Agenda_rrhh;
use common\models\ConsultasConfiguracion;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\User;
use webvimark\modules\UserManagement\components\AuthHelper;
use common\components\Actions\AllowedRoutesResolver;
use Firebase\JWT\JWT;

/**
 * Orquesta el establecimiento del "contexto operativo" en sesión (efector, RRHH, servicio y encounter class),
 * alineado con el flujo histórico de la web.
 *
 * Nota: si un usuario tiene roles por efector pero no tiene fila en rrhh_efector, por ahora devolvemos error.
 */
class SesionOperativaService extends Component
{
    /**
     * @param array{efector_id:mixed, servicio_id:mixed, encounter_class:mixed} $body
     * @return array{efector:array{id:int,nombre:string},servicio:array{id:int,nombre:string,id_rrhh_servicio:int},encounter_class:array{code:string,label:string},rrhh_id:int,redirect_url:string,context_token:string}
     */
    public function establecer(array $body): array
    {
        $efectorId = $body['efector_id'] ?? null;
        $servicioId = $body['servicio_id'] ?? null;
        $encounterClass = $body['encounter_class'] ?? null;

        if (!$efectorId || !$servicioId || !$encounterClass) {
            throw new \InvalidArgumentException('Todos los parámetros son requeridos: efector_id, servicio_id, encounter_class');
        }

        $efectorId = (int) $efectorId;
        $servicioId = (int) $servicioId;
        $encounterClass = (string) $encounterClass;

        $validEncounterClasses = array_keys(ConsultasConfiguracion::ENCOUNTER_CLASS);
        if (!in_array($encounterClass, $validEncounterClasses, true)) {
            throw new \InvalidArgumentException('Encounter class inválido');
        }

        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $efectorId,
                'id_persona' => Yii::$app->user->getIdPersona(),
            ])
            ->one();

        if ($rrhhEfector === null) {
            // Por ahora no soportamos usuarios con roles x efector sin RRHH asociado.
            throw new \RuntimeException('No se encontró relación RRHH-Efector para la persona autenticada');
        }

        $rrhhServicio = RrhhServicio::find()
            ->where([
                'id_servicio' => $servicioId,
                'id_rr_hh' => $rrhhEfector->id_rr_hh,
            ])
            ->one();

        if ($rrhhServicio === null) {
            throw new \InvalidArgumentException('El servicio especificado no está disponible para este efector');
        }

        Yii::$app->user->setEncounterClass($encounterClass);
        Yii::$app->user->setServicioActual($servicioId);

        Yii::$app->user->setIdEfector($rrhhEfector->id_efector);
        Yii::$app->user->setNombreEfector($rrhhEfector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhhEfector->id_rr_hh);

        Yii::$app->user->setIdRrhhServicio($rrhhServicio->id);

        // Servicios disponibles en el efector (para el RRHH)
        Yii::$app->user->setServicios(ArrayHelper::map($rrhhEfector->rrhhServicio, 'id_servicio', 'servicio.nombre'));

        AuthHelper::updatePermissions(Yii::$app->user->identity);
        AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

        $this->establecerAgendaDisponible((int) $rrhhEfector->id_rr_hh);

        $redirectUrl = Yii::$app->urlManager->createUrl($this->getRedirectRouteForCurrentUser());

        // Token stateless con contexto operativo: permite que clientes móviles operen sin cookie de sesión.
        $identity = Yii::$app->user->identity;
        $payload = [
            'user_id' => (int) ($identity->id ?? 0),
            'email' => (string) ($identity->email ?? ''),
            'id_persona' => (int) Yii::$app->user->getIdPersona(),
            'id_efector' => (int) $rrhhEfector->id_efector,
            'id_rr_hh' => (int) $rrhhEfector->id_rr_hh,
            'servicio_actual' => (int) $servicioId,
            'id_rrhh_servicio' => (int) $rrhhServicio->id,
            'encounter_class' => (string) $encounterClass,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60),
        ];
        $contextToken = JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');

        return [
            'efector' => [
                'id' => (int) $rrhhEfector->id_efector,
                'nombre' => (string) $rrhhEfector->efector->nombre,
            ],
            'servicio' => [
                'id' => (int) $servicioId,
                'nombre' => (string) $rrhhServicio->servicio->nombre,
                'id_rrhh_servicio' => (int) $rrhhServicio->id,
            ],
            'encounter_class' => [
                'code' => (string) $encounterClass,
                'label' => (string) ConsultasConfiguracion::ENCOUNTER_CLASS[$encounterClass],
            ],
            'rrhh_id' => (int) $rrhhEfector->id_rr_hh,
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
        if (User::hasRole(['Enfermeria'])) {
            return ['/personas/buscar-persona'];
        }
        return ['/site/pacientes'];
    }

    /**
     * Hidratación de agenda disponible (mismo comportamiento que la web).
     */
    private function establecerAgendaDisponible(int $idRrHh): void
    {
        $serviciosDelRrhh = RrhhServicio::find()
            ->select(['id', 'id_servicio'])
            ->andWhere(['id_rr_hh' => $idRrHh])
            ->asArray()
            ->all();

        foreach ($serviciosDelRrhh as $servicioDelRrhh) {
            if ((int) Yii::$app->user->getServicioActual() === (int) $servicioDelRrhh['id_servicio']) {
                Yii::$app->user->setIdRrhhServicio((int) $servicioDelRrhh['id']);
            }
        }

        $nroDiaDeSemana = (int) date('N') - 1;
        $nroDiaDeSemanaManiana = $nroDiaDeSemana === 6 ? 0 : $nroDiaDeSemana + 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];

        $agendas = Agenda_rrhh::find()
            ->andWhere(['in', 'id_rrhh_servicio_asignado', ArrayHelper::getColumn($serviciosDelRrhh, 'id')])
            ->all();

        $servicios = [$nroDiaDeSemana => [], ($nroDiaDeSemana + 1) => []];
        foreach ($agendas as $agenda) {
            if (
                (($agenda->{$columnasAgenda[$nroDiaDeSemana]} ?? null) === null || $agenda->{$columnasAgenda[$nroDiaDeSemana]} === '')
                && (($agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} ?? null) === null || $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} === '')
            ) {
                continue;
            }

            $horasDeAgendaHoy = explode(',', (string) $agenda->{$columnasAgenda[$nroDiaDeSemana]});
            $servicios[$nroDiaDeSemana] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaHoy[0] ?? null,
                    'horaFinal' => $horasDeAgendaHoy[count($horasDeAgendaHoy) - 1] ?? null,
                ],
            ];

            $horasDeAgendaManiana = explode(',', (string) $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]});
            $servicios[$nroDiaDeSemana + 1] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaManiana[0] ?? null,
                    'horaFinal' => $horasDeAgendaManiana[count($horasDeAgendaManiana) - 1] ?? null,
                ],
            ];
        }

        Yii::$app->user->setServicioYhorarioDeTurno($servicios);
    }
}

