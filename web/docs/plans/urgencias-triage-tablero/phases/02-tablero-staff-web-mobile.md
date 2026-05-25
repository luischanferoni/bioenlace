# Fase 2 — Tablero operativo (staff: web + móvil)

## Objetivo

Pantalla de **sala de guardia** para admisión, enfermería y coordinación: ver cola, filtrar, asignar médico, detectar demoras. Misma API que usará el médico en Fase 3.

## Checklist implementación

- [x] Tablero en **inicio web** (`site/pacientes` con EMER): `pacientes-listado.js` + plantillas + `guardia-tablero.css`
- [x] Polling 30 s en web (EMER)
- [x] Tarjetas: nivel/color, minutos, circuito, motivo triage, profesional, Atender / Triage
- [ ] Vista dedicada `guardia/tablero` full-screen (opcional; ingreso/libro siguen en `guardia/index`)
- [ ] Acciones: asignar PES, llamar, derivación (Fase 4)
- [ ] Filtros query en UI
- [ ] Sonido SLA superado
- [x] App móvil: tablero en **Inicio** cuando `encounter_class == EMER` (misma API)
- [x] `PacientesController` EMER → `GuardiaQueueService::listadoCompacto` (compat); inicio EMER usa endpoint `tablero`

## UX web (referencia)

```
┌─────────────────────────────────────────────────────────────┐
│ Guardia — Tablero · Efector X          [Filtros] [Actualizar]│
├─────────────────────────────────────────────────────────────┤
│ 🔴 2  Pérez, Juan · DNI · 18 min · Sin asignar  [Asignar]   │
│ 🟠 3  López, Ana  · ...  · 45 min · Dr. Gómez   [Detalle]   │
│ 🟡 5  ...                                                    │
└─────────────────────────────────────────────────────────────┘
```

- Clic fila → panel lateral o modal con triage, vitales, timeline de eventos.
- Ingreso nuevo: botón → formulario existente o modal API `ingresar` (migración progresiva).

## API adicional Fase 2

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/emergency/guardia/<id>/asignar` | Body: `id_profesional_efector_servicio` |
| POST | `/api/v1/emergency/guardia/<id>/llamar` | Evento “paciente llamado a consultorio” (opcional) |
| GET | `/api/v1/emergency/guardia/tablero/resumen` | Contadores por nivel/estado (KPI en vivo) |

## Integración home web

- `_boxGuardia` y listado pacientes EMER: consumir resumen del tablero (últimos 5 + link “Ver tablero”).
- `frontend/views/pacientes/listado.php` plantillas: datos enriquecidos (nivel, tiempo espera).

## Criterio de aceptación

- Staff en web ve cola actualizada sin recargar página completa.
- Asignar médico refleja en tablero del médico (Fase 3) en &lt; 30 s.
- Usuario sin permiso tablero no accede a vista ni API.

## Próximo paso

Fase 3: experiencia móvil médico (cola + triage + atender).
