<?php

namespace common\tests\unit\platform\ai;

use Codeception\Test\Unit;
use common\components\Platform\Ai\Prompt\YamlPrompt;

class YamlPromptTest extends Unit
{
    private const BASE = __DIR__ . '/../../../../components/';

    /** @return array<string, list<string>> archivo => placeholders esperados */
    private function prompts(): array
    {
        return [
            'Platform/Ai/Prompt/medical-word-correction.yaml' => ['especialidad', 'contexto', 'palabra'],
            'Platform/Ai/Prompt/abbreviation-disambiguation.yaml' => ['abreviatura', 'contexto', 'opciones'],
            'Domain/Clinical/Encounter/Application/Prompt/appointment-reason-summary.yaml' => ['patient_block', 'image_block', 'transcript'],
            'Domain/Clinical/Encounter/Application/Prompt/appointment-reason-insights.yaml' => ['motivos_resumen', 'patient_block'],
            'Domain/Clinical/CareCohort/Application/Prompt/care-pack.yaml' => ['profile_json', 'encounter_hint', 'delta_hint', 'context_block', 'schema'],
        ];
    }

    public function testEveryPromptHasVersionAndDocumentedPlaceholders(): void
    {
        foreach ($this->prompts() as $rel => $expected) {
            $file = self::BASE . $rel;
            $this->assertGreaterThanOrEqual(1, YamlPrompt::version($file), $rel);
            $found = YamlPrompt::placeholders($file);
            sort($found);
            sort($expected);
            $this->assertSame($expected, $found, $rel);
        }
    }

    public function testRenderRejectsMissingAndExtraValues(): void
    {
        $file = self::BASE . 'Platform/Ai/Prompt/medical-word-correction.yaml';
        $vars = ['especialidad' => 'x', 'contexto' => 'y', 'palabra' => 'z'];

        $this->assertStringContainsString('médico en x.', YamlPrompt::render($file, $vars));

        try {
            YamlPrompt::render($file, ['especialidad' => 'x']);
            $this->fail('Debía fallar por placeholders sin valor');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('contexto', $e->getMessage());
        }

        $this->expectException(\LogicException::class);
        YamlPrompt::render($file, $vars + ['sobra' => '1']);
    }

    public function testValuesWithBracesAreNotReinterpreted(): void
    {
        $file = self::BASE . 'Platform/Ai/Prompt/medical-word-correction.yaml';
        $out = YamlPrompt::render($file, ['especialidad' => '{contexto}', 'contexto' => 'y', 'palabra' => 'z']);

        $this->assertStringContainsString('en {contexto}.', $out);
    }

    public function testCarePackSchemasExistForEveryPackType(): void
    {
        $file = self::BASE . 'Domain/Clinical/CareCohort/Application/Prompt/care-pack.yaml';
        foreach (['assistance_questions', 'followup_program', 'education_bundle'] as $type) {
            $this->assertStringStartsWith('Esquema:', YamlPrompt::text($file, 'schema_' . $type));
        }
    }
}
