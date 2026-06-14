<?php

namespace common\components\Platform\Assistant\Chat\Conversational;

use common\components\Platform\Core\Product\ProductRegistryConfig;

final class ConversationalChannelProviderRegistry
{
    /**
     * @return list<class-string<ConversationalChannelProviderInterface>>
     */
    public static function allProviderClasses(): array
    {
        $classes = [];
        foreach (ProductRegistryConfig::section('conversationalChannelProviders') as $class) {
            if (is_string($class) && $class !== ''
                && is_subclass_of($class, ConversationalChannelProviderInterface::class)) {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param list<string> $parts
     */
    public static function appendPatientContext(int $idPersona, array &$parts): void
    {
        if ($idPersona <= 0) {
            return;
        }
        foreach (self::allProviderClasses() as $class) {
            $class::appendPatientContext($idPersona, $parts);
        }
    }
}
