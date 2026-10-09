<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideIaResponseParser;

class GuideIaResponseParserTest extends Unit
{
    public function testParseValidJson(): void
    {
        $parsed = GuideIaResponseParser::parse(<<<'JSON'
{
  "mensaje": "Configurá la representación primero.",
  "botones": []
}
JSON);

        $this->assertNotNull($parsed);
        $this->assertSame('Configurá la representación primero.', $parsed['mensaje']);
        $this->assertSame([], $parsed['botones']);
    }

    public function testParseJsonWithButtonAndParams(): void
    {
        $parsed = GuideIaResponseParser::parse(
            '{"mensaje":"Tocá el botón.","botones":[{"intent_id":"atencion.necesito-atencion","params":{"motivo":"malestar_nuevo"}}]}'
        );

        $this->assertNotNull($parsed);
        $this->assertSame('Tocá el botón.', $parsed['mensaje']);
        $this->assertCount(1, $parsed['botones']);
        $this->assertSame('atencion.necesito-atencion', $parsed['botones'][0]['intent_id']);
        $this->assertSame(['motivo' => 'malestar_nuevo'], $parsed['botones'][0]['params']);
    }

    public function testParseBotonesConsideradosWithIdAlias(): void
    {
        $parsed = GuideIaResponseParser::parse(<<<'JSON'
{
  "mensaje": "Tocá el botón de abajo.",
  "botones_considerados": [
    {
      "id": "atencion.necesito-atencion",
      "texto_boton": "Solicitar Atención",
      "params": { "motivo": "malestar_nuevo", "zona": "zona_pecho" }
    }
  ]
}
JSON);

        $this->assertNotNull($parsed);
        $this->assertSame('Tocá el botón de abajo.', $parsed['mensaje']);
        $this->assertCount(1, $parsed['botones']);
        $this->assertSame('atencion.necesito-atencion', $parsed['botones'][0]['intent_id']);
        $this->assertSame(
            ['motivo' => 'malestar_nuevo', 'zona' => 'zona_pecho'],
            $parsed['botones'][0]['params']
        );
    }

    public function testParseJsonInMarkdownFence(): void
    {
        $raw = "```json\n{\"mensaje\":\"Hola\",\"botones\":[]}\n```";
        $parsed = GuideIaResponseParser::parse($raw);

        $this->assertNotNull($parsed);
        $this->assertSame('Hola', $parsed['mensaje']);
    }

    public function testParseRejectsProse(): void
    {
        $this->assertNull(GuideIaResponseParser::parse(
            'No puedo ayudarte con la medicación para tu amigo.'
        ));
    }

    public function testInterpretRespectsEmptyBotones(): void
    {
        $allowed = [[
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
        ]];
        $raw = '{"mensaje":"Primero configurá la representación.","botones":[]}';
        $out = GuideChannel::interpretGuideIa($raw, $allowed);

        $this->assertTrue($out['parsed']);
        $this->assertSame('Primero configurá la representación.', $out['text']);
        $this->assertSame([], $out['buttons']);
    }

    public function testInterpretFiltersUnknownIntent(): void
    {
        $allowed = [[
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
        ]];
        $raw = '{"mensaje":"Ok","botones":[{"intent_id":"inventado.foo"},{"intent_id":"atencion.necesito-atencion"}]}';
        $out = GuideChannel::interpretGuideIa($raw, $allowed);

        $this->assertTrue($out['parsed']);
        $this->assertCount(1, $out['buttons']);
        $this->assertSame('atencion.necesito-atencion', $out['buttons'][0]['intent_id']);
        $this->assertSame('Solicitar Atención', $out['buttons'][0]['label']);
    }

    public function testInterpretFallbackKeepsAllowedButtons(): void
    {
        $allowed = [[
            'label' => 'Solicitar Atención',
            'intent_id' => 'atencion.necesito-atencion',
        ]];
        $out = GuideChannel::interpretGuideIa(
            'No puedo ayudarte con la medicación para tu amigo.',
            $allowed
        );

        $this->assertFalse($out['parsed']);
        $this->assertSame($allowed, $out['buttons']);
        $this->assertStringContainsString('medicación', $out['text']);
    }
}
