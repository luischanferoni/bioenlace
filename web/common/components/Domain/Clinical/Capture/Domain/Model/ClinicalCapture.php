<?php

namespace common\components\Domain\Clinical\Capture\Domain\Model;

/**
 * Aggregate: borrador de captura clínica por etapas (sin I/O ni Yii).
 *
 * Application reconstituye desde AR / repo, aplica mutaciones y persiste.
 */
final class ClinicalCapture
{
    /** @var ClinicalCaptureId|null */
    private $id;

    /** @var string */
    private $clientCaptureId;

    /** @var int */
    private $subjectPersonaId;

    /** @var int */
    private $createdByUserId;

    /** @var string */
    private $stage;

    /** @var string|null */
    private $parentType;

    /** @var int|null */
    private $parentId;

    /** @var int|null */
    private $encounterId;

    /** @var string|null */
    private $audioRelativePath;

    /** @var string|null */
    private $audioMime;

    /** @var string|null */
    private $transcript;

    /** @var string|null */
    private $textoProcesado;

    /** @var array<string, mixed> */
    private $sttMeta;

    /** @var array<string, mixed> */
    private $datosExtraidos;

    /** @var array<string, mixed> */
    private $analysisResponse;

    /** @var string|null */
    private $analysisCacheToken;

    /** @var list<string> */
    private $stagedItemIds;

    /** @var string|null */
    private $lastError;

    /** @var int */
    private $attemptsStt;

    /** @var int */
    private $attemptsAnalysis;

    /** @var int */
    private $attemptsSave;

    /**
     * @param array<string, mixed> $sttMeta
     * @param array<string, mixed> $datosExtraidos
     * @param array<string, mixed> $analysisResponse
     * @param list<string> $stagedItemIds
     */
    private function __construct(
        ?ClinicalCaptureId $id,
        string $clientCaptureId,
        int $subjectPersonaId,
        int $createdByUserId,
        string $stage,
        ?string $parentType,
        ?int $parentId,
        ?int $encounterId,
        ?string $audioRelativePath,
        ?string $audioMime,
        ?string $transcript,
        ?string $textoProcesado,
        array $sttMeta,
        array $datosExtraidos,
        array $analysisResponse,
        ?string $analysisCacheToken,
        array $stagedItemIds,
        ?string $lastError,
        int $attemptsStt,
        int $attemptsAnalysis,
        int $attemptsSave
    ) {
        $this->id = $id;
        $this->clientCaptureId = trim($clientCaptureId);
        $this->subjectPersonaId = $subjectPersonaId;
        $this->createdByUserId = $createdByUserId;
        $this->stage = ClinicalCaptureStage::normalize($stage);
        $this->parentType = $parentType !== null && trim($parentType) !== '' ? trim($parentType) : null;
        $this->parentId = $parentId !== null && $parentId > 0 ? $parentId : null;
        $this->encounterId = $encounterId !== null && $encounterId > 0 ? $encounterId : null;
        $this->audioRelativePath = $audioRelativePath;
        $this->audioMime = $audioMime;
        $this->transcript = $transcript;
        $this->textoProcesado = $textoProcesado;
        $this->sttMeta = $sttMeta;
        $this->datosExtraidos = $datosExtraidos;
        $this->analysisResponse = $analysisResponse;
        $this->analysisCacheToken = $analysisCacheToken;
        $this->stagedItemIds = array_values($stagedItemIds);
        $this->lastError = $lastError;
        $this->attemptsStt = max(0, $attemptsStt);
        $this->attemptsAnalysis = max(0, $attemptsAnalysis);
        $this->attemptsSave = max(0, $attemptsSave);

        if ($this->clientCaptureId === '' || strlen($this->clientCaptureId) > 64) {
            throw new \InvalidArgumentException('client_capture_id inválido.');
        }
        if ($this->subjectPersonaId <= 0 || $this->createdByUserId <= 0) {
            throw new \InvalidArgumentException('subject_persona_id / created_by_user_id inválidos.');
        }
    }

    public static function start(
        string $clientCaptureId,
        int $subjectPersonaId,
        int $createdByUserId,
        ?string $parentType = null,
        ?int $parentId = null
    ): self {
        return new self(
            null,
            $clientCaptureId,
            $subjectPersonaId,
            $createdByUserId,
            ClinicalCaptureStage::UPLOADED,
            $parentType,
            $parentId,
            null,
            null,
            null,
            null,
            null,
            [],
            [],
            [],
            null,
            [],
            null,
            0,
            0,
            0
        );
    }

