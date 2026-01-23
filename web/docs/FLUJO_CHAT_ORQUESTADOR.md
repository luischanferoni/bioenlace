# Flujo del Chat y Orquestador de Intents

## Flujo desde la Llegada de un Chat

Cuando un usuario envía un mensaje al chatbot, el sistema procesa la consulta siguiendo este flujo:

### 1. Entrada del Mensaje
El mensaje del usuario llega al sistema y se invoca `ConsultaIntentRouter::process()` con:
- `$message`: El texto del usuario
- `$userId`: ID del usuario (opcional)
- `$botId`: ID del bot (default: 'BOT')

### 2. Carga del Contexto de Conversación
```php
$context = ConversationContext::load($userId, $botId);
```
- Se recupera el contexto previo de la conversación desde la base de datos (tabla `Dialogo`)
- El contexto almacena solo parámetros relevantes del intent actual, no todo el historial
- Si no hay contexto previo, se crea uno vacío
- Se limpia el contexto si ha expirado (según `lifetime` del intent)

### 3. Clasificación del Intent
```php
$classification = IntentClassifier::classify($message, $context);
```
El sistema intenta identificar la intención del usuario mediante dos métodos:

**a) Clasificación por Reglas (más rápido):**
- Busca keywords y patrones regex en el mensaje
- Calcula un score de coincidencia para cada intent
- Si el score es >= 0.7, retorna el resultado inmediatamente

**b) Clasificación por IA (fallback):**
- Si no hay match claro por reglas, usa IA para clasificar
- La IA recibe un prompt con todas las categorías e intents disponibles
- Retorna: `category`, `intent`, `confidence`, `method`

### 4. Extracción de Parámetros
```php
$parameters = ParameterExtractor::extract($message, $intent, $context);
```
- Extrae parámetros específicos del mensaje según el intent detectado
- Usa reglas específicas para cada tipo de parámetro (fecha, hora, servicio, etc.)
- Puede resolver referencias del paciente si está configurado
- Retorna un array con los parámetros encontrados

### 5. Fusión del Contexto
```php
$context = ConversationContext::merge($context, $intent, $parameters);
```
- Si es el mismo intent, fusiona los nuevos parámetros con los existentes
- Si es un nuevo intent, limpia el contexto anterior y crea uno nuevo
- Actualiza metadatos (timestamp, contador de mensajes, etc.)

### 6. Obtención del Handler
```php
$handler = self::getHandler($category, $intent);
```
- Busca el handler configurado para la categoría e intent en `intent-categories.php`
- Instancia la clase del handler (ej: `TurnosHandler`, `ConsultaMedicaHandler`)
- Si no existe el handler, retorna respuesta de fallback

### 7. Procesamiento por el Handler
```php
$response = $handler->handle($intent, $message, $parameters, $context, $userId);
```
El handler específico:
- Valida parámetros requeridos
- Ejecuta la lógica de negocio correspondiente
- Puede usar `UniversalQueryAgent` para buscar acciones del sistema
- Genera una respuesta estructurada con:
  - `success`: boolean
  - `needs_more_info`: boolean (si faltan parámetros)
  - `response`: array con texto y datos
  - `suggestions`: array de sugerencias
  - `actions`: array de acciones disponibles

### 8. Actualización y Guardado del Contexto
```php
ConversationContext::save($userId, $context, $botId);
```
- Si el handler actualizó el contexto, se guarda en la base de datos
- El contexto se almacena en formato JSON en `Dialogo.estado_json`
- Se limpia automáticamente si expiró

### 9. Respuesta Final
Se retorna una respuesta estructurada con:
- La respuesta del handler
- Metadatos adicionales (categoría, intent, confidence, método de detección, parámetros extraídos)

---

## Crear una Nueva Entity con el Orquestador

Para agregar una nueva funcionalidad al chatbot, sigue estos pasos:

### Paso 1: Crear el Handler

Crea un nuevo archivo en `web/common/components/intent_handlers/` siguiendo el patrón:

**Ejemplo: `MiNuevaEntityHandler.php`**

