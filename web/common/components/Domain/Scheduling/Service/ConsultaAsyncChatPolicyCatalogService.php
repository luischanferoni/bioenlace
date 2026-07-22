<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo {@see metadata/consulta_async_chat_policy.yaml}.
 */
final class ConsultaAsyncChatPolicyCatalogService
{
    private const CATALOG_FILE = 'consulta_async_chat_policy.yaml';

    public const CATEGORIA_RENOVACION_MEDICACION = 'renovacion_medicacion';

    public const CATEGORIA_AJUSTE_MEDICACION = 'ajuste_medicacion';

    public const CATEGORIA_CONSULTA_EVOLUCION = 'consulta_evolucion';

    /** @var list<string> */
    public const SOLICITUD_CATEGORIA_CODES = [
        self::CATEGORIA_RENOVACION_MEDICACION,
        self::CATEGORIA_AJUSTE_MEDICACION,
        self::CATEGORIA_CONSULTA_EVOLUCION,
    ];

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

    /**
     * Códigos canónicos de categoría de solicitud.
     *
     * @return list<string>
     */
    public function allowedSolicitudCategorias(): array
    {
        $map = self::cached()['solicitud_categorias'] ?? [];
        if (!is_array($map) || $map === []) {
            return self::SOLICITUD_CATEGORIA_CODES;
        }
        $out = [];
        foreach (array_keys($map) as $code) {
            $s = trim((string) $code);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out !== [] ? $out : self::SOLICITUD_CATEGORIA_CODES;
    }

    /**
     * Resuelve código canónico desde alias de flujo/meta o categoría directa.
     */
    public function resolveSolicitudCategoria(string $codeOrAlias): string
    {
        $aliases = self::cached()['solicitud_categoria_aliases'] ?? [];
        if (!is_array($aliases)) {
            $aliases = [];
        }
        $key = trim($codeOrAlias);
        if ($key !== '' && isset($aliases[$key])) {
            $resolved = trim((string) $aliases[$key]);
            if ($resolved !== '') {
                return $resolved;
            }
        }
        if ($key !== '' && in_array($key, $this->allowedSolicitudCategorias(), true)) {
            return $key;
        }
        $default = trim((string) ($aliases['default'] ?? self::CATEGORIA_CONSULTA_EVOLUCION));

        return $default !== '' ? $default : self::CATEGORIA_CONSULTA_EVOLUCION;
    }

    /**
     * Categoría canónica desde meta del encounter async.
     *
     * @param array<string, mixed> $meta
     */
    public function solicitudCategoriaFromMeta(array $meta): string
    {
        $op = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($op !== '') {
            return $this->resolveSolicitudCategoria($op);
        }
        $necesidad = trim((string) ($meta['seguimiento_necesidad'] ?? ''));
        if ($necesidad !== '') {
            return $this->resolveSolicitudCategoria($necesidad);
        }
        $intake = trim((string) ($meta['intake_tipo'] ?? ''));

        return $this->resolveSolicitudCategoria($intake !== '' ? $intake : 'default');
    }

    public function solicitudCategoriaLabel(string $categoriaOrAlias): string
    {
        $categoria = $this->resolveSolicitudCategoria($categoriaOrAlias);
        $map = self::cached()['solicitud_categorias'] ?? [];
        if (is_array($map) && isset($map[$categoria]) && is_array($map[$categoria])) {
            $label = trim((string) ($map[$categoria]['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
        $fallbacks = [
            self::CATEGORIA_RENOVACION_MEDICACION => 'Solicitud de renovación de medicación',
            self::CATEGORIA_AJUSTE_MEDICACION => 'Solicitud de ajuste de medicación',
            self::CATEGORIA_CONSULTA_EVOLUCION => 'Consulta o evolución',
        ];

        return $fallbacks[$categoria] ?? $categoria;
    }

    /**
     * Labels canónicos (para strip de prefijos históricos en reason_text).
     *
     * @return list<string>
     */
    public function solicitudCategoriaLabels(): array
    {
        $out = [];
        foreach ($this->allowedSolicitudCategorias() as $code) {
            $label = $this->solicitudCategoriaLabel($code);
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return $out;
    }

    /**
     * Map message_type legacy → categoría (migración / filas antiguas).
     */
    public function solicitudCategoriaFromLegacyMessageType(string $messageType): ?string
    {
        $type = trim($messageType);
        if ($type === '') {
            return null;
        }
        $map = self::cached()['solicitud_categorias'] ?? [];
        if (!is_array($map)) {
            return null;
        }
        foreach ($map as $code => $def) {
            if (!is_array($def)) {
                continue;
            }
            if (trim((string) ($def['message_type_legacy'] ?? '')) === $type) {
                return trim((string) $code);
            }
        }

        return null;
    }

    /**
     * @deprecated Usar resolveSolicitudCategoria / solicitudCategoriaFromMeta.
     * Conservado para compat con tests y callers legacy.
     */
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

    /**
     * Label de categoría para bandeja (`solicitud_tipo`). Acepta alias de flujo o código canónico.
     */
    public function solicitudTipoLabel(string $code): string
    {
        return $this->solicitudCategoriaLabel($code);
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