    /**
     * @param array<string, mixed> $sttMeta
     * @param array<string, mixed> $datosExtraidos
     * @param array<string, mixed> $analysisResponse
     * @param list<string> $stagedItemIds
     */
    public static function reconstitute(
        ClinicalCaptureId $id,
        string $clientCaptureId,
        int $subjectPersonaId,
        int $createdByUserId,
        string $stage,
        ?string $parentType,
        ?int $parentId,
        ?int $encounterId,
        ?string $audioRelativePath,
        ?string $audioMime,
        ?string $transcript,
        ?string $textoProcesado,
        array $sttMeta,
        array $datosExtraidos,
        array $analysisResponse,
        ?string $analysisCacheToken,
        array $stagedItemIds,
        ?string $lastError,
        int $attemptsStt,
        int $attemptsAnalysis,
        int $attemptsSave
    ): self {
        return new self(
            $id,
            $clientCaptureId,
            $subjectPersonaId,
            $createdByUserId,
            $stage,
            $parentType,
            $parentId,
            $encounterId,
            $audioRelativePath,
            $audioMime,
            $transcript,
            $textoProcesado,
            $sttMeta,
            $datosExtraidos,
            $analysisResponse,
            $analysisCacheToken,
            $stagedItemIds,
            $lastError,
            $attemptsStt,
            $attemptsAnalysis,
            $attemptsSave
        );
    }

    public function assignId(ClinicalCaptureId $id): void
    {
        if ($this->id !== null) {
            throw new \LogicException('ClinicalCapture ya tiene id.');
        }
        $this->id = $id;
    }

    public function attachAudio(string $relativePath, ?string $mime): void
    {
        $this->assertOpen();
        $path = trim($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('audio_relative_path vacío.');
        }
        $this->audioRelativePath = $path;
        $this->audioMime = $mime !== null && trim($mime) !== '' ? trim($mime) : null;
    }

    /**
     * @param array<string, mixed> $sttMeta
     */
    public function markTranscribed(string $transcript, array $sttMeta = []): void
    {
        $this->assertOpen();
        $text = trim($transcript);
        if ($text === '') {
            throw new \InvalidArgumentException('Transcripción vacía.');
        }
        $this->transcript = $text;
        if ($sttMeta !== []) {
            $this->sttMeta = array_merge($this->sttMeta, $sttMeta);
        }
        $this->attemptsStt++;
        $this->stage = ClinicalCaptureStage::TRANSCRIBED;
        $this->lastError = null;
    }

