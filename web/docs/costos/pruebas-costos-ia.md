# Pruebas de costos de IA

Módulo para ejecutar conversaciones de prueba y medir cuántas llamadas a la IA se **evitaron** (caché de aplicación, dedup, CPU, validación) y cuántas se **simularon** (en pruebas nunca se llama al proveedor real).

Para **tokens Gemini y context caching** en entornos reales, ver [estrategias-reduccion/monitoreo.md](./estrategias-reduccion/monitoreo.md) (`ia_usage_tracking_habilitado`).

## Carpeta de conversaciones

- **Ubicación**: `web/common/data/conversaciones/`
- **Subcarpetas por tipo**: `pre_turno/`, `pre_consulta/`, `consulta_medico/`, `onboarding/`, `sistema/`
- **Archivos**: JSON con nombre descriptivo sin espacios (ej. `sacar_turno_completo.json`)

### Formato del archivo JSON

```json
{
  "tipo": "pre_turno",
  "nombre": "Sacar turno completo",
  "descripcion": "Usuario pide turno e indica servicio y día",
  "mensajes": [
    "Hola, quiero sacar un turno",
    "Para clínica médica",
    "Mañana a la tarde"
  ]
}
```

## Ejecutar por CLI

Desde la raíz del proyecto (carpeta `web`):

```bash
php yii costos/ejecutar-conversacion --conversacion=pre_turno/sacar_turno_completo
```

Si el comando no existe en el entorno, usar tests unitarios (abajo).

## Componente `AICostTracker`

`web/common/components/Platform/Ai/Cost/AICostTracker.php`

| Modo | Activación | Qué mide |
|------|------------|----------|
| **Pruebas** | `iniciarEjecucionPrueba()` (runner/tests) | Evitadas + llamadas **simuladas** (sin HTTP) |
| **Producción / staging** | `ia_usage_tracking_habilitado => true` en `params.php` | `llamada_real`, tokens, `cached_content_token_count`, desglose por `contexto` de `consultarIA` |

### Parámetros (`web/frontend/config/params.php`)

```php
'vertex_ai_model' => 'gemini-2.5-flash-lite',
'ia_usage_tracking_habilitado' => false, // true para calibrar context caching
```

### Resumen (`getResumen()`)

- **evitada_por_***: caché Yii, dedup, CPU, validación
- **llamada_simulada** / **llamada_real**
- **tokens**: `prompt_token_count`, `cached_content_token_count`, `billable_input_token_count`, `ratio_input_en_cache`
- **por_contexto**: p. ej. `asistente-preprocess`, `intent-engine-classification`

## Tests unitarios

`web/common/tests/unit/costos/AICostTrackerTest.php`

```bash
cd web && vendor/bin/codecept run unit common/tests/unit/costos/AICostTrackerTest
```

Los tests **nunca** llaman a la IA real salvo que se configure explícitamente fuera del modo simulación.

## Relacionado

- [costos-api.md](./costos-api.md) — columnas sin / con context caching
- [estrategias-reduccion/context-caching-implicita.md](./estrategias-reduccion/context-caching-implicita.md)
