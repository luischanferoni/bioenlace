# Fase 4 — Integración captura, derivación y notificaciones

## Objetivo

Cerrar el circuito clínico-administrativo: vincular consulta EMER al episodio, derivación trazable, egreso alineado con libro de guardia, notificaciones útiles.

## Checklist implementación

- [x] `POST iniciar-atencion`: circuito `en_atencion`, evento, `captura_url` (consulta al abrir historia)
- [x] Sincronizar `guardia.estado` → `atendida` al iniciar atención
- [x] `POST asignar` + `POST derivar` + `POST finalizar` (`GuardiaOperacionService`)
- [ ] Push (opcional): `EMERGENCY_*`
- [ ] UI JSON / intents asistente (opcional)
- [x] Web: Atender → `iniciar-atencion`; Triage → modal + `registrar-triage`
- [x] Móvil: Atender → `iniciar-atencion` + timeline
- [ ] Deprecar `Guardia::footerTimeline` (legacy web)

## Derivación a internación

- Reutilizar flujo existente de notificación de internación (`notificar_internacion_id_efector`).
- En tablero: badge “Derivación pendiente” hasta confirmación.

## Asistente (opcional)

- Intents YAML sin hardcode de pantalla; metadata `api_route`, `required_encounter_class: EMER`.
- Documentar en `Assistant/README.md` al agregar intents.

## Criterio de aceptación

- Libro de guardia (`libroGuardia`) sigue consistente con nuevos estados.
- Timeline del paciente muestra guardia + consulta hijo.
- Derivación genera evento auditable.

## Próximo paso

Fase 5: indicadores y export para dirección médica.
