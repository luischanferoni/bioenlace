# Centralización de Configuración del Chatbot

## Propuesta de Arquitectura

### Problema Actual
La configuración del chatbot está duplicada en múltiples archivos:
- `intent-categories.php`: Categorías e intents
- `intent-parameters.php`: Parámetros por intent
- `patient-references.php`: Referencias del paciente
- Modelos: Estructura de datos real

Esto genera:
- ❌ Duplicación de información
- ❌ Inconsistencias entre archivos
- ❌ Mantenimiento complejo
- ❌ Riesgo de errores

### Solución Propuesta

**Source of Truth: Los Modelos ActiveRecord**

Los modelos ya definen:
- ✅ Estructura de la tabla (`tableName()`)
- ✅ Atributos disponibles (`getTableSchema()`)
- ✅ Relaciones (`hasOne()`, `hasMany()`)
- ✅ Validaciones (`rules()`)
- ✅ Labels (`attributeLabels()`)

**Agregar Metadata del Chatbot en los Modelos**

Usar anotaciones en docblocks de los modelos para definir:
- Categoría del chatbot
- Intents asociados
- Parámetros requeridos/opcionales
- Keywords y patrones
- Handler asociado

**Generación Automática**

Un script que:
1. Lee todos los modelos
2. Extrae metadata de anotaciones
3. Genera los archivos de configuración PHP
4. Mantiene compatibilidad con configuración manual (override)

## Estructura de Anotaciones en Modelos

### Ejemplo: Modelo Turno

```php
<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "turnos".
 *
 * @property string $id_turnos
 * @property integer $id_persona
 * @property string $fech
 * @property string $hora
 * @property string $id_rr_hh
 * @property string $id_servicio
 * 
 * @chatbot-category turnos
 * @chatbot-category-name "Gestión de Turnos"
 * @chatbot-category-description "Acciones concretas relacionadas con turnos médicos"
 * 
 * @chatbot-intent crear_turno
 * @chatbot-intent-name "Crear Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "sacar turno,reservar turno,agendar turno,pedir turno"
 * @chatbot-intent-patterns "/\b(sacar|reservar|agendar|pedir|necesito|quiero)\s+(un\s+)?turno/i"
 * @chatbot-intent-required-params servicio,fecha,hora
 * @chatbot-intent-optional-params profesional,efector,observaciones
 * @chatbot-intent-lifetime 600
 * @chatbot-intent-patient-profile-can-use professional,efector,service
 * @chatbot-intent-patient-profile-resolve-references true
 * 
 * @chatbot-intent modificar_turno
 * @chatbot-intent-name "Modificar Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "cambiar turno,modificar turno,reagendar turno"
 * @chatbot-intent-required-params turno_id
 * @chatbot-intent-optional-params fecha,hora,profesional
 * @chatbot-intent-lifetime 600
 * 
 * @chatbot-intent cancelar_turno
 * @chatbot-intent-name "Cancelar Turno"
 * @chatbot-intent-handler TurnosHandler
 * @chatbot-intent-priority high
 * @chatbot-intent-keywords "cancelar turno,anular turno,borrar turno"
 * @chatbot-intent-required-params turno_id
 * @chatbot-intent-lifetime 300
 */
class Turno extends ActiveRecord
{
    // ... código del modelo ...
}
```

### Sintaxis de Anotaciones

#### Categoría (una por modelo)
```
@chatbot-category {category_key}
@chatbot-category-name "{Nombre Legible}"
@chatbot-category-description "{Descripción}"
```

#### Intents (múltiples por modelo)
```
@chatbot-intent {intent_key}
@chatbot-intent-name "{Nombre Legible}"
@chatbot-intent-handler {HandlerClass}
@chatbot-intent-priority {critical|high|medium|low}
@chatbot-intent-keywords "{keyword1,keyword2,keyword3}"
@chatbot-intent-patterns "{/pattern1/i,/pattern2/i}"
@chatbot-intent-required-params {param1,param2}
@chatbot-intent-optional-params {param1,param2}
@chatbot-intent-lifetime {segundos}
@chatbot-intent-patient-profile-can-use {professional,efector,service}
@chatbot-intent-patient-profile-resolve-references {true|false}
@chatbot-intent-patient-profile-update-on-complete-type {professional|efector|service}
@chatbot-intent-patient-profile-update-on-complete-fields {field1,field2}
@chatbot-intent-patient-profile-cache-ttl {segundos}
```

## Script Generador

### Ubicación
`web/console/controllers/ChatbotConfigGeneratorController.php`

