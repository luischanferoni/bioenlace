# Fase 4 — Integración captura, derivación y notificaciones

## Objetivo

Cerrar el circuito clínico-administrativo: vincular consulta EMER al episodio, derivación trazable, egreso alineado con libro de guardia, notificaciones útiles.

## Checklist implementación

- [ ] `POST iniciar-atencion`: crear o reutilizar `Consulta` con `parent_class = GUARDIA`, `parent_id`, encounter EMER; idempotente si ya existe consulta abierta
- [ ] Sincronizar `guardia.estado` legacy `atendida` al guardar consulta / al iniciar según regla acordada
- [ ] `POST derivar`: campos existentes (`id_efector_derivacion`, `condiciones_derivacion`, `notificar_internacion_id_efector`) + evento + cambio `circuito_estado`
- [ ] `POST finalizar`: egreso administrativo; validar consulta cerrada si política del efector lo exige
- [ ] Push (opcional): `EMERGENCY_ASSIGNED_TO_YOU` al asignar PES; `EMERGENCY_PATIENT_CRITICAL` nivel 1–2 (solo roles configurados)
- [ ] UI JSON / intents (opcional): `urgencias.ver-tablero`, `urgencias.triage-paciente` en `Assistant/SubIntentEngine/schemas/intents/` + catálogo `ClinicalUiActionCatalog`
- [ ] Web: botón “Atender” en tablero usa misma API que móvil
- [ ] Deprecar duplicación `Guardia::footerTimeline` lógica en API-driven actions donde sea posible

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
