<?php

namespace common\components\Domain\Clinical\Capture\Domain\Model;

/**
 * Etapas del aggregate ClinicalCapture (checkpoint síncrono).
 * Fuente de verdad de strings de stage; el AR debe alinearse.
 */
final class ClinicalCaptureStage
{
    public const UPLOADED = 'UPLOADED';
    public const STT_FAILED = 'STT_FAILED';
    public const TRANSCRIBED = 'TRANSCRIBED';
    public const ANALYSIS_FAILED = 'ANALYSIS_FAILED';
    public const READY_FOR_REVIEW = 'READY_FOR_REVIEW';
    public const SAVE_FAILED = 'SAVE_FAILED';
    public const COMPLETED = 'COMPLETED';
    public const DISCARDED = 'DISCARDED';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::UPLOADED,
            self::STT_FAILED,
            self::TRANSCRIBED,
            self::ANALYSIS_FAILED,
            self::READY_FOR_REVIEW,
            self::SAVE_FAILED,
            self::COMPLETED,
            self::DISCARDED,
        ];
    }

    /**
     * Etapas de trabajo pendiente (cross-device).
     *
     * @return list<string>
     */
    public static function open(): array
    {
        return [
            self::UPLOADED,
            self::STT_FAILED,
            self::TRANSCRIBED,
            self::ANALYSIS_FAILED,
            self::READY_FOR_REVIEW,
            self::SAVE_FAILED,
        ];
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::all(), true);
    }

    public static function isOpen(string $stage): bool
    {
        return in_array($stage, self::open(), true);
    }

    public static function normalize(string $stage): string
    {
        $stage = trim($stage);
        if (!self::isValid($stage)) {
            throw new \InvalidArgumentException("Etapa de captura inválida: «{$stage}».");
        }

        return $stage;
    }
}
