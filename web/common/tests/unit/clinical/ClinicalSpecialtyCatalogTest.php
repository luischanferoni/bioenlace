<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\EnfermeriaSignosVitalesRowContract;
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

    public function testSpecificSpecialtiesDeclareOwnSectionsAndOthersDoNot(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $definition) {
            if ($definition['status'] === ClinicalSpecialtyCatalog::STATUS_SPECIFIC) {
                $this->assertNotSame([], $definition['sections'], $definition['code']);
                $this->assertSame([], $definition['extra_sections'], $definition['code']);
            } else {
                $this->assertSame([], $definition['sections'], $definition['code']);
            }
            if ($definition['status'] === ClinicalSpecialtyCatalog::STATUS_PLANNED) {
                $this->assertSame([], $definition['extra_sections'], $definition['code']);
            }
        }
    }

    public function testSectionKeysAreUniqueWithinSpecialty(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            $sectionKeys = array_column(
                array_merge($definition['sections'], $definition['extra_sections']),
                'key'
            );
            $this->assertSame($sectionKeys, array_values(array_unique($sectionKeys)), $code);
        }
    }

    public function testExtraSectionsNeverClaimTheSpecialty(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            foreach ($definition['extra_sections'] as $section) {
                $this->assertNotSame(
                    $code,
                    ClinicalSpecialtyCatalog::specialtyForCaptureModel($section['key']),
                    "{$code}: una categoría adicional no puede identificar a la especialidad."
                );
            }
        }
    }

    public function testPediatricsAddsAnthropometryOnTopOfTheStandardWorkflow(): void
    {
        $pediatrics = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::PEDIATRICS);

        $this->assertNotNull($pediatrics);
        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_STANDARD, $pediatrics['status']);
        $this->assertSame([], $pediatrics['sections']);

        $extra = ClinicalSpecialtyCatalog::extraSectionsFor(ClinicalSpecialtyCatalog::PEDIATRICS);
        $this->assertCount(1, $extra);
        $this->assertSame('EncounterNursingCare', $extra[0]['key']);
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            array_column($extra[0]['fields'], 'key')
        );
        // La validación pide al menos una medida antropométrica.
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::ANTHROPOMETRY_FIELDS,
            $extra[0]['validation']['fields']
        );

        $units = [
            'Peso' => 'kg',
            'Talla' => 'cm',
            'Perimetro cefalico' => 'cm',
            'Circunferencia abdominal' => 'cm',
            'TA sistolica' => 'mmHg',
            'Frecuencia cardiaca' => 'lpm',
            'Saturacion O2' => '%',
            'Temperatura' => '°C',
        ];
        foreach ($extra[0]['fields'] as $field) {
            $expectedFormat = in_array($field['key'], EnfermeriaSignosVitalesRowContract::ANTHROPOMETRY_FIELDS, true) ? 'decimal' : null;
            if ($expectedFormat !== null) {
                $this->assertSame($expectedFormat, $field['format'], $field['key']);
            }
            if (isset($units[$field['key']])) {
                $this->assertSame($units[$field['key']], $field['unit'], $field['key']);
            }
            $this->assertTrue($field['extracted'], $field['key']);
            $this->assertContains(
                $field['key'],
                EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
                "«{$field['key']}» se declara pero el contrato no lo pide."
            );
        }
    }

    public function testAnthropometryFeedsTheGrowthCurvesKeys(): void
    {
        // Las curvas leen estas claves de atenciones_enfermeria.datos.
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'Peso' => '12.5',
            'Talla' => '88',
            'Perimetro cefalico' => '47',
            'Circunferencia abdominal' => '50',
        ]);

        $this->assertArrayHasKey('peso', $datos);
        $this->assertArrayHasKey('talla', $datos);
        $this->assertArrayHasKey('perimetro_cefalico', $datos);
        $this->assertArrayHasKey('circunferencia_abdominal', $datos);
    }

    public function testCatalogNeverOmitsAFieldThePromptAsksFor(): void
    {
        foreach (ClinicalSpecialtyCatalog::all() as $code => $definition) {
            foreach (array_merge($definition['sections'], $definition['extra_sections']) as $section) {
                $promptFields = $this->promptFieldsFor($section['key']);
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
            foreach (array_merge($definition['sections'], $definition['extra_sections']) as $section) {
                $promptFields = $this->promptFieldsFor($section['key']);
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
            $promptFields = $this->promptFieldsFor($section['key']);
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

    public function testNursingDeclaresTheVitalsItInterprets(): void
    {
        $extra = ClinicalSpecialtyCatalog::extraSectionsFor(ClinicalSpecialtyCatalog::NURSING);

        $this->assertCount(1, $extra);
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            array_column($extra[0]['fields'], 'key')
        );
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            $extra[0]['validation']['fields']
        );
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            array_keys(ClinicalSpecialtyCatalog::extractedFieldsFor(ClinicalSpecialtyCatalog::NURSING))
        );
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
    /**
     * Campos que el prompt pide para una categoría.
     *
     * Prefiere el modelo de captura; si es un ActiveRecord que no puede instanciarse
     * en tests, usa su constante `PROMPT_FIELDS` (misma fuente).
     *
     * @return list<string>
     */
    private function promptFieldsFor(string $modelo): array
    {
        $fields = EncounterDefinition::camposRequeridosDelModelo($modelo);
        if ($fields !== []) {
            return $fields;
        }

        foreach (['\\common\\models\\Clinical\\', '\\common\\models\\'] as $prefix) {
            $class = $prefix . $modelo;
            if (class_exists($class) && defined($class . '::PROMPT_FIELDS')) {
                return $class::PROMPT_FIELDS;
            }
        }

        return [];
    }
}
