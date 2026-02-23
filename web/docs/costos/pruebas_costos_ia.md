# Pruebas de costos de IA

Módulo para ejecutar conversaciones de prueba y medir cuántas llamadas a la IA se **evitaron** (cache, dedup, CPU, validación) y cuántas se **simularon** (en pruebas nunca se llama al proveedor real).

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

- **tipo**: uno de `pre_turno`, `pre_consulta`, `consulta_medico`, `onboarding`, `sistema`
- **nombre**: nombre legible
- **descripcion**: opcional, para documentación o UI
- **mensajes**: array de strings (mensajes del usuario en orden)
- **userId**: opcional; si no se indica se usa `test-costos`

## Ejecutar por CLI

Desde la raíz del proyecto (carpeta `web`):

```bash
php yii costos/ejecutar-conversacion --conversacion=pre_turno/sacar_turno_completo
```

O con alias:

```bash
php yii costos/ejecutar-conversacion -c pre_turno/sacar_turno_completo
```

Si no se indica conversación, se listan las disponibles. La ejecución **siempre simula** la IA (no se hace ninguna llamada HTTP al proveedor).

## Ejecutar por web (backend)

- **Listar**: `GET /admin/costos/listar-conversaciones` (requiere usuario autenticado).
- **Ejecutar**: `GET` o `POST` con parámetro `conversacion=pre_turno/sacar_turno_completo` en la URL ` /admin/costos/ejecutar-conversacion`.

La respuesta incluye `respuestas` (por cada mensaje) y `resumen_costos` (evitadas por cache/dedup/CPU/validación, llamadas simuladas). Acceso restringido a usuarios autenticados.

## Tests unitarios

En `web/common/tests/unit/costos/AICostTrackerTest.php` se comprueba el tracker (reset, iniciar/finalizar ejecución de prueba, registro de evitadas y simuladas). Un test opcional ejecuta un flujo que pasa por IAManager con simulación activa. Los tests **nunca** llaman a la IA real.

Ejecutar (desde `web`):

```bash
vendor/bin/codecept run unit common/tests/unit/costos/AICostTrackerTest
```

## Resumen de costos

Tras ejecutar una conversación (CLI o web), el resumen incluye:

- **evitada_por_cache**: respuestas obtenidas desde cache
- **evitada_por_dedup**: respuestas reutilizadas por deduplicación
- **evitada_por_cpu**: tareas resueltas por CPU (ej. corrección básica)
- **evitada_por_validacion**: solicitudes descartadas (ej. prompt vacío)
- **llamada_simulada**: veces que se habría llamado a la IA pero se simuló (solo en pruebas)
- **total_evitadas**: suma de las cuatro evitadas

Producción no activa simulación; solo cuando se ejecuta explícitamente el runner de conversaciones o los tests de costos.
