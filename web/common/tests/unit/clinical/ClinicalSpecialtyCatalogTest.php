<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\components\Domain\Clinical\Specialty\Domain\EncounterDefinitionSpecialtyRegistry;
use common\models\Clinical\EncounterDefinition;

/**
 * El catálogo es la fuente única de especialidades, secciones y campos.
 * Los tests comparan contra esa fuente (los modelos de captura), no contra literales.
 */
class ClinicalSpecialtyCatalogTest extends Unit
{
    public function testEveryDefinitionKeyMatchesItsCode(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $key => $definition) {
            $this->assertSame($key, $definition['code']);
            $this->assertNotSame('', $definition['label']);
            $this->assertNotSame('', $definition['public_copy']);
            $this->assertContains($definition['status'], [
                ClinicalSpecialtyCatalog::STATUS_SPECIFIC,
                ClinicalSpecialtyCatalog::STATUS_STANDARD,
                ClinicalSpecialtyCatalog::STATUS_PLANNED,
            ]);
        }
    }

    public function testSpecificSpecialtiesDeclareSectionsAndOthersDoNot(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $definition) {
            if ($definition['status'] === ClinicalSpecialtyCatalog::STATUS_SPECIFIC) {
                $this->assertNotSame([], $definition['sections'], $definition['code']);
            } else {
                $this->assertSame([], $definition['sections'], $definition['code']);
            }
        }
    }

    public function testSectionKeysAreUniqueWithinSpecialty(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            $sectionKeys = array_column($definition['sections'], 'key');
            $this->assertSame($sectionKeys, array_values(array_unique($sectionKeys)), $code);
        }
    }

    public function testCatalogNeverOmitsAFieldThePromptAsksFor(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            foreach ($definition['sections'] as $section) {
                $promptFields = EncounterDefinition::camposRequeridosDelModelo($section['key']);
                $catalogFields = array_column($section['fields'], 'key');

                foreach ($promptFields as $field) {
                    $this->assertContains(
                        $field,
                        $catalogFields,
                        "{$code}/{$section['key']}: el prompt pide «{$field}» y el catálogo no lo declara."
                    );
                }
            }
        }
    }

    public function testExtractedFieldsAreAlwaysPrompted(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            foreach ($definition['sections'] as $section) {
                $promptFields = EncounterDefinition::camposRequeridosDelModelo($section['key']);
                foreach ($section['fields'] as $field) {
                    if (($field['extracted'] ?? true) !== true) {
                        continue;
                    }
                    $this->assertContains(
                        $field['key'],
                        $promptFields,
                        "{$code}/{$section['key']}: «{$field['key']}» se marca extraído pero el prompt no lo pide."
                    );
                }
            }
        }
    }

    public function testOdontologyExtractionCoversEveryPromptField(): void
    {
        $extracted = ClinicalSpecialtyCatalog::extractedFieldsFor(ClinicalSpecialtyCatalog::ODONTOLOGY);

        foreach (ClinicalSpecialtyCatalog::sectionsFor(ClinicalSpecialtyCatalog::ODONTOLOGY) as $section) {
            $promptFields = EncounterDefinition::camposRequeridosDelModelo($section['key']);
            $this->assertSame(
                $promptFields,
                array_keys($extracted),
                "{$section['key']}: los campos extraídos deben ser exactamente los del prompt."
            );
        }
    }

    public function testSpecialtyForCaptureModelResolvesEveryDeclaredSection(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            foreach ($definition['sections'] as $section) {
                $this->assertSame(
                    $code,
                    ClinicalSpecialtyCatalog::specialtyForCaptureModel($section['key'])
                );
                $this->assertSame(
                    $code,
                    ClinicalSpecialtyCatalog::specialtyForCaptureModel('\\common\\models\\Clinical\\' . $section['key'])
                );
            }
        }
        $this->assertNull(ClinicalSpecialtyCatalog::specialtyForCaptureModel('EncounterMedication'));
        $this->assertNull(ClinicalSpecialtyCatalog::specialtyForCaptureModel(''));
    }

    public function testStandardSpecialtiesReuseTheStandardWorkflow(): void
    {
        $labels = ClinicalSpecialtyCatalog::standardSectionLabels();
        $this->assertNotSame([], $labels);

        $standard = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::GENERAL_MEDICINE);
        $this->assertNotNull($standard);
        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_STANDARD, $standard['status']);
        $this->assertSame([], $standard['sections']);
    }

    public function testRegistryMetadataComesFromCatalog(): void
    {
        $registry = new EncounterDefinitionSpecialtyRegistry();
        $metadata = $registry->metadataForSpecialties([
            ClinicalSpecialtyCatalog::ODONTOLOGY,
            ClinicalSpecialtyCatalog::OPHTHALMOLOGY,
        ]);

        $this->assertSame(
            [ClinicalSpecialtyCatalog::ODONTOLOGY, ClinicalSpecialtyCatalog::OPHTHALMOLOGY],
            array_column($metadata, 'code')
        );
        foreach ($metadata as $entry) {
            $definition = ClinicalSpecialtyCatalog::get($entry['code']);
            $this->assertSame($definition['label'], $entry['label']);
            $this->assertSame($definition['sections'], $entry['sections']);
        }
    }

    public function testUnknownSpecialtyIsNotResolved(): void
    {
        $registry = new EncounterDefinitionSpecialtyRegistry();
        $this->assertSame([], $registry->metadataForSpecialties(['unknown']));
        $this->assertFalse(ClinicalSpecialtyCatalog::isValid('unknown'));
        $this->assertNull(ClinicalSpecialtyCatalog::get('unknown'));
    }
}
