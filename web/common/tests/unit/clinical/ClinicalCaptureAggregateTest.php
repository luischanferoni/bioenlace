<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCapture;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureId;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;

/**
 * Aggregate ClinicalCapture — transiciones sin Yii/DB.
 */
class ClinicalCaptureAggregateTest extends Unit
{
    public function testStartAndMarkTranscribed(): void
    {
        $c = ClinicalCapture::start('client-1', 10, 20);
        verify($c->stage())->equals(ClinicalCaptureStage::UPLOADED);
        verify($c->isOpen())->true();

        $c->markTranscribed('Paciente con fiebre', ['provenance' => 'server']);
        verify($c->stage())->equals(ClinicalCaptureStage::TRANSCRIBED);
        verify($c->transcript())->equals('Paciente con fiebre');
        verify($c->attemptsStt())->equals(1);
        verify($c->sttMeta()['provenance'])->equals('server');
    }

    public function testMarkReadyForReviewAndComplete(): void
    {
        $c = ClinicalCapture::start('client-2', 1, 2);
        $c->markTranscribed('nota');
        $c->markReadyForReview('nota limpia', ['Motivos' => [['texto' => 'fiebre']]], ['ok' => true], 'tok', ['a::0'], 99);
        verify($c->stage())->equals(ClinicalCaptureStage::READY_FOR_REVIEW);
        verify($c->encounterId())->equals(99);
        verify($c->stagedItemIds())->equals(['a::0']);

        $c->complete(100);
        verify($c->stage())->equals(ClinicalCaptureStage::COMPLETED);
        verify($c->encounterId())->equals(100);
        verify($c->isOpen())->false();
    }

    public function testDiscardFromOpenAndRejectFromCompleted(): void
    {
        $c = ClinicalCapture::start('client-3', 1, 2);
        $c->discard();
        verify($c->stage())->equals(ClinicalCaptureStage::DISCARDED);

        $done = ClinicalCapture::reconstitute(
            ClinicalCaptureId::fromInt(5),
            'client-4',
            1,
            2,
            ClinicalCaptureStage::COMPLETED,
            null,
            null,
            1,
            null,
            null,
            't',
            null,
            [],
            [],
            [],
            null,
            [],
            null,
            0,
            0,
            1
        );
        $this->expectException(\InvalidArgumentException::class);
        $done->discard();
    }

    public function testAcceptInitialTranscriptDoesNotCountSttAttempt(): void
    {
        $c = ClinicalCapture::start('client-6', 1, 2);
        $c->acceptInitialTranscript('texto device', ['provenance' => 'device']);
        verify($c->stage())->equals(ClinicalCaptureStage::TRANSCRIBED);
        verify($c->attemptsStt())->equals(0);
    }

    public function testApplyResolutionSnapshot(): void
    {
        $c = ClinicalCapture::start('client-7', 1, 2);
        $c->acceptInitialTranscript('nota');
        $c->markReadyForReview('nota', ['X' => []], ['ok' => true]);
        $c->applyResolutionSnapshot(['X' => [['a' => 1]]], ['ok' => true, 'datosExtraidos' => ['X' => [['a' => 1]]]]);
        verify($c->stage())->equals(ClinicalCaptureStage::READY_FOR_REVIEW);
        verify($c->attemptsAnalysis())->equals(1);
        verify($c->datosExtraidos()['X'][0]['a'])->equals(1);
    }

    public function testResetAfterAudioReplace(): void
    {
        $c = ClinicalCapture::start('client-8', 1, 2);
        $c->acceptInitialTranscript('viejo');
        $c->markReadyForReview('viejo', ['A' => []], ['x' => 1], 'tok', ['id1']);
        $c->attachAudio('uploads/encounter_capture/x/a.m4a', 'audio/mp4');
        $c->resetAfterAudioReplace();
        verify($c->stage())->equals(ClinicalCaptureStage::UPLOADED);
        verify($c->hasTranscript())->false();
        verify($c->datosExtraidos())->equals([]);
        verify($c->hasAudio())->true();
    }
}
