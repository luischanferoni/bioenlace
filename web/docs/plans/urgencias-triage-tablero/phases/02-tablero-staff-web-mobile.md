# Fase 2 — Tablero operativo (staff: web + móvil)

## Objetivo

Pantalla de **sala de guardia** para admisión, enfermería y coordinación: ver cola, filtrar, asignar médico, detectar demoras. Misma API que usará el médico en Fase 3.

## Checklist implementación

- [ ] Vista web dedicada `guardia/tablero` (o módulo SPA ligero) — **sin** lógica de negocio en `registerJs` masivo; JS en `frontend/web/js/guardia/tablero.js` + AssetBundle
- [ ] Polling cada 15–30 s (o WebSocket futuro; MVP polling)
- [ ] Tarjetas por paciente: nombre, documento, nivel/color, minutos espera, estado, médico asignado
- [ ] Acciones: asignar PES, abrir detalle, marcar “llamado” (evento opcional), link derivación
- [ ] Filtros: sin triage / espera médico / en atención / todos activos
- [ ] Sonido o badge opcional cuando SLA superado (config por nivel)
- [ ] App móvil staff: vista **solo lectura + asignar** en tablet (misma API); prioridad menor que Fase 3 si hay presión de tiempo
- [ ] Unificar `PacientesController::guardiasPendientesPorEfector` → delegar en `GuardiaQueueService`

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
