<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Encounter\Application\Service\EpisodioHistoriaBannerService;
use common\models\Clinical\Encounter;

class EpisodioHistoriaBannerServiceTest extends Unit
{
    public function testEpisodePresentationProvidesBackendLabelAndIntent(): void
    {
        $this->assertSame(
            ['label' => 'Guardia', 'intent' => 'danger'],
            EpisodioHistoriaBannerService::episodePresentation(Encounter::PARENT_GUARDIA)
        );
        $this->assertSame(
            ['label' => 'Internación', 'intent' => 'info'],
            EpisodioHistoriaBannerService::episodePresentation(Encounter::PARENT_INTERNACION)
        );
    }

    public function testUnknownEpisodeTypeHasNoImplicitPresentation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EpisodioHistoriaBannerService::episodePresentation('UNKNOWN');
    }

    public function testTriageActionDeclaresItsNativeKindAndPresentation(): void
    {
        $method = (new \ReflectionClass(EpisodioHistoriaBannerService::class))
            ->getMethod('triageAction');
        $method->setAccessible(true);

        $this->assertSame([
            'id' => 'editar_triage',
            'label' => 'Registrar triage',
            'kind' => 'native_triage',
            'intent' => 'primary',
            'variant' => 'outline',
            'api' => [
                'route' => '/clinical/emergency-guardia/registrar-triage-formulario?guardia_id=45',
                'method' => 'GET',
            ],
        ], $method->invoke(null, 45, false));
    }

    public function testTriageActionIsHiddenWithoutCapabilityOrForClosedEpisode(): void
    {
        $method = (new \ReflectionClass(EpisodioHistoriaBannerService::class))
            ->getMethod('triageActions');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke(null, 45, false, false, false));
        $this->assertSame([], $method->invoke(null, 45, false, true, true));
        $actions = $method->invoke(null, 45, true, false, true);
        $this->assertCount(1, $actions);
        $this->assertSame('Editar triage', $actions[0]['label']);
    }

    public function testInpatientEvolutionCanBeSerializedAsGenericSections(): void
    {
        $sections = EpisodioHistoriaBannerService::sectionsForInternacionEvolutions([
            [
                'encounter_id' => 123,
                'fecha' => '23/10/2026 08:00',
                'texto' => 'Control clínico.',
                'status' => 'finished',
            ],
            [
                'encounter_id' => 124,
                'fecha' => '23/10/2026 09:00',
                'texto' => 'Evolución sin status.',
                'status' => '',
            ],
        ]);

        $this->assertSame(
            ['inpatient_evolution', 'inpatient_evolution'],
            array_column($sections, 'key')
        );
        $this->assertSame([1, 2], array_column($sections, 'order'));
        $this->assertSame(
            ['encounter_id', 'fecha', 'texto', 'status'],
            array_column($sections[0]['fields'], 'key')
        );
        $this->assertSame(
            ['encounter_id', 'fecha', 'texto'],
            array_column($sections[1]['fields'], 'key')
        );
        $this->assertSame('Control clínico.', $sections[0]['fields'][2]['value']);
        $this->assertSame('integer', $sections[0]['fields'][0]['format']);
        $this->assertSame([], EpisodioHistoriaBannerService::sectionsForInternacionEvolutions([]));
    }
}