```php
<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\ConversationContext;
use common\components\UniversalQueryAgent;

/**
 * Handler para intents relacionados con Mi Nueva Entity
 */
class MiNuevaEntityHandler extends BaseIntentHandler
{
    /**
     * Procesar intent de mi nueva entity
     */
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent, 'parameters' => $parameters]);
        
        switch ($intent) {
            case 'crear_mi_entity':
                return $this->handleCrear($message, $parameters, $context, $userId);
            
            case 'consultar_mi_entity':
                return $this->handleConsultar($message, $parameters, $context, $userId);
            
            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por MiNuevaEntityHandler");
        }
    }
    
    private function handleCrear($message, $parameters, $context, $userId)
    {
        // Verificar parámetros requeridos
        $missing = $this->getMissingRequiredParams('crear_mi_entity', $parameters);
        
        if (!empty($missing)) {
            $context = $this->updateContext($userId, $context, 'crear_mi_entity', $parameters);
            $context = ConversationContext::setAwaitingInput($context, $missing[0]);
            
            return [
                'success' => true,
                'needs_more_info' => true,
                'missing_params' => $missing,
                'response' => [
                    'text' => $this->getQuestionsForParams($missing)[0] ?? 'Necesito más información.',
                    'awaiting' => $missing[0]
                ],
                'suggestions' => $this->getSuggestionsForParams($missing),
                'context_update' => $context
            ];
        }
        
        // Lógica de creación
        // Puedes usar UniversalQueryAgent para buscar acciones del sistema
        $query = "crear mi entity";
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $context = ConversationContext::markCompleted($context);
        $context = $this->updateContext($userId, $context, 'crear_mi_entity', $parameters);
        
        return $this->generateSuccessResponse(
            "Entity creada exitosamente",
            $parameters,
            $actionResult['data']['actions'] ?? []
        );
    }
    
    private function handleConsultar($message, $parameters, $context, $userId)
    {
        // Lógica de consulta
        $query = "consultar mi entity";
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $context = ConversationContext::markCompleted($context);
        
        return $this->generateSuccessResponse(
            "Aquí está la información:",
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    /**
     * Obtener sugerencias para parámetros (opcional)
     */
    protected function getSuggestionsForParams($params)
    {
        $suggestions = [];
        
        if (in_array('campo_especifico', $params)) {
            $suggestions[] = 'Opción 1';
            $suggestions[] = 'Opción 2';
        }
        
        return $suggestions;
    }
}
```

### Paso 2: Registrar la Categoría e Intents

Edita `web/common/config/chatbot/intent-categories.php` y agrega tu nueva categoría:

```php
return [
    // ... otras categorías ...
    
    'mi_nueva_entity' => [
        'name' => 'Mi Nueva Entity',
        'description' => 'Descripción de la funcionalidad',
        'intents' => [
            'crear_mi_entity' => [
                'name' => 'Crear Mi Entity',
                'keywords' => [
                    'crear entity', 'nuevo entity', 'agregar entity',
                    'necesito entity', 'quiero entity'
                ],
                'patterns' => [
                    '/\b(crear|nuevo|agregar)\s+entity/i',
                    '/necesito\s+entity/i'
                ],
                'handler' => 'MiNuevaEntityHandler', // Nombre de la clase sin namespace
                'priority' => 'high' // 'critical', 'high', 'medium', 'low'
            ],
            'consultar_mi_entity' => [
                'name' => 'Consultar Mi Entity',
                'keywords' => [
                    'ver entity', 'mis entities', 'consultar entity',
                    'listar entity'
                ],
                'patterns' => [
                    '/\b(ver|consultar|listar)\s+entity/i',
                    '/mis\s+entities/i'
                ],
                'handler' => 'MiNuevaEntityHandler',
                'priority' => 'medium'
            ]
        ]
    ]
];
```

**Notas importantes:**
- `handler`: Debe ser el nombre de la clase sin el namespace completo
- `keywords`: Palabras clave que activan este intent
- `patterns`: Expresiones regulares para detección más precisa
- `priority`: Nivel de prioridad para el scoring

