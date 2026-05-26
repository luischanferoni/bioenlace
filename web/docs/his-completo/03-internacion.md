# Internación

**Madurez orientativa:** 3,25 / 4 (~78 %)

## Lo que tenemos

- [x] Episodios de internación con camas y pisos.
- [x] Prácticas, consumos y medicación asociados al episodio.
- [x] Vínculo con nomencladores y facturación parcial según configuración del efector.
- [x] Ingreso desde guardia con `id_guardia` y cierre del pendiente en tablero.
- [x] **Mapa de camas (API + web + móvil IMP):** libre, ocupada, bloqueada, aislamiento; acciones B/A/L en web; chips en app médico.
- [x] **Indicadores:** ocupación %, internaciones activas, estadía media/mediana (API + resumen en web).
- [x] **Alta estructurada:** epicrisis, plantillas por efector/servicio, responsable de sesión, checklist → `doExternacion`.
- [x] **Plantillas epicrisis** (`internacion_epicrisis_plantilla`) con placeholders `{paciente}`, `{fecha_ingreso}`, `{dias_internacion}`.
- [x] Intents asistente `internacion.mapa-camas-flow` e `internacion.alta-estructurada-flow`.

## Lo que falta

- [ ] Administración UI de plantillas por efector (hoy vía BD / migración seed).
- [ ] Firma digital del responsable del alta.
- [ ] Integración plena quirófano–internación–facturación en un solo flujo.

## En producto hoy

- Web: `/internacion/index` (mapa + indicadores), `/internacion/view` (alta API + modal clásico), `/internacion/ronda`.
- Móvil médico (IMP): icono cama en inicio → mapa de camas.
- API: `clinical/internacion/*` (ver `InternacionController`).
