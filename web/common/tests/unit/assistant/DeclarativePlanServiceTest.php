<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Context\AssistantContextAnchorBag;
use common\components\Platform\Assistant\Context\AssistantContextAreaAspectCatalog;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Planning\AssistantFirstIaAdapter;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingService;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;

class DeclarativePlanServiceTest extends Unit
{
    protected function _after(): void
    {
        AssistantContextAreaAspectCatalog::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
        SmartCatalogRegistry::resetCacheForTests();
        AssistantPlanningLogService::resetForTests();
    }

    public function testPlanAppointmentsIncludesPolicyAspects(): void
    {
        $anchors = new AssistantContextAnchorBag();
        $anchors->subjectPersonaId = 1;

        $plan = DeclarativePlanService::plan(
            [AssistantContextHISArea::APPOINTMENTS],
            [['span' => '10 minutos tarde', 'category' => 'tiempo', 'synonyms' => []]],
            $anchors,
            null
        );

        $this->assertContains(
            'aspect:' . AssistantContextHISAreaAspect::SITE_APPOINTMENT_POLICIES,
            $plan->toolIds
        );
        $this->assertContains(
            'aspect:' . AssistantContextHISAreaAspect::APPOINTMENT_CURRENT,
            $plan->toolIds
        );
        $this->assertFalse($plan->needsPlanner);
    }

    public function testFirstIaAdapterInfersLateArrivalTags(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Voy a tener problemas si llego 10 minutos tarde?',
            'user_goal' => 'guide',
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('llegar_tarde', $first['tags']);
        $this->assertSame('incompletas', $first['routing_hint']);
    }

    public function testFirstIaAdapterAlwaysInfersSacarTurnoEvenIfIaTaggedArea(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Quiero un turno con el dentista',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('sacar_turno', $first['tags']);
        $this->assertNotContains('pedido_turno_sin_destino', $first['tags']);
    }

    public function testFirstIaAdapterInfersPedidoSinDestinoForBareTurno(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('pedido_turno_sin_destino', $first['tags']);
        $this->assertNotContains('sacar_turno', $first['tags']);
    }

    public function testFirstIaAdapterCancelDoesNotGetPedidoTurno(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Cancelá el turno del martes',
            'user_goal' => 'operational',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('cancelar_turno', $first['tags']);
        $this->assertNotContains('pedido_turno_sin_destino', $first['tags']);
        $this->assertNotContains('sacar_turno', $first['tags']);
    }

    public function testFirstIaAdapterHistorialNotMisTurnos(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Mostrame los turnos que ya tuve',
            'user_goal' => 'operational',
            'tags' => ['mis_turnos', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('historial_turnos', $first['tags']);
        $this->assertNotContains('mis_turnos', $first['tags']);
    }

    public function testFirstIaAdapterPoliticaNotCancelar(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Hasta cuándo puedo cancelar?',
            'user_goal' => 'guide',
            'tags' => ['cancelar_turno', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('politica_turnos', $first['tags']);
        $this->assertNotContains('cancelar_turno', $first['tags']);
    }

    public function testFirstIaAdapterUltimaAtencionStripsHistorialTurnosNoise(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Qué me dijo el médico ayer?',
            'user_goal' => 'guide',
            'tags' => ['historial_turnos', 'staff', 'encounters'],
            'context_areas' => ['encounters'],
            'extractions' => [],
        ]);

        $this->assertContains('ultima_atencion', $first['tags']);
        $this->assertNotContains('historial_turnos', $first['tags']);
    }

    public function testFirstIaAdapterAlwaysInfersSintomaEvenIfIaTagged(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Me duele la cabeza',
            'user_goal' => 'guide',
            'tags' => ['appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ]);

        $this->assertContains('sintoma', $first['tags']);
        $this->assertContains('necesito_atencion', $first['tags']);
    }

    public function testSymptomDropsClinicalRecordAreaUnlessAsked(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Tengo un pinchazo en el pecho cuando respiro',
            'user_goal' => 'guide',
            'tags' => ['sintoma', 'clinical_record'],
            'context_areas' => ['clinical_record'],
            'extractions' => [],
        ]);

