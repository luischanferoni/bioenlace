<?php

namespace common\components\Organization\Service\SesionOperativa;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use common\models\Clinical\EncounterDefinition;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use common\models\Servicio;
use common\models\User;
use common\components\Core\Permission\BioenlaceAccessChecker;
use common\components\Assistant\UiActions\AllowedRoutesResolver;
use Firebase\JWT\JWT;

/**
 * Orquesta el establecimiento del "contexto operativo" en sesión (efector, PES, servicio y encounter class),
 * alineado con el flujo histórico de la web.
 *
 * La asignación operativa canónica es {@see ProfesionalEfectorServicio}; el JWT puede repetir el id PES en campos alias.
 */
class SesionOperativaService extends Component
{
    private const ITEM_NAME_SERVICIO_ADMIN_EFECTOR = 'AdminEfector';

    public static function isServicioAdminEfector(int $idServicio): bool
    {
        if ($idServicio <= 0) {
            return false;
        }
        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            return false;
        }

        return (string) $servicio->item_name === self::ITEM_NAME_SERVICIO_ADMIN_EFECTOR;
    }

    public static function isSesionOperativaCompleta(): bool
    {
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idServicio = (int) Yii::$app->user->getServicioActual();
        if ($idEfector <= 0 || $idServicio <= 0) {
            return false;
        }
        $encounterClass = Yii::$app->user->getEncounterClass();
        if ($encounterClass !== null && $encounterClass !== '') {
            return true;
        }

        return self::isServicioAdminEfector($idServicio);
    }

    /**
     * Hidrata `servicioYhorarioDeTurno` y datos de agenda desde PES + agendas en el efector actual.
     */
    public static function aplicarAgendaDisponibleDesdeContextoUsuario(): void
    {
        (new self())->establecerAgendaDisponiblePorContextoSesion();
    }

    /**
     * @param array{efector_id:mixed, servicio_id:mixed, encounter_class:mixed} $body
     *
     * Respuesta: `servicio` incluye `id_profesional_efector_servicio` (PK PES).
     *
     * @return array{
     *   efector: array{id:int,nombre:string},
     *   servicio: array{id:int,nombre:string,id_profesional_efector_servicio:int},
     *   encounter_class: array{code:string,label:string},
     *   id_contexto_profesional: int,
     *   redirect_url: string,
     *   context_token: string
     * }
     */
    public function establecer(array $body): array
    {
        $efectorId = $body['efector_id'] ?? null;
        $servicioId = $body['servicio_id'] ?? null;
        $encounterClassRaw = $body['encounter_class'] ?? null;

        if (!$efectorId || !$servicioId) {
            throw new \InvalidArgumentException('efector_id y servicio_id son requeridos.');
        }

        $efectorId = (int) $efectorId;
        $servicioId = (int) $servicioId;
        $omiteEncounter = self::isServicioAdminEfector($servicioId);
        $encounterClass = $encounterClassRaw !== null && $encounterClassRaw !== ''
            ? (string) $encounterClassRaw
            : '';

        if (!$omiteEncounter && $encounterClass === '') {
            throw new \InvalidArgumentException('encounter_class es requerido para este servicio.');
        }

        $validEncounterClasses = array_keys(EncounterDefinition::ENCOUNTER_CLASS);
        if ($encounterClass !== '' && !in_array($encounterClass, $validEncounterClasses, true)) {
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

        $idContextoStaff = (int) $pes->id;

        Yii::$app->user->setEncounterClass($encounterClass !== '' ? $encounterClass : null);
        Yii::$app->user->setServicioActual($servicioId);

        Yii::$app->user->setIdEfector($pes->id_efector);
        Yii::$app->user->setNombreEfector($pes->efector !== null ? (string) $pes->efector->nombre : '');
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

        BioenlaceAccessChecker::refreshForIdentity(Yii::$app->user->identity);
        AllowedRoutesResolver::markSessionRoutesOwner((int) Yii::$app->user->id);

        $this->establecerAgendaDisponiblePorContextoSesion();

        $redirectUrl = Yii::$app->urlManager->createUrl(self::redirectRouteForCurrentUser());

        // Token stateless con contexto operativo: permite que clientes m?viles operen sin cookie de sesi?n.
        $identity = Yii::$app->user->identity;
        $payload = [
            'user_id' => (int) ($identity->id ?? 0),
            'email' => (string) ($identity->email ?? ''),
            'id_persona' => $idPersona,
            'id_efector' => (int) $pes->id_efector,
            'id_profesional_efector_servicio' => (int) $pes->id,
            'servicio_actual' => (int) $servicioId,
            'encounter_class' => $encounterClass !== '' ? $encounterClass : null,
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
            ],
            'encounter_class' => $encounterClass !== ''
                ? [
                    'code' => $encounterClass,
                    'label' => (string) EncounterDefinition::ENCOUNTER_CLASS[$encounterClass],
                ]
                : null,
            'id_contexto_profesional' => $idContextoStaff,
            'redirect_url' => (string) $redirectUrl,
            'context_token' => (string) $contextToken,
        ];
    }

    /**
     * Ruta post-login / cambio de contexto según rol (web).
     *
     * @return array<int|string, string|int>
     */
    public static function redirectRouteForCurrentUser(): array
    {
        if (User::hasRole(['Medico'])) {
            return ['/site/index'];
        }
        if (User::hasRole(['Administrativo'])) {
            return ['/site/index'];
        }
        if (User::hasRole(['AdminEfector'])) {
            return ['/site/index'];
        }
        if (User::hasRole(['Enfermeria'])) {
            return ['/personas/buscar-persona'];
        }
        return ['/site/index'];
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
