<?php

namespace common\components\Platform\Assistant\Copy;

use common\components\Platform\Core\Product\ClientContextMetadata;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;
use yii\web\Request;

/**
 * Textos UX del asistente por perfil de cliente ({@see channel-copy.yaml}).
 * Los motores piden una clave; no enumeran canales ni redactan por intent.
 */
final class AssistantChannelCopy
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @param array<string, string> $vars placeholders {nombre}
     */
    public static function t(string $messageKey, array $vars = [], ?string $appClientId = null): string
    {
        $key = trim($messageKey);
        if ($key === '') {
            return '';
        }

        $profile = self::resolveProfileId($appClientId);
        $messages = self::loadConfig()['messages'] ?? [];
        if (!is_array($messages)) {
            $messages = [];
        }
        $variants = $messages[$key] ?? null;
        if (!is_array($variants)) {
            return self::applyVars($key, $vars);
        }

        $text = '';
        if ($profile !== 'default' && isset($variants[$profile]) && is_string($variants[$profile])) {
            $text = trim($variants[$profile]);
        }
        if ($text === '' && isset($variants['default']) && is_string($variants['default'])) {
            $text = trim($variants['default']);
        }
        if ($text === '') {
            return '';
        }

        return self::applyVars($text, $vars);
    }

    public static function resolveProfileId(?string $appClientId = null): string
    {
        $id = trim((string) ($appClientId ?? self::requestAppClientId() ?? ''));
        if ($id === '') {
            return 'default';
        }

        $fromContext = ClientContextMetadata::profileSectionKeyForAppClient($id);
        if ($fromContext !== null && $fromContext !== '') {
            return $fromContext;
        }

        return 'default';
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = ['messages' => []];
        $path = ProductMetadataPaths::assistantChannelCopyFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AssistantChannelCopy: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (is_array($data) && isset($data['messages']) && is_array($data['messages'])) {
            self::$config['messages'] = $data['messages'];
        }

        return self::$config;
    }

    private static function requestAppClientId(): ?string
    {
        try {
            if (!Yii::$app->has('request')) {
                return null;
            }
            $request = Yii::$app->request;
            if (!$request instanceof Request) {
                return null;
            }
            $id = trim((string) $request->headers->get('X-App-Client', ''));

            return $id !== '' ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $vars
     */
    private static function applyVars(string $text, array $vars): string
    {
        if ($vars === []) {
            return $text;
        }
        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $replace);
    }
}
