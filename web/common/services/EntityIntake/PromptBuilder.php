<?php

namespace common\services\EntityIntake;

final class PromptBuilder
{
    /**
     * Construye un prompt estricto para extraer JSON.
     *
     * @return string
     */
    public static function build(string $text, string $entity, ?string $intent, array $schema): string
    {
        $required = $schema['required'] ?? [];
        $optional = $schema['optional'] ?? [];

        $fields = array_values(array_unique(array_merge($required, $optional)));
        $fieldsList = implode(', ', $fields);

        $requiredList = implode(', ', $required);

        $intentText = $intent ? "Intent: {$intent}\n" : '';

        return "Extrae datos estructurados en JSON para un formulario clínico.\n"
            . "Entity: {$entity}\n"
            . $intentText
            . "Campos disponibles: {$fieldsList}\n"
            . "Campos requeridos: " . ($requiredList !== '' ? $requiredList : '(ninguno)') . "\n\n"
            . "Reglas:\n"
            . "- Responde SOLO con JSON válido (sin texto antes o después).\n"
            . "- Si un campo no está presente en el texto, usa null.\n"
            . "- No inventes valores.\n"
            . "- Para listas, usa arrays.\n\n"
            . "Formato EXACTO:\n"
            . "{\"prefill\":{ \"campo\": null }, \"missing_required\": [\"campo\"], \"confidence\": 0.0}\n\n"
            . "Texto: \"" . $text . "\"";
    }
}

