<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EpisodeCaptureDedupService;
use common\models\Clinical\Encounter;

class EpisodeCaptureDedupServiceTest extends Unit
{
    public function testNormalizeNoteCollapsesCaseAndWhitespace(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $this->assertSame(
            'hola mundo clinico',
            $svc->normalizeNote("  Hola   MUNDO\nclínico  ")
        );
    }

    public function testNearIdenticalNotesReachSimilarityThreshold(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $base = 'Evolución día 2 de internación por neumonía comunitaria. '
            . 'Paciente afebril, Sat estable aire ambiente, continúa antibiótico EV '
            . 'y régimen general. Balance hídrico equilibrado. Control laboratorio mañana.';
        $a = $svc->normalizeNote($base);
        $b = $svc->normalizeNote(str_replace('neumonía', 'neumonia', $base));
        similar_text($a, $b, $percent);
        $this->assertGreaterThanOrEqual(
            EpisodeCaptureDedupService::NOTE_SIMILARITY_THRESHOLD * 100,
            $percent
        );
    }

    public function testAnalyzeDoesNotApplyOutsideEpisodeParents(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $out = $svc->analyze(Encounter::PARENT_TURNO, 10, 1, str_repeat('nota clínica ', 20), []);
        $this->assertFalse($out['applies']);
        $this->assertFalse($out['note_duplicate']);
        $this->assertSame([], $out['duplicate_item_ids']);
    }

    public function testApplyToReviewBlocksNoteDuplicateAndFiltersStaged(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $review = [
            'categories' => [
                [
                    'title' => 'Diagnósticos',
                    'items' => [
                        ['id' => 'Diagnósticos::0', 'label' => 'Neumonía', 'subtitle' => ''],
                        ['id' => 'Diagnósticos::1', 'label' => 'HTA', 'subtitle' => 'I10'],
                    ],
                ],
            ],
            'default_staged_item_ids' => ['Diagnósticos::0', 'Diagnósticos::1'],
            'tiene_datos_faltantes' => false,
            'puede_confirmar' => true,
        ];
        $dedup = [
            'applies' => true,
            'note_duplicate' => true,
            'duplicate_item_ids' => ['Diagnósticos::0'],
            'advisories' => [
                [
                    'code' => EpisodeCaptureDedupService::ADVISORY_NOTE_DUPLICATE,
                    'severity' => 'danger',
                    'message' => 'Nota casi idéntica a una evolución previa.',
                ],
                [
                    'code' => EpisodeCaptureDedupService::ADVISORY_ITEMS_ACTIVE,
                    'severity' => 'warning',
                    'message' => 'Algunos ítems ya están activos.',
                ],
            ],
        ];

        $out = $svc->applyToReview($review, $dedup);

        $this->assertFalse($out['puede_confirmar']);
        $this->assertTrue($out['tiene_datos_faltantes']);
        $this->assertSame(['Diagnósticos::1'], $out['default_staged_item_ids']);
        $this->assertTrue($out['categories'][0]['items'][0]['already_active']);
        $this->assertStringContainsString(
            'Ya activo en el episodio',
            (string) $out['categories'][0]['items'][0]['subtitle']
        );
        $this->assertArrayNotHasKey('already_active', $out['categories'][0]['items'][1]);
        $this->assertCount(2, $out['advisories']);
        $this->assertTrue(($out['datos_faltantes_detalle']['episode_note_duplicate'] ?? false) === true);
    }

    public function testApplyToReviewNoOpWhenDoesNotApply(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $review = ['puede_confirmar' => true, 'default_staged_item_ids' => ['A::0']];
        $out = $svc->applyToReview($review, ['applies' => false, 'note_duplicate' => true]);
        $this->assertTrue($out['puede_confirmar']);
        $this->assertSame(['A::0'], $out['default_staged_item_ids']);
    }

    public function testHasActiveHelpersReturnFalseWithoutIds(): void
    {
        $svc = new EpisodeCaptureDedupService();
        $this->assertFalse($svc->hasActiveConditionKey(1, [], 'neumonia'));
        $this->assertFalse($svc->hasActiveMedicationDisplay([], 'amoxicilina'));
        $this->assertFalse($svc->hasActiveConditionKey(0, [1], 'neumonia'));
    }
}
