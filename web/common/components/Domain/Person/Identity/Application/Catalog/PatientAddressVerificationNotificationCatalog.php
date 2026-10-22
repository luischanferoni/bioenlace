<?php

namespace common\components\Domain\Person\Identity\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

final class PatientAddressVerificationNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{title: string, body: string}
     */
    public function verified(): array
    {
        $message = self::load()['verified'] ?? null;
        if (
            !is_array($message)
            || !is_string($message['title'] ?? null)
            || trim($message['title']) === ''
            || !is_string($message['body'] ?? null)
            || trim($message['body']) === ''
        ) {
            throw new \RuntimeException('Copy de domicilio verificado incompleto.');
        }

        return [
            'title' => trim($message['title']),
            'body' => trim($message['body']),
        ];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
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
            . DIRECTORY_SEPARATOR . 'Person'
            . DIRECTORY_SEPARATOR . 'Identity'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'patient-address-verification-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de notificación de domicilio: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data) || ($data['version'] ?? null) !== 1) {
            throw new \RuntimeException('Catálogo patient-address-verification-notifications inválido.');
        }

        self::$cache = $data;
        return self::$cache;
    }
}
