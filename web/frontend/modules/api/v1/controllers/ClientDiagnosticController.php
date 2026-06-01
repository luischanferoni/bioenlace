<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\BadRequestHttpException;

/**
 * Logs de diagnóstico desde apps móviles (flows, UI JSON, red).
 */
class ClientDiagnosticController extends BaseController
{
    private const MAX_ENTRIES_PER_REQUEST = 50;
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_DATA_JSON_LENGTH = 8000;

    /**
     * POST body: { "entries": [ { "ts", "category", "message", "data"? }, ... ] }
     */
    public function actionRegistrar()
    {
        $body = Yii::$app->request->getBodyParams();
        $entries = $body['entries'] ?? null;
        if (!is_array($entries) || $entries === []) {
            throw new BadRequestHttpException('entries debe ser un array no vacío');
        }
        if (count($entries) > self::MAX_ENTRIES_PER_REQUEST) {
            throw new BadRequestHttpException('Máximo ' . self::MAX_ENTRIES_PER_REQUEST . ' entradas por request');
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        $idUser = Yii::$app->user->isGuest ? 0 : (int) Yii::$app->user->id;
        $appClient = trim((string) ($body['app_client'] ?? Yii::$app->request->headers->get('X-App-Client', '')));
        $dir = Yii::getAlias('@runtime/logs/client-diagnostic');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de logs');
        }

        $file = $dir . '/client-diagnostic-' . date('Y-m-d') . '.log';
        $received = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $line = $this->normalizeEntry($entry, $idPersona, $idUser, $appClient);
            if ($line === null) {
                continue;
            }
            file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
            $received++;
        }

        return [
            'success' => true,
            'received' => $received,
        ];
    }

    private function normalizeEntry(array $entry, int $idPersona, int $idUser, string $appClient): ?string
    {
        $category = trim((string) ($entry['category'] ?? ''));
        $message = trim((string) ($entry['message'] ?? ''));
        if ($category === '' || $message === '') {
            return null;
        }
        if (strlen($category) > 64) {
            $category = substr($category, 0, 64);
        }
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_MESSAGE_LENGTH);
        }

        $data = $entry['data'] ?? null;
        $dataJson = '';
        if (is_array($data) && $data !== []) {
            $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($dataJson === false) {
                $dataJson = '';
            } elseif (strlen($dataJson) > self::MAX_DATA_JSON_LENGTH) {
                $dataJson = substr($dataJson, 0, self::MAX_DATA_JSON_LENGTH) . '…';
            }
        }

        $record = [
            'server_ts' => date('c'),
            'client_ts' => $entry['ts'] ?? null,
            'id_persona' => $idPersona,
            'id_user' => $idUser,
            'app_client' => $appClient !== '' ? $appClient : null,
            'category' => $category,
            'message' => $message,
            'data' => $dataJson !== '' ? $dataJson : null,
        ];

        $encoded = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }
}
