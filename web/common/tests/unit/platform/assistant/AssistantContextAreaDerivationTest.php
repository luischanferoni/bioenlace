<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Context\AssistantContextAreaDerivation;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;

class AssistantContextAreaDerivationTest extends Unit
{
    public function testDerivesDomainFromIntentToolRef(): void
    {
        $entry = new SmartCatalogEntry(
            'turnos-crear',
            false,
            'intent',
            'turnos.crear-como-paciente',
            'intent:turnos.crear-como-paciente',
            PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT,
            85,
            ['sacar_turno'],
            ['scheduling'],
            [],
            [],
            [],
            [],
            '',
            []
        );

        $areas = AssistantContextAreaDerivation::fromEntry($entry);

        $this->assertSame([AssistantContextHISArea::SCHEDULING], $areas);
    }

    public function testDerivesMultipleDomainsFromCtas(): void
    {
        $entry = new SmartCatalogEntry(
            'agenda-pedido-sin-destino',
            true,
            '',
            '',
            '',
            PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT,
            88,
            ['pedido_turno_sin_destino'],
            ['scheduling'],
            [],
            [],
            [],
            [],
            '',
            ['turnos.crear-como-paciente', 'atencion.necesito-atencion']
        );

        $areas = AssistantContextAreaDerivation::fromEntry($entry);

        $this->assertContains(AssistantContextHISArea::SCHEDULING, $areas);
        $this->assertContains(AssistantContextHISArea::CLINICAL, $areas);
    }

    public function testFromMatchNullSafe(): void
    {
        $this->assertSame([], AssistantContextAreaDerivation::fromMatch(null));
        $empty = new SmartCatalogMatchResult([], null, 0, false);
        $this->assertSame([], AssistantContextAreaDerivation::fromMatch($empty));
    }
}
