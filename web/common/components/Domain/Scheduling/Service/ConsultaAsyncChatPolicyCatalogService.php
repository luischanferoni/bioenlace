<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo {@see metadata/consulta_async_chat_policy.yaml}.
 */
final class ConsultaAsyncChatPolicyCatalogService
{
    private const CATALOG_FILE = 'consulta_async_chat_policy.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        return self::cached();
    }

    /**
     * @return list<string>
     */
    public function structuredMedicacionOperaciones(): array
    {
        $ops = self::cached()['conversation_modes']['structured']['medicacion_operaciones'] ?? [];

        return is_array($ops) ? array_values(array_map('strval', $ops)) : [];
    }

    public function composerHintStructured(): string
    {
        return trim((string) (self::cached()['conversation_modes']['structured']['composer_hint'] ?? ''));
    }

    public function staffComposerHintStructured(): string
    {
        return trim((string) (self::cached()['conversation_modes']['structured']['staff_composer_hint'] ?? ''));
    }

    /**
     * Si el staff puede usar composer de texto/adjuntos en modo structured.
     * Por defecto false (resolución por CTAs).
     */
    public function staffComposerStructured(): bool
    {
        return (self::cached()['conversation_modes']['structured']['staff_composer'] ?? false) === true;
    }

    /**
     * Si el staff puede usar composer en modo conversational (default true).
     */
    public function staffComposerConversational(): bool
    {
        return (self::cached()['conversation_modes']['conversational']['staff_composer'] ?? true) === true;
    }

    public function staffMessage(string $key): string
    {
        $map = self::cached()['staff_messages'] ?? [];
        if (!is_array($map)) {
            return '';
        }

        return trim((string) ($map[$key] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsConversational(): array
    {
        $block = self::cached()['limits']['conversational'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsRateLimit(): array
    {
        $block = self::cached()['limits']['rate_limit'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicateConfig(): array
    {
        $block = self::cached()['duplicate'] ?? [];

        return is_array($block) ? $block : [];
    }

    public function duplicateMessage(string $key): string
    {
        $map = self::cached()['duplicate']['messages'] ?? [];
        if (!is_array($map)) {
            return '';
        }

        return trim((string) ($map[$key] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelConfig(): array
    {
        $block = self::cached()['cancel'] ?? [];

        return is_array($block) ? $block : [];
    }

    public function cancelMessageExito(): string
    {
        return trim((string) (self::cached()['cancel']['message_exito'] ?? ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolution(string $code): ?array
    {
        $map = self::cached()['resolutions'] ?? [];
        if (!is_array($map)) {
            return null;
        }
        $key = trim($code);
        if ($key === '' || !isset($map[$key]) || !is_array($map[$key])) {
            return null;
        }

        return $map[$key];
    }

    /**
     * @return array<string, string>
     */
    public function resolutionOptions(): array
    {
        $map = self::cached()['resolutions'] ?? [];
        if (!is_array($map)) {
            return [];
        }
        $out = [];
        foreach ($map as $code => $def) {
            if (!is_array($def)) {
                continue;
            }
            $label = trim((string) ($def['label'] ?? $code));
            if ($label !== '') {
                $out[(string) $code] = $label;
            }
        }

        return $out;
    }

    public function solicitudMessageType(string $operacion): string
    {
        $map = self::cached()['solicitud_message_types'] ?? [];
        if (!is_array($map)) {
            return 'solicitud_consulta';
        }
        $op = trim($operacion);
        if ($op !== '' && isset($map[$op])) {
            return trim((string) $map[$op]);
        }

        return trim((string) ($map['default'] ?? 'solicitud_consulta'));
    }

    public function systemMessage(string $key): string
    {
        $map = self::cached()['system_messages'] ?? [];
        if (!is_array($map)) {
            return '';
        }

        return trim((string) ($map[$key] ?? ''));
    }

    public function solicitudTipoLabel(string $code): string
    {
        $map = self::cached()['solicitud_tipo_labels'] ?? [];
        if (!is_array($map)) {
            return $code;
        }
        $key = trim($code);

        return trim((string) ($map[$key] ?? $key));
    }

    /**
     * @return list<string>
     */
    public function allowedUploadMessageTypes(): array
    {
        return $this->filterUploadTypes(
            self::cached()['attachments']['allowed_message_types'] ?? ['audio', 'documento'],
            ['audio', 'documento']
        );
    }

    /**
     * Tipos permitidos para paciente (p. ej. solo imagen).
     *
     * @return list<string>
     */
    public function allowedUploadMessageTypesForPatient(): array
    {
        return $this->filterUploadTypes(
            self::cached()['attachments']['patient_allowed_message_types'] ?? ['imagen'],
            ['imagen']
        );
    }

    /**
     * @param mixed $types
     * @param list<string> $fallback
     * @return list<string>
     */
    private function filterUploadTypes($types, array $fallback): array
    {
        if (!is_array($types)) {
            return $fallback;
        }
        $whitelist = ['audio', 'documento', 'imagen'];
        $out = [];
        foreach ($types as $t) {
            $s = trim((string) $t);
            if ($s !== '' && in_array($s, $whitelist, true)) {
                $out[] = $s;
            }
        }

        return $out !== [] ? $out : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    public function attachmentDocumentConfig(): array
    {
        $block = self::cached()['attachments']['document'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function attachmentAudioConfig(): array
    {
        $block = self::cached()['attachments']['audio'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function attachmentImageConfig(): array
    {
        $block = self::cached()['attachments']['image'] ?? [];

        return is_array($block) ? $block : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cached(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = __DIR__ . '/../metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
