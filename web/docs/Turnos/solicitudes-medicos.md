# Solicitudes entre médicos (`solicitud_rrhh` en base de datos)

## Modos (`efector_turnos_config.modo_comunicacion_medicos`)

| Valor | Comportamiento |
|--------|----------------|
| `deshabilitado` | No se listan ni crean solicitudes. |
| `directo` | Requiere `id_destinatario_profesional_efector_servicio` al crear. |
| `intermediario` | Sin destinatario fijo al crear (gestión manual posterior). |
| `auto_asignacion` | Asigna otro profesional (PES) del mismo efector (heurística simple). |

## API

- `GET api/v1/solicitud-profesional`
- `POST api/v1/solicitud-profesional` — `mensaje`, opcional `tipo`, `id_destinatario_profesional_efector_servicio` (modo directo)

Las respuestas de listado exponen `id_solicitante_profesional_efector_servicio` e `id_destinatario_profesional_efector_servicio` (valores persistidos en columnas `id_solicitante_rr_hh` / `id_destinatario_rr_hh`).

## Admin

Listado reciente en backend: Efector → **Config. turnos**.
