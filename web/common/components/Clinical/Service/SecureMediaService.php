<?php

namespace common\components\Clinical\Service;

use common\components\Clinical\Service\Authorization\EncounterAccessService;
use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;
use common\models\ConsultaMotivosMessage;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Archivos en `frontend/web/uploads/*` servidos solo vía API autenticada.
 */
final class SecureMediaService
{
    public const SCOPE_MOTIVOS_CONSULTA = 'motivos-consulta';
    public const SCOPE_CONSULTA_CHAT = 'consulta-chat';

    /** @var array<string, array{dir: string, messageClass: class-string, mediaTypes: list<string>}> */
    private const SCOPES = [
        self::SCOPE_MOTIVOS_CONSULTA => [
            'dir' => 'motivos_consulta',
            'messageClass' => ConsultaMotivosMessage::class,
            'mediaTypes' => ['imagen', 'audio'],
        ],
        self::SCOPE_CONSULTA_CHAT => [
            'dir' => 'consulta_chat',
            'messageClass' => ConsultaChatMessage::class,
            'mediaTypes' => ['imagen', 'audio', 'video', 'documento'],
        ],
    ];

    /**
     * URL absoluta API para un adjunto almacenado (ruta relativa, URL pública legacy o path absoluto en disco).
     */
    public static function absoluteApiUrl(string $scope, int $encounterId, string $stored): string
    {
        $filename = self::filenameFromStored($stored);
        if ($filename === '') {
            return $stored;
        }

        return rtrim(self::apiBaseUrl(), '/') . self::apiPath($scope, $encounterId, $filename);
    }

    /**
     * Ruta relativa API (`/api/v1/media/...`) sin host.
     */
    public static function apiPath(string $scope, int $encounterId, string $filename): string
    {
        return '/api/v1/media/'
            . rawurlencode($scope) . '/'
            . $encounterId . '/'
            . rawurlencode($filename);
    }

    /**
     * @return array{path: string, mime: string, filename: string}
     */
    public static function resolveForDownload(string $scope, int $encounterId, string $filename): array
    {
        self::assertValidScope($scope);
        self::assertValidFilename($filename);

        $encounter = Encounter::findOne($encounterId);
        if ($encounter === null) {
            throw new NotFoundHttpException('Encounter no encontrado');
        }
        if (!EncounterAccessService::canAccess($encounter)) {
            throw new ForbiddenHttpException('No tiene permiso para ver este archivo');
        }

        $relative = self::relativeStoragePath($scope, $encounterId, $filename);
        if (!self::messageReferencesPath($scope, $encounterId, $relative)) {
            throw new NotFoundHttpException('Archivo no registrado para este encounter');
        }

        $fullPath = Yii::getAlias('@frontend/web') . '/' . $relative;
        if (!is_file($fullPath)) {
            throw new NotFoundHttpException('Archivo no encontrado');
        }

        return [
            'path' => $fullPath,
            'mime' => self::guessMime($filename),
            'filename' => $filename,
        ];
    }

    /**
     * Normaliza texto guardado en BD a `uploads/{dir}/{encounterId}/{filename}`.
     */
    public static function normalizeStoragePath(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        if (preg_match('#uploads/(motivos_consulta|consulta_chat)/(\d+)/([^/\s]+)#', $stored, $m)) {
            return 'uploads/' . $m[1] . '/' . $m[2] . '/' . $m[3];
        }

        return null;
    }

    public static function filenameFromStored(string $stored): string
    {
        $normalized = self::normalizeStoragePath($stored);
        if ($normalized !== null) {
            return basename($normalized);
        }

        $stored = trim($stored);
        if (preg_match('#/([^/]+)$#', $stored, $m)) {
            return $m[1];
        }

        // Solo nombre de archivo en BD (sin uploads/…/encounter_id/)
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $stored)) {
            return $stored;
        }

        return '';
    }

    public static function encounterIdFromStored(string $stored): ?int
    {
        $normalized = self::normalizeStoragePath($stored);
        if ($normalized === null) {
            return null;
        }
        if (preg_match('#uploads/(?:motivos_consulta|consulta_chat)/(\d+)/#', $normalized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function scopeFromStoragePath(string $stored): ?string
    {
        $normalized = self::normalizeStoragePath($stored);
        if ($normalized === null) {
            return null;
        }
        if (strpos($normalized, 'uploads/motivos_consulta/') === 0) {
            return self::SCOPE_MOTIVOS_CONSULTA;
        }
        if (strpos($normalized, 'uploads/consulta_chat/') === 0) {
            return self::SCOPE_CONSULTA_CHAT;
        }

        return null;
    }

    /**
     * Si el contenido ya es URL API de media, la devuelve absoluta; si no, la construye.
     */
    public static function contentForApi(string $scope, int $encounterId, string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return $stored;
        }

        if (preg_match('#(/api/v\d+/media/[^?\s]+)#', $stored, $apiMatch)) {
            if (strpos($stored, 'http') === 0) {
                return $stored;
            }

            return rtrim(self::apiBaseUrl(), '/') . $apiMatch[1];
        }

        if (strpos($stored, 'http') === 0) {
            $scopeFromPath = self::scopeFromStoragePath($stored);
            $encFromPath = self::encounterIdFromStored($stored);
            $filename = self::filenameFromStored($stored);
            if ($scopeFromPath !== null && $encFromPath !== null && $encFromPath === $encounterId && $filename !== '') {
                return self::absoluteApiUrl($scopeFromPath, $encounterId, $stored);
            }
        }

        $filename = self::filenameFromStored($stored);
        if ($filename === '') {
            return $stored;
        }

        return self::absoluteApiUrl($scope, $encounterId, $filename);
    }

    private static function apiBaseUrl(): string
    {
        $host = Yii::$app->request->hostInfo;

        return $host;
    }

    private static function assertValidScope(string $scope): void
    {
        if (!isset(self::SCOPES[$scope])) {
            throw new NotFoundHttpException('Ámbito de media no válido');
        }
    }

    private static function assertValidFilename(string $filename): void
    {
        if ($filename === '' || $filename !== basename($filename)) {
            throw new NotFoundHttpException('Nombre de archivo no válido');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new NotFoundHttpException('Nombre de archivo no válido');
        }
    }

    private static function relativeStoragePath(string $scope, int $encounterId, string $filename): string
    {
        $dir = self::SCOPES[$scope]['dir'];

        return 'uploads/' . $dir . '/' . $encounterId . '/' . $filename;
    }

    private static function messageReferencesPath(string $scope, int $encounterId, string $relative): bool
    {
        $cfg = self::SCOPES[$scope];
        /** @var class-string<\yii\db\ActiveRecord> $class */
        $class = $cfg['messageClass'];

        /** @var list<\yii\db\ActiveRecord> $rows */
        $rows = $class::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['message_type' => $cfg['mediaTypes']])
            ->all();

        foreach ($rows as $row) {
            $texto = (string) ($row->getAttribute('texto') ?? '');
            if (self::normalizeStoragePath($texto) === $relative) {
                return true;
            }
        }

        return false;
    }

    private static function guessMime(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
