<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;

/**
 * La sección «Especialidades clínicas» del sitio institucional se genera desde el
 * catálogo; este test falla si alguien publica una lista desactualizada a mano.
 *
 * Misma verificación que el modo `--check` del generador, para que también falle en
 * la suite y no sólo en el hook de pre-commit.
 */
class InstitutionalSpecialtiesSectionTest extends Unit
{
    private const MARKER_BEGIN = '<!-- BEGIN:especialidades -->';
    private const MARKER_END = '<!-- END:especialidades -->';

    public function testSectionIsSynchronizedWithTheCatalog(): void
    {
        [, $exitCode] = $this->runGenerator('--check');

        $this->assertSame(
            0,
            $exitCode,
            "La sección de especialidades del sitio institucional está desfasada.\n"
            . 'Corré: php tools/generate-institutional-specialties.php'
        );
    }

    public function testSectionPublishesEveryConfiguredSpecialty(): void
    {
        $html = $this->generatedSection();

        foreach ($this->catalogCodes() as $label) {
            $this->assertStringContainsString(
                '<h3 class="especialidad-card__title">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h3>',
                $html,
                "«{$label}» no aparece en la sección institucional."
            );
        }
    }

    public function testSectionLivesBeforeThePricingSection(): void
    {
        $page = $this->indexHtml();

        $begin = strpos($page, self::MARKER_BEGIN);
        $pricing = strpos($page, '<section id="precios"');

        $this->assertNotFalse($begin);
        $this->assertNotFalse($pricing);
        $this->assertLessThan($pricing, $begin);
    }

    public function testEveryPublishedFieldIsOneTheCaptureAsksFor(): void
    {
        $html = $this->generatedSection();
        $chips = [];
        preg_match_all('/<li class="especialidad-chip">([^<]*)<\/li>/u', $html, $chips);

        $declared = [];
        foreach (\common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog::all() as $definition) {
            foreach (\common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog::extractedFieldsFor($definition['code']) as $label) {
                $declared[$label] = true;
            }
        }

        $this->assertNotSame([], $chips[1]);
        foreach ($chips[1] as $chip) {
            $this->assertArrayHasKey(
                html_entity_decode($chip, ENT_QUOTES, 'UTF-8'),
                $declared,
                "«{$chip}» se publica pero ninguna especialidad lo declara como campo interpretado."
            );
        }
    }

    /**
     * @return list<string> etiquetas de las especialidades publicables
     */
    private function catalogCodes(): array
    {
        $out = [];
        foreach (\common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog::all() as $definition) {
            if ($definition['status'] === \common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog::STATUS_PLANNED) {
                continue;
            }
            $out[] = $definition['label'];
        }

        return $out;
    }

    private function generatedSection(): string
    {
        [$output, $exitCode] = $this->runGenerator('--check');
        $this->assertSame(0, $exitCode, $output);

        $page = $this->indexHtml();
        $begin = strpos($page, self::MARKER_BEGIN) + strlen(self::MARKER_BEGIN);
        $end = strpos($page, self::MARKER_END);

        return substr($page, $begin, $end - $begin);
    }

    private function indexHtml(): string
    {
        $path = dirname(__DIR__, 5) . '/institucional/index.html';
        $html = file_get_contents($path);
        $this->assertNotFalse($html, "No se pudo leer {$path}");

        return (string) $html;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function runGenerator(string $argument): array
    {
        $php = PHP_BINARY;
        $script = dirname(__DIR__, 5) . '/tools/generate-institutional-specialties.php';
        $command = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($argument) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [implode("\n", $output), (int) $exitCode];
    }
}
