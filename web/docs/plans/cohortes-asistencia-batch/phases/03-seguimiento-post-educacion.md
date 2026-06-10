# Fase 3 — Seguimiento post y educación

**Estado:** implementada

- Programar touchpoints desde `followup_program` al publicar resumen (`CareFollowupSchedulerService`, hook en `PatientEncounterSummaryPublishService` y al completar pack)
- Cola `care_followup_touchpoint_queue` + cron `care-pack/run-jobs` / `care-pack/process-followups`
- Push `CARE_FOLLOWUP_TOUCHPOINT` + formularios de evolución (`GET|POST /api/v1/care-packs/followup`)
- Módulos `education_bundle` resueltos por `education_refs` en cada touchpoint
- Push a tutores/representantes (`PersonRepresentationNotifyRecipientService`); API followup con `clinical.care_pack_assistance` (mismo permiso que pre-consulta)
