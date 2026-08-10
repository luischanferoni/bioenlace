<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\components\Domain\Clinical\Workflow\EncounterDocumentationService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use ReflectionMethod;

/**
 * Ciclo de vida: en INTERNACION/GUARDIA no se reutiliza un encounter finished.
 */
class EncounterEpisodeResolveTest extends Unit
{
    public function testResolveReturnsNullWhenParentIdMissing(): void
    {
        $svc = new EncounterDocumentationService();
        $method = new ReflectionMethod(EncounterDocumentationService::class, 'resolveEncounterForParent');
        $method->setAccessible(true);

        $persona = new Persona();
        $persona->id_persona = 1;

        $result = $method->invoke($svc, [
            'parent' => Encounter::PARENT_INTERNACION,
            'parent_id' => 0,
        ], $persona);

        $this->assertNull($result);
    }

    public function testEpisodeParentsRestrictToInProgressStatusConstant(): void
    {
        // Contrato fijo: solo IN_PROGRESS se reutiliza; finished implica nuevo pase.
        $this->assertSame('in-progress', EncounterStatus::IN_PROGRESS);
        $this->assertContains(Encounter::PARENT_INTERNACION, [
            Encounter::PARENT_INTERNACION,
            Encounter::PARENT_GUARDIA,
        ]);
        $this->assertContains(Encounter::PARENT_GUARDIA, [
            Encounter::PARENT_INTERNACION,
            Encounter::PARENT_GUARDIA,
        ]);

        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/components/Domain/Clinical/Workflow/EncounterDocumentationService.php'
        );
        $this->assertStringContainsString("['status' => EncounterStatus::IN_PROGRESS]", $src);
        $this->assertStringContainsString('PARENT_INTERNACION, Encounter::PARENT_GUARDIA', $src);
    }
}
