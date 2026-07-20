# Fase 4 — Reglas de perfil preventivo

## Objetivo

Aplicar protocolos **sin** Condition previa, según edad, sexo y otras señales de perfil — p. ej. controles ginecológicos por edad, recordatorio de vacunas en pediatría.

## Reglas en metadata (extensión del YAML)

```yaml
applies:
  age_years: { min: 25, max: 65 }
  sex: [F]
  # opcional: life_stage, jurisdiction
```

Matcher combina Condition **o** perfil (OR documentado por protocolo).

## Ejemplos iniciales (contenido clínico a validar con equipo)

| Protocolo ejemplo | Disparador | Acción inicial |
|-------------------|------------|----------------|
| Control preventivo adulto (placeholder) | edad ≥ 40 | Pedir turno / consulta |
| Vacunas pediátricas (orientación) | edad &lt; 18 | Info + deep-link SISA o “consultar en el centro” (sin Immunization HIS) |
| Control según sexo/edad | mujer 25–65 | Turno / mensaje “control recomendado” |

El contenido clínico exacto **no** se inventa en código: va en YAML revisado; esta fase solo cablea el motor de `applies`.

## Vacunas

- **No** implementar ImmunizationRecommendation completo.
- Acción posible: `external_info` / abrir recurso provincial / texto + turno pediatría.
- CarePacks IA siguen fuera.

## Implementación

| Pieza | Ubicación |
|-------|-----------|
| YAML preventivos | `Clinical/metadata/care_protocols.yaml` (`control_preventivo_adulto`, `control_ginecologico_edad`, `vacunas_pediatricas_orientacion`) |
| Matcher perfil | `CareProtocolMatcherService::matchByProfile` / `actionsForProtocolId` |
| Hub anclas `prot:{id}` | `ControlSeguimientoHubService::listHubItems` + `applyAnchorToDraft` |
| Flow | `control_hub_kind: protocol` → `cs_condition_acciones` + `protocol_id` en open_ui |
| Edad/sexo | `Persona::getEdad()` / `getSexoLetra()` |

Copy de hub: “Sugerido según tu perfil · Consultá con tu equipo”.

## Checklist

- [x] Matcher soporta `age_years` / `sex` desde Persona.
- [x] ≥1 protocolo preventivo en YAML de ejemplo (contenido orientativo; validar clínicamente).
- [x] Hub muestra ítems recomendados por perfil (`prot:` + `hub_label`).
- [x] Tests matcher perfil + ancla protocolo + acciones por `protocol_id`.

## Riesgo clínico

No presentar recomendaciones preventivas como indicación médica firme hasta validación; copy: “Control habitual sugerido según perfil” / “Consultá con tu equipo”.