        $this->assertNotContains('clinical_record', $first['context_areas']);
        $this->assertNotContains('clinical_record', $first['tags']);
        $this->assertContains('sintoma', $first['tags']);
    }

    public function testAsksAboutAllergiesKeepsClinicalRecordArea(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => '¿Cuáles son mis alergias?',
            'user_goal' => 'guide',
            'tags' => ['clinical_record'],
            'context_areas' => ['clinical_record'],
            'extractions' => [],
        ]);

        $this->assertContains('clinical_record', $first['context_areas']);
    }

    public function testFirstIaAdapterInfersMisAnalisis(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'Mis análisis',
            'user_goal' => 'operational',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ]);

        $this->assertContains('mis_analisis', $first['tags']);
    }

    public function testRoutingCancelIsClaraNotAgendaCtas(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Cancelá el turno del martes',
            'user_goal' => 'operational',
            'routing_hint' => 'pedido_claro',
            'tags' => ['cancelar_turno', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isMatch100());
        $this->assertSame('turnos.cancelar-como-paciente-flow', $evaluation->decision->primaryIntentId());
    }

    public function testRoutingSintomaIsIncompletasWithAtencionCta(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Me duele la cabeza',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['sintoma', 'necesito_atencion'],
            'context_areas' => [],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isIncompletas());
        $this->assertSame('atencion-sintoma', $evaluation->decision->catalogEntry?->id);
        $this->assertSame(['atencion.necesito-atencion'], $evaluation->decision->catalogEntry?->ctaIntentIds);
        $this->assertSame([], $evaluation->declarativePlan->toolIds);
    }

    public function testRoutingMisAnalisisIsClaraLab(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Mis análisis',
            'user_goal' => 'operational',
            'routing_hint' => 'pedido_claro',
            'tags' => ['mis_analisis'],
            'context_areas' => ['diagnostics'],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isMatch100());
        $this->assertSame('laboratorio.ver-resultados-como-paciente', $evaluation->decision->primaryIntentId());
    }

    public function testFirstIaAdapterInfersEstudioNotSacarTurno(): void
    {
        $first = AssistantFirstIaAdapter::fromPreprocess([
            'normalized_text' => 'necesito una ecografia',
            'tags' => [],
            'context_areas' => [],
            'extractions' => [],
        ]);

        $this->assertContains('estudio', $first['tags']);
        $this->assertNotContains('sacar_turno', $first['tags']);
        $this->assertNotContains('pedido_turno_sin_destino', $first['tags']);
    }

    public function testRoutingFueraDeHisForMedium(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'necesito una sesion con una medium',
            'user_goal' => 'ambiguous',
            'context_areas' => [],
            'extractions' => [],
        ], 0);

        $this->assertTrue($evaluation->decision->isFueraDeHis());
        $snap = AssistantPlanningLogService::snapshot();
        $this->assertSame('fuera_de_his', $snap['routing_result'] ?? null);
    }

    public function testBareTurnoOrientationPlanHasNoHisTools(): void
    {
        $evaluation = SmartCatalogRoutingService::evaluate([
            'normalized_text' => 'Quiero un turno',
            'user_goal' => 'guide',
            'routing_hint' => 'pedido_claro',
            'tags' => ['pedido_turno_sin_destino', 'appointments'],
            'context_areas' => ['appointments'],
            'extractions' => [],
        ], 1);

        $this->assertTrue($evaluation->decision->isIncompletas());
        $this->assertSame('agenda-pedido-sin-destino', $evaluation->decision->catalogEntry?->id);
        $this->assertSame([], $evaluation->declarativePlan->toolIds);
        $this->assertFalse($evaluation->declarativePlan->needsPlanner);
        $this->assertStringContainsString('cta_orientation', $evaluation->declarativePlan->reason);
    }
}
