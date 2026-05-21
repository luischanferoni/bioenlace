# Design — Captura clínica

## Por qué dos niveles de carga

| Nivel | Motor | Cuándo |
|-------|--------|--------|
| **1** | `ConsultaProcesamientoService` + `ConsultasConfiguracion` | Varias categorías clínicas en una misma carga (evolución, informe quirúrgico redactar) |
| **2** | Intents + UI JSON / formulario precargado | Una entidad o acción (turno, agenda cirugía) con guardado en submit |

**Alternativa descartada:** un solo motor de intents para todo lo clínico — no cubre pasos multimodelo ni `pasos_json` legacy sin reescribir años de configuración.

El **rol** (paciente/médico) no define el nivel; solo permisos y flujos disponibles.

## Unmapped en tabla única

Propuesta `ai_unmapped_data` con `level`, `scope_type`, `scope_id`, `raw`, `reason`.

**Política A (recomendada):** persistir unmapped solo cuando existe el registro destino en BD (entidad clínica, mensaje de chat, turno final, etc.).

**Alternativa descartada:** columnas JSON ad hoc en cada tabla — difícil de auditar y consultar entre niveles.

## Corrección de texto híbrida

SymSpell + diccionario primero; IA solo si quedan palabras sin sugerencia o baja confianza. Aprendizaje automático solo con `confidence = 1.0`.

**Alternativa descartada:** IA en cada keystroke — costo y latencia inaceptables en consultorio.

## Resumen timeline y sensibilidad

- Texto base del paciente desde **datos ya codificados** (SNOMED), no texto libre del médico/paciente para filtrar.
- Reglas: código → categoría sensibilidad → acción (`generalizar` / `ocultar`) + lista de servicios afectados.
- Resúmenes por servicio cacheados; invalidación al cambiar reglas o nueva consulta.

Ver [flows/timeline-paciente-ia.md](./flows/timeline-paciente-ia.md).

## Anclas transversales

| Área | Componente / ruta |
|------|-------------------|
| Consulta Nivel 1 | `ConsultaController` — `consulta/analizar`, `consulta/guardar` |
| Chat Nivel 2 | `ChatController`, `IntentEngine`, handlers por dominio |
| Corrección | servicios de texto médico + `IAManager::corregirTextoCompletoConIA` |
| Resumen paciente | `ResumenPacienteController` (consola), tablas sensibilidad/resumen |
