<?php

namespace common\tests\unit\platform\assistant;

use common\components\Platform\Assistant\UiActions\ActionDiscoveryService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once dirname(__DIR__, 5) . '/common/components/Platform/Assistant/UiActions/ActionDiscoveryService.php';

final class EncounterControllerAssistantMetadataTest extends TestCase
{
    public function testEncounterControllerEntityAnnotationsUseCanonicalNames(): void
    {
        $metadataExtractor = new ReflectionMethod(ActionDiscoveryService::class, 'extractCustomMetadata');
        $metadataExtractor->setAccessible(true);
        $definitions = [
            ['EncounterAsyncController.php', 'actionSolicitarComoPaciente', 'EncounterAsync'],
            ['EncounterAsyncController.php', 'actionTomarComoStaff', 'EncounterAsync'],
            ['EncounterFollowupController.php', 'actionHub', 'EncounterFollowup'],
            ['EncounterFollowupController.php', 'actionCondicionAcciones', 'EncounterFollowup'],
            ['EncounterFollowupController.php', 'actionPaso', 'EncounterFollowup'],
        ];
        $controllerDirectory = dirname(__DIR__, 5) . '/frontend/modules/api/v1/controllers/scheduling/';

        foreach ($definitions as [$fileName, $methodName, $expectedEntity]) {
            $source = file_get_contents($controllerDirectory . $fileName);
            $this->assertNotFalse($source, $fileName);
            $pattern = '/\/\*\*(.*?)\*\/\s*public function\s+' . preg_quote($methodName, '/') . '\s*\(/s';
            $this->assertSame(1, preg_match($pattern, $source, $matches), $fileName . '::' . $methodName);
            $metadata = $metadataExtractor->invoke(
                null,
                '/**' . $matches[1] . '*/',
                '',
                $methodName
            );
            $this->assertSame($expectedEntity, $metadata['entity'], $fileName . '::' . $methodName);
        }
    }
}
