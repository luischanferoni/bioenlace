# Fase 02 — COVID legacy + vistas huérfanas enfermería

## Alcance ejecutado

### A. Módulo COVID (post Fase 01)

- Modelos: `CovidEntrevistaTelefonica`, `CovidFactoresRiesgo`, `CovidInvestigacionEpidemiologica`
- Búsqueda: `CovidEntrevistaTelefonicaBusqueda`
- Migración: `m260605_100000_drop_covid_entrevista_tables` (`covid_factores_riesgo`, `covid_investigacion_epidemiologica`, `covid_entrevista_telefonica`)

**No tocar:** columna `covid` en `infraestructura_sala` (flag de sala COVID, distinto del módulo entrevistas).

### B. Enfermería — limpieza parcial

- Vistas `consulta-atenciones-enfermeria/*` sin referencias (index, create, _form, …)
- `ConsultaAtencionesEnfermeriaBusqueda` (archivo `AtencionesEnfermeriaBusqueda.php`) — sin uso
- Modelo duplicado `AtencionesEnfermeria` → controller usa `ConsultaAtencionesEnfermeria`
- Menú: quitar enlace roto `/atenciones-enfermeria/index`
- Backend persona: quitar enlace roto `atenciones-enfermeria/create`

**Mantener:** `AtencionesEnfermeriaController` (view histórico + reporte mensual), `ConsultaAtencionesEnfermeria`, internación MVC.

## Diferido (Fase 02 original en overview — aún cableado)

| Ítem | Motivo |
|------|--------|
| Sub-controllers internación (`InternacionDiagnostico*`, medicamento, práctica, hcama) | `internacion/v2/_view_*` y `internacion/view` los invocan |
| `InternacionAtencionesEnfermeriaController` | Flujo activo internación |
| `EncuestaParchesMamariosController` | Enlaces en `personas/view` |
| `AutofacturacionController`, `ReporteController` | SUMAR / planillas operativas |
| Modelo `Consulta` + tablas `consultas` | Fase 03 |
