# API v1: relaciones y serialización

## Inventario (renombrado PES / RRHH)

Búsqueda realizada sobre `web/frontend/modules/api/v1` por referencias a propiedades legacy `rrhhEfector` / expand con ese nombre: **sin coincidencias**.

Las respuestas JSON suelen armarse con arrays explícitos en controladores o con `toArray()` sobre atributos; los renombres de getters AR (`profesionalRrhh`, `rrhh`, etc.) **afectan sobre todo a vistas Yii y a código PHP que encadena relaciones**, no a claves JSON salvo que se expongan relaciones por nombre.

## Auditoría Fase A / busquedas (convenciones AR)

- Revisión por `rg` en `web/frontend/modules/api/v1`: **sin** uso de `tipoConsulta`, `idTipoConsulta`, `medicamentosConsultas` ni `consultaMedicamentos` como claves de serialización o `expand`.
- Los cambios de `joinWith` en modelos de búsqueda (`LocalidadBusqueda`, `EfectorBusqueda`) **no** alteran el contrato JSON de la API; solo corrigen nombres de relación al construir `ActiveQuery`.

## Modelo `Consulta` (dominio ambulatorio)

En `common\models\Consulta`, las relaciones canónicas preferidas son propiedades mágicas en español sin prefijo redundante `consulta`: `diagnosticos`, `medicamentos`, `practicasPostDiagnostico`, `practicasPreDiagnostico`, `sintomas`, `obstetricia`, `evolucion`, `antecedentesPersonales`, `antecedentesFamiliares`. Los nombres históricos (`diagnosticoConsultas`, `consultaPracticas`, etc.) siguen disponibles como alias para compatibilidad con `pasos_json` y clonación.

## Modelo `SegNivelInternacion` (internación)

Propiedades mágicas preferidas: `diagnosticos`, `practicas`, `medicamentos`, `suministrosMedicamentos`, `atencionesEnfermeria`, además de `atenciones` (consultas hijas del episodio). Los nombres con prefijo `segNivelInternacion*` se mantienen como alias. En `SegNivelInternacionTipoAlta` / `TipoIngreso` / cama: relación inversa `internaciones` (alias `segNivelInternacions`).

## Modelo `Turno` (agenda)

El paciente del turno se expone como **`paciente`** (`getPaciente()` → `$turno->paciente`), alineado con `Consulta` y `Guardia`. **`persona`** sigue como alias. Las respuestas JSON de API que arman objetos anidados `paciente: { ... }` no cambian de forma por este renombre de relación AR; solo el acceso PHP/Yii.

## Programas de salud y diabetes

- `Programa`: `inscripciones` (empadronamientos `PersonaPrograma`), alias `personaProgramas`.
- `PersonaPrograma`: `inscripcionesDiabetes`, alias `personaProgramaDiabetes`.
- `PersonaProgramaDiabetes`: `dispensas`, `empadronamiento` (padre `PersonaPrograma`); aliases históricos conservados.
- `DispensaProgramaDiabetes`: `fichaDiabetes` (padre `PersonaProgramaDiabetes`), alias `personaProgramaDiabetes`.

## SUMAR (`common\models\sumar\Autofacturacion`)

`consulta` es relación **hasOne** con `Consulta` (antes estaba declarada como hasMany por error). Resto: `beneficiario`, `rrhh`, `profesionalEfectorServicio`.

## Recomendación ante futuros renombres

1. Antes de renombrar una relación usada en un endpoint, buscar: `toArray(`, `fields()`, `extraFields()`, `expand`, `serializer`.
2. Si un cliente depende de una clave derivada del nombre de relación, mantener **compatibilidad** una versión (campo deprecado en paralelo) o acordar **breaking change** en release notes.
