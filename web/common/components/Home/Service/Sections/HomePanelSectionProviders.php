<?php

namespace common\components\Home\Service\Sections;

use Yii;
use common\components\Clinical\Emergency\Service\GuardiaEfectorAccess;
use common\components\Clinical\Emergency\Service\GuardiaIndicadoresService;
use common\components\Clinical\Emergency\Service\GuardiaQueueService;
use common\components\Core\Service\Actions\CommonActionsService;
use common\components\Clinical\Service\CarePlanPresentationService;
use common\components\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationSubjectService;
use common\components\Scheduling\Service\TurnoPacienteListadoService;

final class EmergencyBoardSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = GuardiaEfectorAccess::resolveIdEfector(
            isset($context['id_efector']) ? (int) $context['id_efector'] : null
        );
        GuardiaEfectorAccess::assertCanAccessEfector($idEfector);

        return (new GuardiaQueueService())->tablero($idEfector, ['solo_activos' => true]);
    }
}

final class EmergencyIndicatorsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $idEfector = GuardiaEfectorAccess::resolveIdEfector(
            isset($context['id_efector']) ? (int) $context['id_efector'] : null
        );
        GuardiaEfectorAccess::assertCanAccessEfector($idEfector);

        return (new GuardiaIndicadoresService())->resumen($idEfector);
    }
}

final class AppointmentsDaySectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $conPrueba = !empty($context['prueba']);
        $payload = (new StaffClinicalDayListService())->turnosAmbulatorioMedico($fecha, null, $conPrueba);

        return [
            'items' => $payload['turnos'],
            'total' => $payload['total'],
            'fecha' => $payload['fecha'],
        ];
    }
}

final class InpatientsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $items = (new StaffClinicalDayListService())->internadosPorEfector();

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
}

final class SurgeriesDaySectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $fecha = (string) ($context['fecha'] ?? date('Y-m-d'));
        $items = (new StaffClinicalDayListService())->cirugiasAgendadasPorEfectorYFecha($fecha);

        return [
            'items' => $items,
            'total' => count($items),
            'fecha' => $fecha,
        ];
    }
}

final class ActionCardsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $userId = (int) Yii::$app->user->id;
        if ($userId <= 0) {
            return ['categories' => [], 'actions' => []];
        }

        return CommonActionsService::getFormattedForUser($userId);
    }
}

final class PatientUpcomingAppointmentsSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (!empty($context['subject_persona_id'])) {
            $params['subject_persona_id'] = (int) $context['subject_persona_id'];
        }

        return (new TurnoPacienteListadoService())->listForHomePanel($params);
    }
}

final class PatientCarePlansActiveSectionProvider implements HomePanelSectionProviderInterface
{
    public function build(array $context): array
    {
        $params = [];
        if (!empty($context['subject_persona_id'])) {
            $params['subject_persona_id'] = (int) $context['subject_persona_id'];
        }
        $subjectSvc = new PersonRepresentationSubjectService();
        $idPersona = $subjectSvc->resolveAndAuthorize(
            $params,
            RepresentationPermission::CLINICAL_CARE_PLAN
        );

        $plans = (new PatientActiveCarePlanQuery())->listActive($idPersona);
        $presentation = new CarePlanPresentationService();
        $data = [];
        foreach ($plans as $plan) {
            $data[] = $presentation->toPatientSummary($plan, true);
        }

        return [
            'items' => $data,
            'total' => count($data),
        ];
    }
}