### Paso 3: Definir Parámetros de los Intents

Edita `web/common/config/chatbot/intent-parameters.php` y agrega la configuración de parámetros:

```php
return [
    // ... otros intents ...
    
    'crear_mi_entity' => [
        'required_params' => ['campo_obligatorio_1', 'campo_obligatorio_2'],
        'optional_params' => ['campo_opcional_1', 'campo_opcional_2'],
        'lifetime' => 600, // Tiempo de vida del contexto en segundos (10 minutos)
        'cleanup_on' => ['intent_change', 'completed', 'timeout'],
        'patient_profile' => [
            'can_use' => ['professional', 'efector', 'service'], // Datos del perfil que puede usar
            'resolve_references' => true, // Resolver referencias del paciente
            'update_on_complete' => [
                'type' => 'professional',
                'fields' => ['id_rr_hh', 'id_efector']
            ],
            'cache_ttl' => 3600
        ]
    ],
    
    'consultar_mi_entity' => [
        'required_params' => [],
        'optional_params' => ['filtro_1', 'filtro_2'],
        'lifetime' => 300, // 5 minutos
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ]
];
```

**Parámetros comunes disponibles en ParameterExtractor:**
- `servicio`, `fecha`, `hora`, `horario`
- `profesional`, `id_rr_hh`
- `efector`, `id_efector`
- `medicamento`, `sintoma`
- `turno_id`, `tipo_practica`
- `ubicacion`

Si necesitas un parámetro personalizado, deberás agregarlo en `ParameterExtractor::extractParameter()`.

### Paso 4: (Opcional) Agregar Referencias del Paciente

Si tu entity usa referencias del paciente (como "mi médico", "mi efector"), edita `web/common/config/chatbot/patient-references.php`:

```php
return [
    // ... otras referencias ...
    
    'mi entity' => [
        'type' => 'entity',
        'resolve_to' => 'id_entity',
        'context' => ['mi_nueva_entity']
    ]
];
```

### Paso 5: Probar la Integración

1. **Prueba de detección por keywords:**
   - Envía un mensaje con las keywords definidas
   - Verifica que se detecte correctamente el intent

2. **Prueba de extracción de parámetros:**
   - Envía un mensaje con parámetros
   - Verifica que se extraigan correctamente

3. **Prueba de flujo completo:**
   - Envía un mensaje incompleto (falta parámetro requerido)
   - Verifica que el sistema pregunte por el parámetro faltante
   - Completa la información
   - Verifica que se procese correctamente

4. **Prueba de contexto:**
   - Envía múltiples mensajes relacionados
   - Verifica que el contexto se mantenga entre mensajes

### Resumen de Archivos a Modificar

1. ✅ **Crear Handler**: `web/common/components/intent_handlers/MiNuevaEntityHandler.php`
2. ✅ **Registrar Categoría**: `web/common/config/chatbot/intent-categories.php`
3. ✅ **Definir Parámetros**: `web/common/config/chatbot/intent-parameters.php`
4. ⚠️ **Opcional - Referencias**: `web/common/config/chatbot/patient-references.php`
5. ⚠️ **Opcional - Parámetros personalizados**: `web/common/components/ParameterExtractor.php`

### Buenas Prácticas

- **Nombres consistentes**: Usa nombres descriptivos y consistentes para intents y handlers
- **Logging**: Usa `$this->log()` para registrar actividad importante
- **Manejo de errores**: Siempre retorna respuestas estructuradas, incluso en errores
- **Contexto**: Actualiza el contexto cuando sea necesario para mantener la conversación
- **Validación**: Valida siempre los parámetros requeridos antes de procesar
- **Reutilización**: Usa `UniversalQueryAgent` para buscar acciones del sistema en lugar de hardcodear rutas

### Ejemplo Completo: TurnosHandler

Puedes ver un ejemplo completo y funcional en:
- `web/common/components/intent_handlers/TurnosHandler.php`
- Configuración en `intent-categories.php` bajo la categoría `'turnos'`
- Parámetros en `intent-parameters.php` para intents como `'crear_turno'`, `'modificar_turno'`, etc.
