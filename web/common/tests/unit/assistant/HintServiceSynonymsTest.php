<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Service\HintEntityMatcher;
use common\components\Platform\Assistant\Service\HintServiceSynonyms;

class HintServiceSynonymsTest extends Unit
{
    protected function _after(): void
    {
        HintServiceSynonyms::resetForTests();
    }

    public function testDentistaResolvesToOdontologia(): void
    {
        $names = HintServiceSynonyms::servicioNamesForAlias('dentista');
        $this->assertContains('odontologia', $names);
    }

    public function testOculistaResolvesToOftalmologia(): void
    {
        $names = HintServiceSynonyms::servicioNamesForAlias('oculista');
        $this->assertContains('oftalmologia', $names);
    }

    public function testKinesioResolvesToKinesiologia(): void
    {
        $names = HintServiceSynonyms::servicioNamesForAlias('kinesio');
        $this->assertContains('kinesiologia', $names);
    }

    public function testClinicoResolvesToMedGeneral(): void
    {
        $names = HintServiceSynonyms::servicioNamesForAlias('clinico');
        $this->assertTrue(
            in_array('med general', $names, true) || in_array('med clinica', $names, true),
            'clinico should resolve to med general or med clinica'
        );
    }

    public function testEnrichTermsAddsSynonyms(): void
    {
        $terms = ['dentista'];
        $enriched = HintServiceSynonyms::enrichTerms($terms);
        $this->assertContains('dentista', $enriched);
        $this->assertContains('odontologia', $enriched);
    }

    public function testEnrichTermsNoopForUnknown(): void
    {
        $terms = ['xyzabc'];
        $enriched = HintServiceSynonyms::enrichTerms($terms);
        $this->assertSame(['xyzabc'], $enriched);
    }

    public function testMatcherUsesServiceSynonyms(): void
    {
        $candidates = [
            ['id' => '1', 'nombre' => 'ODONTOLOGIA'],
            ['id' => '2', 'nombre' => 'ENFERMERIA'],
        ];

        $result = HintEntityMatcher::match(['dentista'], $candidates, 'nombre', 'servicio');
        $this->assertNotNull($result, 'dentista should match ODONTOLOGIA via synonyms');
        $this->assertSame('1', $result['id']);
    }

    public function testMatcherWithoutEntityDoesNotEnrich(): void
    {
        $candidates = [
            ['id' => '1', 'nombre' => 'ODONTOLOGIA'],
        ];

        $result = HintEntityMatcher::match(['dentista'], $candidates, 'nombre');
        $this->assertNull($result, 'Without entity=servicio, dentista should not match ODONTOLOGIA');
    }

    public function testAccentInsensitive(): void
    {
        $names = HintServiceSynonyms::servicioNamesForAlias('cardiólogo');
        $this->assertContains('cardiologia', $names);
    }
}
