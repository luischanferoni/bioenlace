# Siguiente paso — clean-legacy

**Estado del programa:** Fases 01, 02, 03, 03b, 03c (pasos 1–8), 03d y migraciones base **hechas**.  
**Fase activa:** **03e** — tablas hijas legacy → FHIR + limpieza de AR muertos.  
**Después:** **04** — turnos MVC residual, nomenclador, RBAC web.

---

## Regla de avance

1. Completar el **paso actual** de 03e (código + smoke).
2. Solo entonces ejecutar `m260526_150002` (drop tablas hijas) en el entorno.
3. Marcar el paso en [PROGRESS.md](./PROGRESS.md) y pasar al siguiente.

---

## Fase 03e — orden de trabajo

| Paso | ID | Entregable | Criterio de hecho |
|------|-----|------------|-------------------|
| 0 | `03e-0` | Nomenclador + reportes solo FHIR | Grids motivos/dx/alergias sin `consultas_*`; planillas 4/farmacia sin AR legacy |
| 1 | `03e-1` | Derivaciones → `service_request` (referral) | Referencias + turnos sin `consultas_derivaciones` |
| 2 | `03e-2` | Diagnósticos previos → `clinical_condition` | `DiagnosticoConsultaRepository` sin `DiagnosticoConsulta` AR |
| 3 | `03e-3` | Alergias → `allergy_intolerance` | API paciente + nomenclador + export legal |
| 4 | `03e-4` | Motivos SNOMED | Nomenclador motivos vía `encounter.reason_text` o `Condition` |
| 5 | `03e-5` | Odontología reportes | Planilla C7 / CPO desde `procedure` + ext |
| 6 | `03e-6` | Internación auxiliar | Balance/régimen/suministro + `seg_nivel_*` → FHIR o episodio |
| 7 | `03e-7` | Limpieza AR | Borrar AR huérfanos, dead code `ConsultaProcesamientoService`, forms sin callers |
| 8 | `03e-8` | Drop BD hijas | `php yii migrate` → `m260526_150002` + smoke [MIGRATIONS.md](./MIGRATIONS.md) |

**Ahora (agente / PR):** pasos **6** (internación auxiliar) y **8** (drop hijas tras smoke).

Detalle: [phases/03e-tablas-hijas-fhir.md](./phases/03e-tablas-hijas-fhir.md)

---

## Fase 04 — después de 03e-8

| Ítem | Archivo guía |
|------|----------------|
| Turnos `index2` / vistas legacy | [phases/04-turnos-nomenclador-rbac.md](./phases/04-turnos-nomenclador-rbac.md) |
| RBAC rutas `guardia/*`, `internacion-*` | idem |
| `ConsultasConfiguracion` → solo `EncounterDefinition` en imports | idem |

---

## Fuera de alcance (mantener)

- `PacienteController::actionFormularioConsulta` (shell captura)
- `InternacionController` hub / ronda / view admin
- Backend `ConsultasConfiguracionController`
- Flutter / asistente (salvo intents ya en 03d)
