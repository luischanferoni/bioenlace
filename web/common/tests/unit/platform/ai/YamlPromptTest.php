<?php

namespace common\tests\unit\platform\ai;

use Codeception\Test\Unit;
use common\components\Platform\Ai\Prompt\PromptRegistry;
use common\components\Platform\Ai\Prompt\YamlPrompt;

class YamlPromptTest extends Unit
{
    /** @return array<string, list<string>> clave => placeholders esperados */
    private function prompts(): array
    {
        return [
            'clinical.appointment_reason_summary' => ['patient_block', 'image_block', 'transcript'],
            'clinical.appointment_reason_insights' => ['motivos_resumen', 'patient_block'],
            'clinical.care_pack' => ['profile_json', 'encounter_hint', 'delta_hint', 'context_block', 'schema'],
        ];
    }

    public function testEveryPromptHasVersionAndDocumentedPlaceholders(): void
    {
        foreach ($this->prompts() as $key => $expected) {
            $file = PromptRegistry::path($key);
            $this->assertGreaterThanOrEqual(1, YamlPrompt::version($file), $key);
            $found = YamlPrompt::placeholders($file);
            sort($found);
            sort($expected);
            $this->assertSame($expected, $found, $key);
        }
    }

    public function testEveryRegisteredPromptFileExists(): void
    {
        foreach (PromptRegistry::all() as $key => $file) {
            $this->assertFileExists($file, $key);
        }
    }

    public function testRenderRejectsMissingAndExtraValues(): void
    {
        $file = PromptRegistry::path('clinical.appointment_reason_insights');
        $vars = ['motivos_resumen' => 'MOTIVOS-X', 'patient_block' => 'PACIENTE-Y'];

        $out = YamlPrompt::render($file, $vars);
        $this->assertStringContainsString('MOTIVOS-X', $out);
        $this->assertStringContainsString('PACIENTE-Y', $out);

        try {
            YamlPrompt::render($file, ['motivos_resumen' => 'x']);
            $this->fail('Debía fallar por placeholders sin valor');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('patient_block', $e->getMessage());
        }

        $this->expectException(\LogicException::class);
        YamlPrompt::render($file, $vars + ['sobra' => '1']);
    }

    public function testValuesWithBracesAreNotReinterpreted(): void
    {
        $file = PromptRegistry::path('clinical.appointment_reason_insights');
        $out = YamlPrompt::render($file, ['motivos_resumen' => '{patient_block}', 'patient_block' => 'y']);

        $this->assertStringContainsString('{patient_block}', $out);
    }

    public function testCarePackSchemasExistForEveryPackType(): void
    {
        $file = PromptRegistry::path('clinical.care_pack');
        foreach (['assistance_questions', 'followup_program', 'education_bundle'] as $type) {
            $this->assertStringStartsWith('Esquema:', YamlPrompt::text($file, 'schema_' . $type));
        }
    }
}
