# Casos de Prueba - Corrección de Errores de Tipeo Médico

## Descripción
Este documento contiene casos de prueba para validar la funcionalidad de corrección de errores de tipeo médico implementada con modelos clínicos de Hugging Face.

## Configuración de Pruebas

### Parámetros de Configuración
```php
// En frontend/config/params-local.php
'hf_activar_correccion' => true,
'hf_modelo_clinico' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
```

### Endpoint de Prueba
```
POST /api/v1/consulta/analizar
```

## Casos de Prueba

### 1. Errores de Tipeo Comunes

#### Caso 1.1: Corrección de "laseracion" → "laceración"
**Input:**
```json
{
    "consulta": "Paciente presenta laseracion en brazo derecho",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Paciente presenta laceración en brazo derecho"
- Cambio detectado: "laseracion" → "laceración"
- Confianza: > 0.7

#### Caso 1.2: Corrección de "diabetis" → "diabetes"
**Input:**
```json
{
    "consulta": "Paciente con diabetis tipo 2, control regular",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Paciente con diabetes tipo 2, control regular"
- Cambio detectado: "diabetis" → "diabetes"

#### Caso 1.3: Corrección de "hipertencion" → "hipertensión"
**Input:**
```json
{
    "consulta": "Hipertencion arterial, TA 150/90",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Hipertensión arterial, TA 150/90"
- Cambio detectado: "hipertencion" → "hipertensión"

#### Caso 1.4: Corrección de "prescrivir" → "prescribir"
**Input:**
```json
{
    "consulta": "Se debe prescrivir metformina 500mg",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Se debe prescribir metformina 500mg"
- Cambio detectado: "prescrivir" → "prescribir"

### 2. Errores de Acentos

#### Caso 2.1: Corrección de "sintomas" → "síntomas"
**Input:**
```json
{
    "consulta": "Paciente refiere sintomas de cefalea",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Paciente refiere síntomas de cefalea"
- Cambio detectado: "sintomas" → "síntomas"

#### Caso 2.2: Corrección de "diagnostico" → "diagnóstico"
**Input:**
```json
{
    "consulta": "Diagnostico presuntivo: neumonia",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Diagnóstico presuntivo: neumonía"
- Cambios detectados: "diagnostico" → "diagnóstico", "neumonia" → "neumonía"

### 3. Casos Sin Errores

#### Caso 3.1: Texto Correcto
**Input:**
```json
{
    "consulta": "Paciente con diabetes tipo 2, hipertensión arterial controlada",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- No se detectan errores
- Texto permanece sin cambios
- Tiempo de procesamiento mínimo

### 4. Casos de Múltiples Errores

#### Caso 4.1: Varios Errores en una Consulta
**Input:**
```json
{
    "consulta": "Paciente con diabetis e hipertencion, presenta sintomas de cefalea. Se debe prescrivir tratamiento",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Paciente con diabetes e hipertensión, presenta síntomas de cefalea. Se debe prescribir tratamiento"
- Cambios detectados:
  - "diabetis" → "diabetes"
  - "hipertencion" → "hipertensión"
  - "sintomas" → "síntomas"
  - "prescrivir" → "prescribir"

### 5. Casos de Especialidades

#### Caso 5.1: Errores en Oftalmología
**Input:**
```json
{
    "consulta": "Paciente con catarata incipiente, vision borrosa",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto corregido: "Paciente con catarata incipiente, visión borrosa"
- Cambio detectado: "vision" → "visión"

#### Caso 5.2: Errores en Cardiología
**Input:**
```json
{
    "consulta": "Paciente con arritmia, requiere electrocardiograma",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Texto correcto (no debería cambiar)
- No se detectan errores

### 6. Casos de Límites

#### Caso 6.1: Texto Muy Corto
**Input:**
```json
{
    "consulta": "OK",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- No se detectan errores (texto muy corto)
- Texto permanece sin cambios

#### Caso 6.2: Texto Muy Largo
**Input:**
```json
{
    "consulta": "Paciente de 65 años con antecedentes de diabetis tipo 2, hipertencion arterial, dislipidemia, presenta sintomas de cefalea, nauseas, vomitos, debilidad generalizada, requiere evaluacion neurologica completa, se debe prescrivir tratamiento sintomatico y realizar estudios complementarios incluyendo tomografia computada de craneo, analisis de laboratorio completo, electrocardiograma, ecocardiograma, se recomienda seguimiento estrecho y control en 48 horas",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Resultado Esperado:**
- Múltiples correcciones aplicadas
- Tiempo de procesamiento mayor pero aceptable (< 5 segundos)

### 7. Casos de Fallback

#### Caso 7.1: API de HuggingFace No Disponible
**Configuración:**
```php
'hf_activar_correccion' => true,
// Simular fallo de API
```

**Resultado Esperado:**
- Sistema continúa con texto original
- Log de error registrado
- No se interrumpe el flujo principal

#### Caso 7.2: Corrección Desactivada
**Configuración:**
```php
'hf_activar_correccion' => false,
```

**Resultado Esperado:**
- No se ejecuta corrección
- Texto original se procesa normalmente
- Tiempo de procesamiento mínimo

## Métricas de Evaluación

### Tiempo de Procesamiento
- **Objetivo**: < 2 segundos para textos normales
- **Límite**: < 5 segundos para textos largos
- **Fallback**: Si excede límite, usar texto original

### Precisión de Corrección
- **Objetivo**: > 90% de correcciones correctas
- **Falsos Positivos**: < 5%
- **Falsos Negativos**: < 10%

### Disponibilidad
- **Objetivo**: 99% de disponibilidad
- **Fallback**: Sistema debe continuar funcionando sin corrección

## Logs de Monitoreo

### Logs de Éxito
```
[INFO] Corrección completada en 1.2s. Cambios: 2
[INFO] Corrección aplicada: 'laseracion' → 'laceración'
[INFO] Corrección aplicada: 'diabetis' → 'diabetes'
```

### Logs de Error
```
[ERROR] Error en corrección de tipeo: API timeout
[ERROR] Error calculando confianza: Connection refused
```

### Logs de Configuración
```
[INFO] Corrección de tipeo desactivada, usando texto original
[INFO] No se detectaron errores de tipeo
```

## Comandos de Prueba

### Prueba Manual con cURL
```bash
curl -X POST http://localhost/api/v1/consulta/analizar \
  -H "Content-Type: application/json" \
  -d '{
    "consulta": "Paciente con laseracion y diabetis",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
  }'
```

### Prueba de Rendimiento
```bash
# Ejecutar múltiples requests para medir latencia
for i in {1..10}; do
  time curl -X POST http://localhost/api/v1/consulta/analizar \
    -H "Content-Type: application/json" \
    -d '{"consulta": "Paciente con laseracion", "userPerTabConfig": {"id_rrhh_servicio": 1, "servicio_actual": 1}, "id_configuracion": 1}'
done
```

## Notas de Implementación

1. **Modelo por Defecto**: `PlanTL-GOB-ES/roberta-base-biomedical-clinical-es`
2. **Fallback**: Si falla la API, usar diccionario básico
3. **Caché**: Considerar implementar caché para correcciones comunes
4. **Rate Limits**: HuggingFace tiene límites de requests por minuto
5. **Logging**: Todos los cambios se registran para análisis posterior
