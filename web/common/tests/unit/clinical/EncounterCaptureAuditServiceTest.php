<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Service\EncounterCaptureAuditService;
use common\models\Clinical\EncounterCaptureAudit;

class EncounterCaptureAuditServiceTest extends Unit
{
    public function testEventTypeCatalog(): void
    {
        $values = EncounterCaptureAudit::eventTypeValues();
        $this->assertContains(EncounterCaptureAudit::EVENT_UPLOADED, $values);
        $this->assertContains(EncounterCaptureAudit::EVENT_SAVED, $values);
        $this->assertContains(EncounterCaptureAudit::EVENT_STT_FAILED, $values);
        $this->assertCount(9, $values);
    }

    public function testBuildAnalyzedMetaCountsSourcesAndIssues(): void
    {
        $review = [
            'puede_confirmar' => true,
            'default_staged_item_ids' => ['Motivos::0', 'Diagnósticos::0'],
            'issues' => [
                ['id' => 'issue-1'],
                ['id' => 'issue-2'],
            ],
            'categories' => [
                [
                    'title' => 'Motivos',
                    'items' => [
                        ['id' => 'Motivos::0', 'source' => 'clinical', 'label' => 'Cefalea'],
                    ],
                ],
                [
                    'title' => 'Diagnósticos',
                    'items' => [
                        ['id' => 'Diagnósticos::0', 'source' => 'clinical', 'label' => 'Migraña'],
                        ['id' => 'Diagnósticos::1', 'source' => 'ai', 'label' => 'Cefalea tensional'],
                    ],
                ],
            ],
        ];

        $meta = EncounterCaptureAuditService::buildAnalyzedMeta($review);
        $this->assertSame(2, $meta['item_counts']['clinical']);
        $this->assertSame(1, $meta['item_counts']['ai']);
        $this->assertSame(2, $meta['issues_count']);
        $this->assertTrue($meta['puede_confirmar']);
        $this->assertSame(2, $meta['default_staged_count']);
    }

    public function testBuildAcceptanceMetaAiAcceptedRejectedAndClinicalDeselected(): void
    {
        $review = [
            'default_staged_item_ids' => ['Motivos::0', 'Diagnósticos::0'],
            'categories' => [
                [
                    'title' => 'Motivos',
                    'items' => [
                        ['id' => 'Motivos::0', 'source' => 'clinical', 'label' => 'Cefalea'],
                    ],
                ],
                [
                    'title' => 'Diagnósticos',
                    'items' => [
                        ['id' => 'Diagnósticos::0', 'source' => 'clinical', 'label' => 'Migraña'],
                        ['id' => 'Diagnósticos::1', 'source' => 'ai', 'label' => 'Cefalea tensional'],
                        ['id' => 'Diagnósticos::2', 'source' => 'ai', 'label' => 'Sinusitis'],
                    ],
                ],
            ],
        ];

        // Profesional acepta una sugerencia IA, rechaza otra, y destilda un clinical.
        $finalStaged = ['Motivos::0', 'Diagnósticos::1'];

        $meta = EncounterCaptureAuditService::buildAcceptanceMeta(
            $review,
            $finalStaged,
            ['issue-plazo' => 7]
        );

        $this->assertSame(['Diagnósticos::1'], $meta['ai_accepted_ids']);
        $this->assertSame(['Diagnósticos::2'], $meta['ai_rejected_ids']);
        $this->assertSame(['Diagnósticos::0'], $meta['clinical_deselected_ids']);
        $this->assertSame(1, $meta['summary']['ai_accepted']);
        $this->assertSame(1, $meta['summary']['ai_rejected']);
        $this->assertSame(1, $meta['summary']['clinical_deselected']);
        $this->assertSame(1, $meta['counts_by_category']['Diagnósticos']['ai_accepted']);
        $this->assertSame(1, $meta['counts_by_category']['Diagnósticos']['ai_rejected']);
        $this->assertSame(1, $meta['counts_by_category']['Diagnósticos']['clinical_deselected']);
        $this->assertSame(1, $meta['counts_by_category']['Motivos']['clinical_kept']);
        $this->assertSame(['issue-plazo' => 7], $meta['resolutions']);
    }

    public function testBuildAcceptanceMetaEmptyReview(): void
    {
        $meta = EncounterCaptureAuditService::buildAcceptanceMeta([], ['x']);
        $this->assertSame([], $meta['ai_accepted_ids']);
        $this->assertSame([], $meta['ai_rejected_ids']);
        $this->assertSame([], $meta['clinical_deselected_ids']);
        $this->assertSame(1, $meta['final_staged_count']);
        $this->assertArrayNotHasKey('resolutions', $meta);
    }
}