### Uso
```bash
# Generar todos los archivos de configuración
php yii chatbot-config/generate

# Generar solo intent-categories.php
php yii chatbot-config/generate --file=categories

# Generar solo intent-parameters.php
php yii chatbot-config/generate --file=parameters

# Generar solo patient-references.php
php yii chatbot-config/generate --file=references

# Forzar regeneración (ignorar cache)
php yii chatbot-config/generate --force

# Validar configuración sin generar
php yii chatbot-config/validate
```

### Funcionalidad

1. **Descubrir Modelos**
   - Usa `ModelDiscoveryService` para encontrar todos los modelos
   - Filtra solo modelos con anotaciones `@chatbot-category`

2. **Extraer Metadata**
   - Lee anotaciones de docblocks usando `ChatbotMetadataExtractor`
   - Extrae información de la estructura del modelo (atributos, relaciones)
   - Valida que los parámetros referenciados existan en el modelo

3. **Generar Archivos**
   - Genera `intent-categories.php` con formato PHP válido
   - Genera `intent-parameters.php` con formato PHP válido
   - Genera `patient-references.php` si hay referencias definidas
   - Mantiene comentarios y formato legible
   - Incluye timestamp y comando usado para generación

4. **Validación**
   - Verifica que todos los handlers existan
   - Valida que los parámetros referenciados existan en `ParameterExtractor`
   - Verifica sintaxis de patrones regex
   - Reporta errores y warnings

## Migración Gradual

### Fase 1: Preparación ✅
1. ✅ Crear el script generador
2. ✅ Documentar el formato de anotaciones
3. ⏳ Crear ejemplos de modelos anotados

### Fase 2: Migración Parcial
1. Anotar modelos nuevos con el nuevo sistema
2. Mantener archivos PHP existentes para modelos antiguos
3. El generador combina ambos (anotaciones + archivos PHP manuales)

### Fase 3: Migración Completa
1. Anotar todos los modelos existentes
2. El generador solo usa anotaciones
3. Los archivos PHP se convierten en solo lectura (generados)

### Fase 4: Automatización
1. Pre-commit hook que valida anotaciones
2. CI/CD que regenera archivos automáticamente
3. Documentación actualizada

## Ventajas

✅ **Single Source of Truth**: El modelo define todo
✅ **Consistencia**: Imposible tener datos inconsistentes
✅ **Mantenibilidad**: Cambios en un solo lugar
✅ **Validación**: El script valida antes de generar
✅ **Documentación**: Las anotaciones documentan el modelo
✅ **Type Safety**: Los parámetros se validan contra el modelo real
✅ **Escalabilidad**: Fácil agregar nuevas entities

## Consideraciones

⚠️ **Anotaciones en Docblocks**: Requiere parseo de docblocks (ya existe en el proyecto)
⚠️ **Migración**: Requiere anotar modelos existentes (puede ser gradual)
⚠️ **Aprendizaje**: El equipo debe aprender el nuevo formato
⚠️ **Herramientas**: Necesita herramientas de validación

## Relación con FLUJO_CHAT_ORQUESTADOR.md

Este documento propone mejoras a la arquitectura actual documentada en [FLUJO_CHAT_ORQUESTADOR.md](./FLUJO_CHAT_ORQUESTADOR.md).

- **FLUJO_CHAT_ORQUESTADOR.md**: Explica cómo usar el sistema actual (configuración manual)
- **CENTRALIZACION_CHATBOT_CONFIG.md**: Propone cómo mejorar la arquitectura (centralización)

Una vez implementada la centralización, el proceso documentado en `FLUJO_CHAT_ORQUESTADOR.md` cambiará de:
- "Edita estos 3 archivos PHP manualmente"
- A: "Anota el modelo y ejecuta el generador"

## Próximos Pasos

1. ✅ Crear documentación (este archivo)
2. ✅ Implementar script generador
3. ⏳ Crear ejemplos de modelos anotados
4. ⏳ Migrar un modelo de prueba (Turno)
5. ⏳ Validar funcionamiento
6. ⏳ Migrar gradualmente otros modelos
7. ⏳ Automatizar en CI/CD

## Referencias

- [FLUJO_CHAT_ORQUESTADOR.md](./FLUJO_CHAT_ORQUESTADOR.md) - Guía de uso del sistema actual
- `ChatbotMetadataExtractor.php` - Servicio que extrae anotaciones
- `ChatbotConfigGeneratorController.php` - Controlador que genera archivos
