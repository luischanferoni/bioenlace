# Flujo de Corrección y Expansión de Texto Médico

## Descripción General

Sistema híbrido optimizado que combina corrección rápida basada en diccionarios con corrección inteligente mediante IA, minimizando las llamadas costosas a modelos de IA mientras mantiene alta precisión.

## Flujo de Procesamiento (8 Pasos)

### Paso 1: Preservar Notación Médica Válida
- **Objetivo**: Identificar y proteger elementos médicos válidos que no deben ser "corregidos"
- **Método**: Usa patrones regex almacenados en `terminos_contexto_medico` (tipo='regex', categoria='preservar')
- **Ejemplos**: `+++`, `Caf.`, `Tyndall+++`, símbolos de medición válidos
- **Resultado**: Elementos marcados temporalmente con placeholders para restaurarlos después

### Paso 2: Expandir Abreviaturas Conocidas
- **Objetivo**: Expandir abreviaturas médicas ya registradas en la base de datos
- **Método**: Consulta `abreviaturas_medicas` (activo=1) y reemplaza en el texto
- **Priorización**: Si hay `idRrHh`, usa preferencias del médico específico
- **Ejemplos**: `OI` → `ojo izquierdo`, `OD` → `ojo derecho`, `AV` → `agudeza visual`

### Paso 3: Corrección Ortográfica Rápida (SymSpell)
- **Objetivo**: Corregir errores comunes de tipeo usando diccionario médico
- **Método**: Algoritmo SymSpell con diccionario cargado desde `diccionario_ortografico`
- **Ventaja**: Muy rápido, sin costo de IA
- **Resultado**: Texto corregido + lista de palabras sin sugerencias + cambios problemáticos

### Paso 4: Evaluar Necesidad de IA
- **Objetivo**: Decidir si se requiere corrección con IA
- **Criterios**: 
  - Hay palabras sin sugerencias de SymSpell
  - Hay cambios con baja confianza (< 1.0)
- **Resultado**: `true` si necesita IA, `false` si SymSpell fue suficiente

### Paso 5: Corrección con IA (Condicional)
- **Objetivo**: Corregir errores complejos que SymSpell no pudo resolver
- **Método**: Llama a `IAManager::corregirTextoCompletoConIA()` solo si es necesario
- **Modelo**: Llama 3.1 70B Instruct (local)
- **Ventaja**: Solo se usa cuando realmente es necesario, reduciendo costos y latencia

### Paso 6: Restaurar Elementos Preservados
- **Objetivo**: Devolver la notación médica válida al texto final
- **Método**: Reemplaza los placeholders con el texto original preservado
- **Resultado**: Texto final con notación médica intacta

### Paso 7: Guardar Correcciones IA en Diccionario
- **Objetivo**: Aprender de las correcciones realizadas por IA
- **Método**: Guarda en `diccionario_ortografico` con tipo='error'
- **Condición CRÍTICA**: Solo se guardan y activan automáticamente si `confidence = 1.0` (100%)
- **Si confidence < 1.0**: Se descarta automáticamente (no se guarda)
- **Resultado**: Diccionario mejorado para futuras correcciones rápidas

### Paso 8: Guardar Expansiones IA en Abreviaturas
- **Objetivo**: Aprender nuevas expansiones de abreviaturas detectadas por IA
- **Método**: Guarda en `abreviaturas_medicas` con origen='LLM'
- **Condición CRÍTICA**: Solo se guardan y activan automáticamente si `confidence = 1.0` (100%)
- **Si confidence < 1.0**: Se descarta automáticamente (no se guarda)
- **Resultado**: Base de datos de abreviaturas mejorada

## Ventajas del Sistema

### Optimización de Costos
- ✅ Reduce llamadas a IA solo cuando SymSpell no puede resolver
- ✅ Correcciones rápidas sin costo de IA
- ✅ Aprendizaje automático mejora el sistema con el tiempo

### Precisión
- ✅ Preserva notación médica válida (no "corrige" símbolos válidos)
- ✅ Solo aprende de correcciones con 100% de confianza
- ✅ Sistema escalable basado en base de datos (sin hard-coding)

### Escalabilidad
- ✅ Patrones de preservación configurables desde BD
- ✅ Diccionario y abreviaturas crecen automáticamente
- ✅ Sin código hard-coded, todo desde base de datos

## Estructura de Datos

### Tablas Utilizadas

#### `terminos_contexto_medico`
- **Uso**: Patrones regex para identificar notación médica a preservar
- **Campos clave**: `tipo='regex'`, `categoria='preservar'`, `termino` (contiene el regex)

#### `abreviaturas_medicas`
- **Uso**: Almacena abreviaturas y sus expansiones
- **Campos clave**: `abreviatura`, `expansion_completa`, `origen` (LLM/USUARIO), `activo`
- **Aprendizaje**: Nuevas expansiones con origen='LLM' y activo=1 (solo si confidence=1.0)

#### `diccionario_ortografico`
- **Uso**: Diccionario de términos médicos y correcciones ortográficas
- **Campos clave**: `termino`, `correccion`, `tipo` (error/termino), `activo`
- **Aprendizaje**: Nuevas correcciones con tipo='error' y activo=1 (solo si confidence=1.0)

## Ejemplo de Flujo Completo

**Texto Original:**
```
Trauma x hondazo OI. Bmc: inyección mixta +, córnea presenta laseracion para central h 5, 4mm aprox. Tyndall +++. Caf. Pupila isocorica reactiva.
```

**Paso 1 - Preservar:**
- `+++` → `{{PRESERVAR_0}}`
- `Caf.` → `{{PRESERVAR_1}}`

**Paso 2 - Expandir:**
- `OI` → `ojo izquierdo`
- `Bmc` → (si existe en BD)

**Paso 3 - SymSpell:**
- `laseracion` → `laceración` (si está en diccionario)
- `isocorica` → `isocórica` (si está en diccionario)

**Paso 4 - Evaluar:**
- Si quedan palabras sin sugerencias → Necesita IA

**Paso 5 - IA (si necesario):**
- Correcciones adicionales con contexto completo

**Paso 6 - Restaurar:**
- `{{PRESERVAR_0}}` → `+++`
- `{{PRESERVAR_1}}` → `Caf.`

**Paso 7 y 8 - Aprender:**
- Guardar correcciones/expansiones con confidence=1.0

**Texto Final:**
```
Trauma por hondazo ojo izquierdo. Bmc: inyección mixta +, córnea presenta laceración para central h 5, 4mm aprox. Tyndall +++. Caf. Pupila isocórica reactiva.
```

## Configuración

### Parámetros Importantes

- `CONFIDENCE_MINIMA_APROBACION = 1.0`: Solo 100% de confianza para auto-aprobación
- `maxEditDistance` (SymSpell): Distancia máxima de edición (default: 2)
- Cache TTL: Correcciones cacheadas por 1 hora

## Logging

Todos los pasos del flujo se registran en `ConsultaLogger` con:
- Método ejecutado
- Cambios realizados
- Confianza de correcciones
- Tiempo de procesamiento
- Decisiones tomadas (necesita IA o no)

## Notas Importantes

⚠️ **Confianza 100%**: El sistema solo aprende automáticamente de correcciones con confianza perfecta (1.0). Cualquier corrección con confianza menor se descarta para evitar errores.

⚠️ **Sin Hard-coding**: Todos los patrones, diccionarios y reglas están en base de datos, permitiendo escalabilidad sin cambios de código.

⚠️ **Preservación**: La notación médica válida nunca se "corrige", se preserva intacta en el texto final.

