# Geo

Maestro geográfico multi-país: país, provincia, departamento, localidad, barrio, y los recursos institucionales provinciales con su tipo y alias.

Existe como dominio porque tiene **dueño propio**: nadie más persiste estas filas, y varios dominios las consumen (`Person` para el domicilio, `Organization` para ubicar el efector, `Clinical` para derivar a un recurso provincial). Antes estaba repartido entre `Domain/Person/Service/` y `Domain/Organization/Service/`, que es justo la dispersión que el árbol espejo elimina.

| Qué | Dónde |
|-----|-------|
| Modelos (AR) | `common/models/Geo/` |
| Lookup y sugerencia en request | `Service/` |
| Carga del maestro | `Service/Seed/` (consola; el request no lee archivos) |

La separación runtime/seed la pide [`runtime-datos-vs-metadata.mdc`](../../../../../.cursor/rules/runtime-datos-vs-metadata.mdc): el maestro se consulta en **BD** con cache, y se puebla desde consola.

## Quién entra por servicio

Los flujos de otros dominios no leen el AR de geo directamente: piden por servicio. Ejemplo: el hidratador de recurso provincial del asistente vive en `Domain/Person/Assistant/` porque el sujeto es el paciente, y consume `Service/ProvincialResourceLookupService`.
