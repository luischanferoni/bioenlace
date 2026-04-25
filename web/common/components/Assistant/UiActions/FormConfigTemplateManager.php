<?php

namespace common\components\Assistant\UiActions;

/**
 * @deprecated Usar {@see UiDefinitionTemplateManager}. El nombre anterior sugería solo formularios;
 *             las plantillas describen definiciones de UI más amplias (wizards, listas, etc.).
 */
class FormConfigTemplateManager
{
    /**
     * @inheritdoc
     * @see UiDefinitionTemplateManager::render()
     */
    public static function render($entity, $action, $params = [])
    {
        return \common\components\UiDefinitionTemplateManager::render($entity, $action, $params);
    }
}
