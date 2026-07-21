<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;
use common\components\Platform\Assistant\IntentEngine\IntentClassifier;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

class IntentClassificationRulesServiceTest extends Unit
{
    protected function _after(): void
    {
        IntentClassificationRulesService::resetCacheForTests();
    }

    public function testStaffAgendaRuleMatchesFormasAtencion(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'staff_agenda_config_edit',
            'necesito modificar las formas de atencion de un profesional'
        ));
    }

    public function testOperationalFallbackRoutesAgendaEditToConfigurarStaff(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'profesional-agenda.configurar-staff',
                    'Configurar agenda (staff)',
                    '',
                    null,
                    '/api/profesional-agenda/configurar-agenda',
                    ['modificar agenda de un profesional'],
                    []
                ),
                new UiActionCatalogItem(
                    'profesional-efector-servicio.crear-flow',
                    'Alta',
                    '',
                    null,
                    '/api/test',
                    ['agenda'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['profesional-agenda.configurar-staff'] = $catalog->items[0];
        $catalog->byActionId['profesional-efector-servicio.crear-flow'] = $catalog->items[1];

        $msg = 'necesito modificar la agenda de un profesional';
        $fb = IntentClassificationRulesService::resolveOperationalFallback($msg, $catalog);

        $this->assertNotNull($fb);
        $this->assertSame('profesional-agenda.configurar-staff', $fb['item']->action_id);
        $this->assertSame('rules_declarative_fallback', $fb['method']);
    }

    public function testScoreAdjustmentPrefersConfigurarStaffOverLicencia(): void
    {
        $msg = 'necesito modificar las formas de atencion';
        $configurar = new UiActionCatalogItem(
            'profesional-agenda.configurar-staff',
            'Configurar agenda',
            '',
            null,
            '/api/profesional-agenda/configurar-agenda',
            ['formas de atencion'],
            []
        );
        $licencia = new UiActionCatalogItem(
            'licencia.cargar-para-profesional-flow',
            'Licencia',
            '',
            null,
            '/api/test',
            ['licencia'],
            []
        );
        $lower = mb_strtolower($msg, 'UTF-8');
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $licencia),
            IntentClassifier::scoreItemPublic($lower, $configurar)
        );
    }

    public function testAiPromptHintsDoNotContainHardcodedIntentIds(): void
    {
        foreach (IntentClassificationRulesService::aiPromptHintLines() as $line) {
            $this->assertStringNotContainsString('best_id =', $line);
            $this->assertDoesNotMatchRegularExpression('/\bdata-access\.\w+\b/', $line);
        }
    }

    public function testClinicalSymptomRuleMatches(): void
    {
        $this->assertTrue(IntentClassificationRulesService::isClinicalSymptomContent('me duele la cabeza'));
        $this->assertFalse(IntentClassificationRulesService::isClinicalSymptomContent('quiero un turno'));
    }

    public function testGoalOverrideSymptomPlusHospitalNearGoesConversational(): void
    {
        $msg = 'estoy con dolor de cabeza de hace varios dias, me gustaría saber que hospital tengo cerca que este atendiendo';
        $this->assertSame(
            'conversational',
            IntentClassificationRulesService::applyChatPreprocessGoalOverrides($msg, 'operational')
        );
        // Si la IA “limpia” el normalized y deja solo la parte de hospital, el original aún desvía.
        $this->assertSame(
            'conversational',
            IntentClassificationRulesService::applyChatPreprocessGoalOverrides(
                'quiero saber que hospital tengo cerca',
                'operational',
                $msg
            )
        );
    }

    public function testGoalOverrideSymptomPlusExplicitTurnoStaysOperational(): void
    {
        $msg = 'me duele la cabeza y quiero un turno';
        $this->assertSame(
            'operational',
            IntentClassificationRulesService::applyChatPreprocessGoalOverrides($msg, 'operational')
        );
    }

    public function testOperationalFallbackRoutesNecesitoUnTurnoToCrearComoPaciente(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'turnos.crear-como-paciente',
                    'Reservar turno',
                    '',
                    null,
                    '/api/turnos/crear-como-paciente',
                    ['turno', 'reservar turno'],
                    []
                ),
                new UiActionCatalogItem(
                    'turnos.ver-mis-turnos-como-paciente',
                    'Mis turnos',
                    '',
                    null,
                    '/api/turnos/ver-mis-turnos',
                    ['mis turnos'],
                    []
                ),
                new UiActionCatalogItem(
                    'atencion.necesito-atencion',
                    'Necesito atención',
                    '',
                    null,
                    '/api/turnos/crear-como-paciente',
                    ['necesito atención'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['turnos.crear-como-paciente'] = $catalog->items[0];
        $catalog->byActionId['turnos.ver-mis-turnos-como-paciente'] = $catalog->items[1];
        $catalog->byActionId['atencion.necesito-atencion'] = $catalog->items[2];

        $fb = IntentClassificationRulesService::resolveOperationalFallback('necesito un turno', $catalog);

        $this->assertNotNull($fb);
        $this->assertSame('turnos.crear-como-paciente', $fb['item']->action_id);
        $this->assertSame('rules_declarative_fallback', $fb['method']);

        $lower = mb_strtolower('necesito un turno', 'UTF-8');
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $catalog->items[1]),
            IntentClassifier::scoreItemPublic($lower, $catalog->items[0])
        );
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $catalog->items[2]),
            IntentClassifier::scoreItemPublic($lower, $catalog->items[0])
        );
    }

    public function testPacienteReservarTurnoRuleMatches(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'paciente_reservar_turno',
            'necesito un turno'
        ));
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'paciente_reservar_turno',
            'Quiero un turno'
        ));
        $this->assertFalse(IntentClassificationRulesService::ruleMatches(
            'paciente_reservar_turno',
            'ver mis turnos'
        ));
    }

    public function testStaffDataAccessEditExcludesScheduling(): void
    {
        $this->assertTrue(IntentClassificationRulesService::isStaffDataAccessEditQuery('modificar agenda del personal'));
        $this->assertFalse(IntentClassificationRulesService::isStaffDataAccessEditQuery('modificar turno del paciente'));
    }

    public function testConsultasSeguimientoRuleMatchesRenovarReceta(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'consultas_seguimiento_followup',
            'quiero renovar la medicación de mi tratamiento'
        ));
    }

    public function testConsultasSeguimientoRuleDoesNotMatchAcuteSymptom(): void
    {
        $this->assertFalse(IntentClassificationRulesService::ruleMatches(
            'consultas_seguimiento_followup',
            'me siento mal y me duele mucho la cabeza'
        ));
    }

    public function testConsultasSeguimientoOperationalFallback(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'atencion.necesito-atencion',
                    'Solicitar Atención',
                    '',
                    null,
                    '/api/test',
                    ['seguimiento tratamiento'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['atencion.necesito-atencion'] = $catalog->items[0];
        $fb = IntentClassificationRulesService::resolveOperationalFallback(
            'tengo una duda sobre el tratamiento',
            $catalog
        );
        $this->assertNotNull($fb);
        $this->assertSame('atencion.necesito-atencion', $fb['item']->action_id);
    }

    public function testDelegarRepresentacionRuleMatchesGestionTurnos(): void
    {
        $this->assertTrue(IntentClassificationRulesService::ruleMatches(
            'paciente_delegar_representacion',
            'Delegar gestión de turnos'
        ));
        $this->assertFalse(IntentClassificationRulesService::ruleMatches(
            'paciente_delegar_representacion',
            'Delegar gestión'
        ));
    }

    public function testDelegarRepresentacionOperationalFallback(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'personas.designar-representante-flow',
                    'Designar representante',
                    '',
                    null,
                    '/api/person-representation/designar-representante',
                    ['delegar gestión de turnos'],
                    []
                ),
                new UiActionCatalogItem(
                    'personas.vincular-menor-flow',
                    'Solicitar tutela de menor',
                    '',
                    null,
                    '/api/person-representation/solicitar-menor-como-tutor',
                    ['vincular hijo'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['personas.designar-representante-flow'] = $catalog->items[0];
        $catalog->byActionId['personas.vincular-menor-flow'] = $catalog->items[1];
        $fb = IntentClassificationRulesService::resolveOperationalFallback(
            'Delegar gestión de turnos',
            $catalog
        );
        $this->assertNotNull($fb);
        $this->assertSame('personas.designar-representante-flow', $fb['item']->action_id);

        $fbTutela = IntentClassificationRulesService::resolveOperationalFallback(
            'Quiero vincular a mi hijo menor',
            $catalog
        );
        $this->assertNotNull($fbTutela);
        $this->assertSame('personas.vincular-menor-flow', $fbTutela['item']->action_id);
    }

    public function testStaffVerificarTutelaOperationalFallback(): void
    {
        $catalog = \common\components\Platform\Assistant\IntentEngine\UiActionCatalog::fromItems(
            [
                new UiActionCatalogItem(
                    'personas.verificar-tutela-staff-flow',
                    'Verificar tutela de menor',
                    '',
                    null,
                    '/api/person-representation/verificar-vinculo-para-staff',
                    ['verificar tutela'],
                    []
                ),
            ],
            []
        );
        $catalog->byActionId['personas.verificar-tutela-staff-flow'] = $catalog->items[0];
        $fb = IntentClassificationRulesService::resolveOperationalFallback(
            'Aprobar solicitudes de tutela pendientes',
            $catalog
        );
        $this->assertNotNull($fb);
        $this->assertSame('personas.verificar-tutela-staff-flow', $fb['item']->action_id);
    }

    public function testDelegarRepresentacionScoresAboveTurnosCrear(): void
    {
        $msg = 'Delegar gestión de turnos';
        $designar = new UiActionCatalogItem(
            'personas.designar-representante-flow',
            'Designar representante',
            '',
            null,
            '/api/person-representation/designar-representante',
            ['delegar gestión de turnos', 'designar representante'],
            []
        );
        $turno = new UiActionCatalogItem(
            'turnos.crear-como-paciente',
            'Reservar turno',
            '',
            null,
            '/api/turnos/crear-como-paciente',
            ['turno', 'reservar turno'],
            []
        );
        $lower = mb_strtolower($msg, 'UTF-8');
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $turno),
            IntentClassifier::scoreItemPublic($lower, $designar)
        );
    }
}
