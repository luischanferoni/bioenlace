<?php

namespace common\components\Platform\Assistant\IntentEngine;

/**
 * DTO de una UI sugerible para el asistente.
 */
final class UiActionCatalogItem
{
    public string $action_id;
    public string $display_name;
    public string $description;
    public ?string $entity;
    public string $route;

    /** @var string[] */
    public array $keywords;

    /** @var array<string, mixed> */
    public array $parameters;

    /** @var array<string, mixed>|null Semántica declarativa del intent (goal/how/constraints/etc.). */
    public ?array $intent_semantics = null;

    /** @var array<string, mixed>|null Instrucción explícita para abrir UI (web/mobile). */
    public ?array $client_open = null;

    /** @var string|null Etiqueta de interacción (ej. ui_asistente_json|ui_asistente_native). */
    public ?string $client_interaction = null;

    /**
     * Presentación sugerida para shell SPA / cliente.
     * Nota histórica: antes se emitía como `client_open.presentation` (inline|fullscreen).
     * El contrato actual abre inline por defecto y ya no emite `presentation`.
     */
    public ?string $spa_presentation = null;

    /**
     * @param string[] $keywords
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $action_id,
        string $display_name,
        string $description,
        ?string $entity,
        string $route,
        array $keywords,
        array $parameters,
        ?array $intent_semantics = null,
        ?array $client_open = null,
        ?string $client_interaction = null,
        ?string $spa_presentation = null
    ) {
        $this->action_id = $action_id;
        $this->display_name = $display_name;
        $this->description = $description;
        $this->entity = $entity;
        $this->route = $route;
        $this->keywords = $keywords;
        $this->parameters = $parameters;
        $this->intent_semantics = $intent_semantics;
        $this->client_open = $client_open;
        $this->client_interaction = $client_interaction;
        $this->spa_presentation = $spa_presentation;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPromptArray(): array
    {
        // Compat: se mantiene para otros callers; para clasificación IA usar toAiCandidateArray().
        return [
            'action_id' => $this->action_id,
            'title' => $this->display_name,
            'description' => $this->description,
            'keywords' => $this->keywords,
        ];
    }

    /**
     * Candidato compacto para IA: señal semántica, no metadata de UI.
     *
     * @return array<string, mixed>
     */
    public function toAiCandidateArray(): array
    {
        $sem = $this->intent_semantics;
        if (!is_array($sem)) {
            $sem = null;
        }
        return [
            'id' => $this->action_id,
            'k' => $this->keywords,
            's' => $sem,
        ];
    }
}

