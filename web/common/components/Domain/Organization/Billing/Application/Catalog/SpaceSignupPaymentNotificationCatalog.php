<?php

namespace common\components\Domain\Organization\Billing\Application\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Copy del aviso al titular cuando se confirma el primer pago de un espacio de atención.
 *
 * El texto vive en el YAML; las condiciones de disparo, en la política que llama.
 */
final class SpaceSignupPaymentNotificationCatalog
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{subject: string, body: string}
     */
    public function paymentConfirmed(string $efectorNombre): array
    {
        $message = self::load()['notifications']['payment_confirmed'] ?? null;
        if (
            !is_array($message)
            || !is_string($message['subject'] ?? null)
            || trim($message['subject']) === ''
            || !is_string($message['body'] ?? null)
            || trim($message['body']) === ''
        ) {
            throw new \RuntimeException("Copy de notificación 'payment_confirmed' incompleto.");
        }

        $replacements = ['{efector}' => trim($efectorNombre) !== '' ? trim($efectorNombre) : 'tu espacio'];

        return [
            'subject' => strtr(trim($message['subject']), $replacements),
            'body' => strtr(trim($message['body']), $replacements),
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
            . DIRECTORY_SEPARATOR . 'Organization'
            . DIRECTORY_SEPARATOR . 'Billing'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Catalog'
            . DIRECTORY_SEPARATOR . 'space-signup-payment-notifications.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException('No se encontró el catálogo de avisos de pago: ' . $path);
        }

        $data = Yaml::parseFile($path);
        if (!is_array($data) || ($data['version'] ?? null) !== 1) {
            throw new \RuntimeException('Catálogo space-signup-payment-notifications inválido.');
        }

        self::$cache = $data;

        return self::$cache;
    }
}
