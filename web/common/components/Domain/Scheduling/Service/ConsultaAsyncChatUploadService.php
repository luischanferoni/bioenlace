<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Clinical\Encounter;
use yii\web\UploadedFile;

/**
 * Validación de adjuntos async según metadata ({@see consulta_async_chat_policy.yaml}).
 */
final class ConsultaAsyncChatUploadService
{
    /**
     * @return list<string>
     */
    public function allowedTypesForViewer(Encounter $encounter, bool $viewerEsPaciente): array
    {
        $policy = (new ConsultaAsyncChatPolicyService())->resolveForEncounter($encounter, $viewerEsPaciente);
        $types = $policy['composer']['upload_types'] ?? [];

        return is_array($types) ? array_values(array_map('strval', $types)) : [];
    }

    public function assertUploadAllowed(
        Encounter $encounter,
        string $messageType,
        UploadedFile $file,
        bool $viewerEsPaciente
    ): void {
        if ($encounter->parent_type !== Encounter::PARENT_SOLICITUD_ASYNC) {
            return;
        }

        $policy = (new ConsultaAsyncChatPolicyService())->resolveForEncounter($encounter, $viewerEsPaciente);
        if (($policy['composer']['upload_enabled'] ?? false) !== true) {
            throw new \InvalidArgumentException('No podés enviar adjuntos en esta consulta.');
        }

        $allowed = $policy['composer']['upload_types'] ?? [];
        if (!is_array($allowed) || !in_array($messageType, $allowed, true)) {
            throw new \InvalidArgumentException('Tipo de adjunto no permitido.');
        }

        $catalog = new ConsultaAsyncChatPolicyCatalogService();
        if ($messageType === 'documento') {
            $this->assertDocument($file, $catalog->attachmentDocumentConfig());
        } elseif ($messageType === 'audio') {
            $this->assertAudio($file, $catalog->attachmentAudioConfig());
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function assertDocument(UploadedFile $file, array $cfg): void
    {
        $maxBytes = max(1, (int) ($cfg['max_bytes'] ?? 10485760));
        if ($file->size > $maxBytes) {
            throw new \InvalidArgumentException('El documento supera el tamaño máximo permitido.');
        }
        $ext = strtolower(trim($file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION)));
        $allowedExt = array_map('strtolower', $cfg['extensions'] ?? ['pdf']);
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            throw new \InvalidArgumentException('Solo se permiten documentos PDF.');
        }
        $mime = strtolower(trim((string) $file->type));
        $allowedMime = array_map('strtolower', $cfg['mime_types'] ?? ['application/pdf']);
        if ($mime !== '' && $allowedMime !== [] && !in_array($mime, $allowedMime, true)) {
            throw new \InvalidArgumentException('Solo se permiten documentos PDF.');
        }
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function assertAudio(UploadedFile $file, array $cfg): void
    {
        $maxBytes = max(1, (int) ($cfg['max_bytes'] ?? 5242880));
        if ($file->size > $maxBytes) {
            throw new \InvalidArgumentException('El audio supera el tamaño máximo permitido.');
        }
        $ext = strtolower(trim($file->getExtension() ?: pathinfo($file->name, PATHINFO_EXTENSION)));
        $allowedExt = array_map('strtolower', $cfg['extensions'] ?? ['m4a', 'mp3', 'webm', 'ogg', 'wav']);
        if ($ext !== '' && !in_array($ext, $allowedExt, true)) {
            throw new \InvalidArgumentException('Formato de audio no permitido.');
        }
    }
}
