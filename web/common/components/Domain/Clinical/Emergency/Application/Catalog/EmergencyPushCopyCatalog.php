<?php

namespace common\components\Domain\Clinical\Emergency\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class EmergencyPushCopyCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string}
     */
    public function assigned(string $patientName): array
    {
        return $this->render('assigned', [
            'patient_name' => $patientName,
        ]);
    }

    /**
     * @return array{title: string, body: string}
     */
    public function criticalTriage(int $level, string $patientName, string $triageLabel): array
    {
        return $this->render('critical_triage.' . $level, [
            'patient_name' => $patientName,
            'triage_label' => $triageLabel,
        ]);
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @param array<string, string> $variables
     * @return array{title: string, body: string}
     */
    private function render(string $messageId, array $variables): array
    {
        $message = self::load()['messages'][$messageId] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body_template'] ?? null)
            || trim($message['body_template']) === ''
        ) {
            throw new \RuntimeException('Copy de push de guardia incompleto: ' . $messageId);
        }

        $placeholders = [];
        $values = [];
        foreach ($variables as $name => $value) {
            $placeholders[] = '{' . $name . '}';
            $values[] = $value;
        }

        return [
            'title' => trim($message['title']),
            'body' => trim(str_replace($placeholders, $values, $message['body_template'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'Emergency'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'emergency-push-copy.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de push de guardia: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (
            !is_array($data)
            || ($data['version'] ?? null) !== 1
            || !is_array($data['messages'] ?? null)
        ) {
            throw new \RuntimeException('Catálogo emergency-push-copy inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
