# Convenciones de relaciones ActiveRecord (Yii2)

Ámbito: modelos PHP (`common/models`, consumo en `frontend`/`backend`). Las relaciones se declaran con `getFoo()` y se acceden como `$model->foo`.

## Idioma y estilo

- **Dominio clínico en español**: `getPersona()`, `getConsulta()`, `getEfector()`.
- **Singular / plural** al estilo Yii: `hasOne` → singular (`profesionalEfectorServicio`), `hasMany` → plural (`profesionalEfectorServicios`).
- **Acrónimos**: `Rrhh`, `Pes` en PascalCase en nombres de método (`getRrhh()`, `getProfesionalEfectorServicio()`).

## RRHH y PES

| Concepto | Relación | Propiedad mágica |
|----------|----------|------------------|
| Fila `rr_hh` de una persona | `getRrhh()` | `rrhh` |
| Fila `profesional_efector_servicio` | `getProfesionalEfectorServicio()` | `profesionalEfectorServicio` |
| El recurso humano del **profesional que atiende** una consulta (vía PES / persona), distinto del paciente | `getProfesionalRrhh()` | `profesionalRrhh` |
| Lista de filas PES agrupadas para UI de turnos (atributo virtual en `ServiciosEfector`) | — | `profesionalesPes` |

No usar el término **Efector** en el nombre de una relación que solo enlaza `rr_hh` por persona (evita confusion con la tabla legacy eliminada).

## Inventario automático

Desde el directorio `web/`:

```bash
php tools/inventory_ar_relations.php
php tools/inventory_ar_relations.php --usage-count
php tools/inventory_ar_relations.php --json > tools/output/ar_relations_inventory.json
php tools/inventory_ar_relations.php --legacy-getid --fail-on-legacy-getid
```

La detección es heurística (cuerpo del getter contiene `hasOne(`, `hasMany(`, `viaTable(`, `via(`).

## API v1

Los JSON de la API **no** deben depender de nombres mágicos de AR salvo que un serializer los exponga explícitamente en `fields`/`expand`. Ante cambios de relación, revisar serialización y clientes (ver `API_RELACIONES_EXPAND.md` en esta carpeta si existe).

## Estado del programa de renombrado

- **Hecho:** paquete RRHH/PES (`getProfesionalRrhh`, `getRrhh`, `profesionalesPes`, relaciones muertas `RrHhEfector` sustituidas por PES/puente condición laboral), vistas PHP tocadas, inventario en `web/tools/inventory_ar_relations.php`.
- **Hecho (internación + infraestructura):** `Efector::getIdRrHhs()` deja de usar la tabla eliminada `rr_hh_efector` y pasa por `profesional_efector_servicio`; `SegNivelInternacionSuministroMedicamento::getRrhhSuministra()`; vistas de práctica/suministro de internación y sala corrigen cadena a `Rrhh->persona` (sin `->rrhh->idPersona`); `getRrhhSuministra` en suministro.
- **Hecho (Fase A — getters `getIdXxx` en relaciones AR):** el inventario con `--legacy-getid` queda en **0** candidatos; getters alineados con `$model->consulta`, `$model->tipoConsulta`, `$model->servicios` / `$model->rrhhs`, enlaces persona–antecedente/domicilio/teléfono/mails, etc., con `rg` de consumidores en `web/`.
- **Hecho (Fase B — barrido incremental):** `busquedas/LocalidadBusqueda` y `busquedas/EfectorBusqueda` usan `joinWith('departamento')` / `joinWith('localidad')` acorde a los getters; `Medicamento` enlaza `consultas_medicamentos` vía `ConsultaMedicamentos` y expone alias `getConsultaMedicamentos()`; docblocks `@property-read Consulta $consulta` en motivos/síntomas/obstetricia/diagnósticos derivados de consulta.
- **Hecho (Fase B — oleada `busquedas/`):** revisión de `joinWith` en el directorio: los únicos usos activos ya coinciden con getters (`departamento`, `localidad`, `paciente`, `servicio`, `efector`, `persona`, `autofacturacion`, `sala`, `categoria`); comentario corregido en `BarriosBusqueda` (modelo `Barrios` enlaza `localidad`, no `idDepartamento`). Snapshot JSON regenerado en `web/tools/output/ar_relations_inventory.json` para comparar regresiones.
- **Hecho (Fase B — dominio Consulta):** relaciones canónicas en `Consulta` (`diagnosticos`, `medicamentos`, `practicasPostDiagnostico` / `practicasPreDiagnostico`, `sintomas`, `obstetricia`, `evolucion`, `antecedentesPersonales` / `antecedentesFamiliares`); getters antiguos conservados como alias para `pasos_json`; mapa actualizado en `ConsultaProcesamientoService::obtenerRelacionConsulta`; vistas/controladores migrados a los nombres nuevos donde aplica.
- **Hecho (Fase B — dominio internación):** `SegNivelInternacion` expone `diagnosticos`, `practicas`, `medicamentos`, `suministrosMedicamentos`, `atencionesEnfermeria` (alias `getSegNivelInternacion*`); `orderBy` de suministros corregido a constantes `SORT_ASC`; `SegNivelInternacionTipoAlta` / `TipoIngreso` con `getInternaciones()` y alias `getSegNivelInternacions()`; docblock `InfraestructuraCama`; vistas `internacion/view_old` y `v2/_view_suministros` actualizadas.
- **Hecho (Fase B — dominio turnos/agenda):** `Turno::getPaciente()` como relación canónica al paciente; `getPersona()` como alias; `TurnoBusqueda::joinWith('paciente')`; vistas/controladores/servicios/API/consola que usaban `$turno->persona` migrados a `$turno->paciente`; `Consulta::obtenerPaciente()` usa `$this->turno->paciente`.
- **Hecho (Fase B — programas de salud / diabetes):** `Programa::inscripciones` + alias `personaProgramas`; `PersonaPrograma::inscripcionesDiabetes` + alias `personaProgramaDiabetes`; `PersonaProgramaDiabetes::dispensas` + alias `dispensaProgramaDiabetes`, `empadronamiento` + alias `personaPrograma`; `DispensaProgramaDiabetes::fichaDiabetes` + alias `personaProgramaDiabetes`; docblocks `@property-read` corregidos.
- **Hecho (Fase B — SUMAR / autofacturación):** `sumar\Autofacturacion::getConsulta()` corregido a **hasOne** (coherente con `Consulta::getAutofacturacion()`); docblock y `use` de modelos; etiqueta “Beneficiario” en `attributeLabels`; sin cambios de nombre de relación (ya en español / PES).
- **Revisión Snomed (`common/models/snomed/*.php`):** tablas de nomenclatura sin relaciones AR declaradas en los modelos actuales; sin renombres en esta oleada.
- **Inventario actual (heurística):** `php tools/inventory_ar_relations.php` — orden de magnitud **~316** relaciones AR en los roots escaneados; snapshot JSON en `web/tools/output/ar_relations_inventory.json` (regenerar tras cambios grandes); siguientes oleadas por carpeta según la misma guía.
- **Gate CI (regresión `getId*`):** desde `web/`, `php tools/inventory_ar_relations.php --legacy-getid --fail-on-legacy-getid` debe terminar con código de salida **0**. Si el contador sube, fallar el job.
