# Fase 0 — Marco clínico-operativo

## Objetivo

Acordar estados, escala de triage, roles y SLAs antes de codificar migraciones. No bloquea un spike técnico de Fase 1 si los defaults están documentados aquí.

## Escala de triage (default)

| Nivel | Manchester (default) | Color UI | Objetivo SLA orientativo (configurable) |
|-------|----------------------|----------|----------------------------------------|
| 1 | Rojo — inmediato | `#c0392b` | Atención inmediata |
| 2 | Naranja — muy urgente | `#e67e22` | &lt; 10 min |
| 3 | Amarillo — urgente | `#f1c40f` | &lt; 60 min |
| 4 | Verde — estándar | `#27ae60` | &lt; 120 min |
| 5 | Azul — no urgente | `#3498db` | &lt; 240 min |

- Institución puede desactivar niveles o usar alias; la **prioridad numérica** (1 = más urgente) es lo que ordena la cola.
- Motivo de consulta: catálogo SNOMED/CIE simplificado interno + texto libre obligatorio.

## Signos vitales mínimos (triage)

| Campo | Obligatorio |
|-------|-------------|
| TA (sistólica/diastólica) o “no tomada” | Sí (con motivo si no tomada) |
| FC | Sí |
| FR | Sí |
| Temperatura | Sí |
| SpO₂ | Recomendado |
| Dolor (0–10) | Recomendado |
| Glasgow | Si alteración de conciencia |

Persistir snapshot JSON en `guardia_triage.vitals_json`.

## Roles y permisos (borrador)

| Acción | Admisión / staff | Enfermería | Médico guardia | Coordinador |
|--------|------------------|------------|----------------|-------------|
| Ingreso paciente | ✓ | ✓ | — | ✓ |
| Triage | ✓ | ✓ | ✓ | ✓ |
| Ver tablero efector | ✓ | ✓ | ✓ | ✓ |
| Asignar médico | ✓ | — | ✓ (tomar caso) | ✓ |
| Iniciar atención / captura | — | — | ✓ | — |
| Finalizar / egreso | ✓ | — | ✓ | ✓ |
| Derivación | — | — | ✓ | ✓ |

Permisos finos vía ApiGhost por ruta API (no lógica en AR).

## Eventos de circuito (tipos)

| `tipo` | Cuándo |
|--------|--------|
| `ingreso` | Alta guardia |
| `triage` | Triage guardado |
| `asignacion` | Cambio de PES asignado |
| `inicio_atencion` | Médico abre captura |
| `fin_atencion` | Consulta asociada cerrada o marcada atendida |
| `derivacion` | Derivación registrada |
| `egreso` | Finalización administrativa |

## Mapeo legacy → circuito

| `guardia.estado` (legacy) | `circuito_estado` (nuevo) |
|---------------------------|---------------------------|
| `pendiente` (sin triage) | `espera_triage` o `ingresado` |
| `pendiente` (con triage) | `espera_medico` |
| `atendida` | `atendido` / `en_atencion` según consulta abierta |
| `finalizada` | `finalizado` |

## Entregables Fase 0

- [ ] Validación con referente clínico de guardia (1 efector piloto)
- [ ] Lista de motivos de consulta v1 (CSV o seed migration)
- [ ] Wireframes baja fidelidad: tablero web, triage móvil, detalle paciente en cola
- [ ] ADR breve en `design.md` si se elige Manchester vs ESI

## Estado

Pendiente de kickoff; defaults de este documento aplican hasta revisión clínica.