    public function markSttFailed(string $error): void
    {
        $this->assertOpen();
        $this->attemptsStt++;
        $this->stage = ClinicalCaptureStage::STT_FAILED;
        $this->lastError = trim($error) !== '' ? trim($error) : 'Error de STT.';
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     * @param array<string, mixed> $analysisResponse
     * @param list<string>|null $stagedItemIds
     */
    public function markReadyForReview(
        string $textoProcesado,
        array $datosExtraidos,
        array $analysisResponse,
        ?string $analysisCacheToken = null,
        ?array $stagedItemIds = null,
        ?int $encounterId = null
    ): void {
        $this->assertOpen();
        $this->attemptsAnalysis++;
        $this->textoProcesado = trim($textoProcesado) !== '' ? trim($textoProcesado) : $this->transcript;
        $this->datosExtraidos = $datosExtraidos;
        $this->analysisResponse = $analysisResponse;
        $this->analysisCacheToken = $analysisCacheToken !== null && trim($analysisCacheToken) !== ''
            ? trim($analysisCacheToken)
            : null;
        if ($stagedItemIds !== null) {
            $this->stagedItemIds = array_values(array_map('strval', $stagedItemIds));
        }
        if ($encounterId !== null && $encounterId > 0) {
            $this->encounterId = $encounterId;
        }
        $this->stage = ClinicalCaptureStage::READY_FOR_REVIEW;
        $this->lastError = null;
    }

    public function markAnalysisFailed(string $error): void
    {
        $this->assertOpen();
        $this->attemptsAnalysis++;
        $this->stage = ClinicalCaptureStage::ANALYSIS_FAILED;
        $this->lastError = trim($error) !== '' ? trim($error) : 'Error al analizar.';
    }

    public function markSaveFailed(string $error): void
    {
        $this->assertOpen();
        $this->attemptsSave++;
        $this->stage = ClinicalCaptureStage::SAVE_FAILED;
        $this->lastError = trim($error) !== '' ? trim($error) : 'Error al guardar.';
    }

    public function complete(?int $encounterId = null): void
    {
        $this->assertOpen();
        if ($encounterId !== null && $encounterId > 0) {
            $this->encounterId = $encounterId;
        }
        $this->attemptsSave++;
        $this->stage = ClinicalCaptureStage::COMPLETED;
        $this->lastError = null;
    }

    public function discard(): void
    {
        if ($this->stage === ClinicalCaptureStage::DISCARDED) {
            return;
        }
        if ($this->stage === ClinicalCaptureStage::COMPLETED) {
            throw new \InvalidArgumentException('No se puede descartar una captura ya completada.');
        }
        $this->stage = ClinicalCaptureStage::DISCARDED;
        $this->lastError = null;
        $this->audioRelativePath = null;
        $this->audioMime = null;
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    public function replaceDatosExtraidos(array $datosExtraidos): void
    {
        $this->assertOpen();
        $this->datosExtraidos = $datosExtraidos;
    }

    /**
     * @param array<string, mixed> $analysisResponse
     */
    public function replaceAnalysisResponse(array $analysisResponse): void
    {
        $this->assertOpen();
        $this->analysisResponse = $analysisResponse;
    }

    /**
     * @param list<string> $ids
     */
    public function replaceStagedItemIds(array $ids): void
    {
        $this->assertOpen();
        $this->stagedItemIds = array_values(array_map('strval', $ids));
    }

    public function setTranscriptOverride(string $transcript): void
    {
        $this->assertOpen();
        $text = trim($transcript);
        if ($text === '') {
            throw new \InvalidArgumentException('Transcripción vacía.');
        }
        $this->transcript = $text;
    }

    public function isOpen(): bool
    {
        return ClinicalCaptureStage::isOpen($this->stage);
    }

    public function hasAudio(): bool
    {
        return is_string($this->audioRelativePath) && trim($this->audioRelativePath) !== '';
    }

    public function hasTranscript(): bool
    {
        return is_string($this->transcript) && trim($this->transcript) !== '';
    }

    public function id(): ?ClinicalCaptureId
    {
        return $this->id;
    }

    public function clientCaptureId(): string
    {
        return $this->clientCaptureId;
    }

    public function subjectPersonaId(): int
    {
        return $this->subjectPersonaId;
    }

    public function createdByUserId(): int
    {
        return $this->createdByUserId;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function parentType(): ?string
    {
        return $this->parentType;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function encounterId(): ?int
    {
        return $this->encounterId;
    }

    public function audioRelativePath(): ?string
    {
        return $this->audioRelativePath;
    }

    public function audioMime(): ?string
    {
        return $this->audioMime;
    }

    public function transcript(): ?string
    {
        return $this->transcript;
    }

    public function textoProcesado(): ?string
    {
        return $this->textoProcesado;
    }

    /**
     * @return array<string, mixed>
     */
    public function sttMeta(): array
    {
        return $this->sttMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosExtraidos(): array
    {
        return $this->datosExtraidos;
    }

    /**
     * @return array<string, mixed>
     */
    public function analysisResponse(): array
    {
        return $this->analysisResponse;
    }

    public function analysisCacheToken(): ?string
    {
        return $this->analysisCacheToken;
    }

    /**
     * @return list<string>
     */
    public function stagedItemIds(): array
    {
        return $this->stagedItemIds;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function attemptsStt(): int
    {
        return $this->attemptsStt;
    }

    public function attemptsAnalysis(): int
    {
        return $this->attemptsAnalysis;
    }

    public function attemptsSave(): int
    {
        return $this->attemptsSave;
    }

    private function assertOpen(): void
    {
        if (!$this->isOpen()) {
            throw new \InvalidArgumentException(
                "La captura no admite mutación en etapa «{$this->stage}»."
            );
        }
    }
}
