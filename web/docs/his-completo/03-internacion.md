# Internación

**Madurez orientativa:** 3,3 / 4 (~82 %)

## Lo que tenemos

- [x] Episodios de internación con camas y pisos.
- [x] Prácticas, consumos y medicación asociados al episodio.
- [x] Vínculo con nomencladores y facturación parcial según configuración del efector.
- [x] Ingreso desde guardia con `id_guardia` y cierre del pendiente en tablero.
- [x] **Mapa de camas (API + web + móvil IMP):** libre, ocupada, bloqueada, aislamiento; acciones B/A/L en web; chips en app médico.
- [x] **Indicadores:** ocupación %, internaciones activas, estadía media/mediana (API + resumen en web).
- [x] **Alta estructurada:** epicrisis, plantillas por efector/servicio, responsable de sesión, checklist → `doExternacion`.
- [x] **Plantillas epicrisis** (`internacion_epicrisis_plantilla`) con placeholders `{paciente}`, `{fecha_ingreso}`, `{dias_internacion}`, `{documento}`.
- [x] **ABM plantillas** (web `/internacion-epicrisis-plantilla/*` + API `clinical/internacion-epicrisis-plantilla/*`): alta, edición, activar/desactivar por efector; globales editables solo por superadmin.
- [x] Intents asistente `internacion.mapa-camas-flow` e `internacion.alta-estructurada-flow`.

## Lo que falta

- [ ] Firma digital del responsable del alta.
- [ ] Integración plena quirófano–internación–facturación en un solo flujo.
- [ ] ABM de plantillas y mapa de camas en app móvil (hoy web + API; móvil solo mapa de lectura/operación básica).

## En producto hoy

| Superficie | Ruta / entry |
|------------|----------------|
| Mapa + indicadores | Web `/internacion/index` |
| Episodio y alta | Web `/internacion/view`, `/internacion/ronda` |
| ABM plantillas | Web `/internacion-epicrisis-plantilla/index` |
| Mapa móvil | App médico — inicio (efector en sesión) |
| API operativa | `GET/POST /api/v1/clinical/internacion/*` |
| API ABM plantillas | `/api/v1/clinical/internacion-epicrisis-plantilla/*` |

Documentación de producto: [internacion.md](../producto/internacion.md).
