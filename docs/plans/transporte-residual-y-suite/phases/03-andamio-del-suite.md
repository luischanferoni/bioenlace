# Fase 03 — Andamio del suite

## Objetivo

Que las fallas que quedan en el suite sean **de producto**, no de armado. Mientras haya
errores de esquema, de guard o de clases no mockeables, no se puede distinguir una regresión
real de un test mal montado.

Al terminar esta fase el número de fallas baja por causas estructurales, no por parches en
las aserciones.

## Tamaño estimado

**Chico (medio día).** Cada ítem es acotado y verificable por separado.

## Tareas

### 1. Esquema de la BD de test (5 errores)

Hoy los servicios fallan con columnas que el esquema no tiene (`password_reset_token`,
`domicilio_verificacion_inicio`) y con un campo obligatorio sin default.

- [ ] Verificar si la base que usa `common/config/test-local.php` está al día con
      `web/common/migrations/`. No asumirlo: comparar `migrate/history` contra el esquema real.
- [ ] Si falta correr migraciones, correrlas; si el esquema ya está al día, el problema es el
      modelo y no la base.
- [ ] Si el test necesita datos, que los cree el propio test con fixtures explícitas, no que
      dependa de filas de la base de desarrollo.

### 2. Carpetas residuales `integrations/` (3 fallas)

El guard de árbol espejo reporta `integrations` en `common/models/`, `tests/unit/` y
`controllers/`. Es una carpeta residual del esquema anterior: los ACL viven ahora en
`Infrastructure/External/`.

- [ ] Revisar qué queda en cada una de las tres y reubicarlo en el dominio que corresponda.
- [ ] Borrar las carpetas; el guard deja de tener excepción que reportar.

### 3. `Application/Catalog/` fuera de los roles (4 fallas)

`BoundedContextLayerShapeTest` marca `Application/Catalog/` en tres BC. O la carpeta está en
el lugar equivocado, o el guard no contempla el rol "catálogo" y hay que declararlo.

- [ ] Decidir cuál de las dos: si el contenido es vocabulario y textos que consume un motor,
      el guard debe permitirlo explícitamente (y quedar como rol del catálogo de capas); si
      es lógica de aplicación, mover.
- [ ] Actualizar la documentación de la gramática de carpetas en el mismo cambio.

### 4. Clases `final` mockeadas (1 error)

PHPUnit no puede mockear una clase `final`. En el plan anterior ya se resolvieron tres
quitando el `final`. Antes de repetir el remedio, evaluar la alternativa: **inyectar una
interfaz** en lugar de mockear la clase concreta.

- [ ] Elegir por caso: contrato (preferido) o quitar `final` (excepción documentada en el PR).

### 5. Errores de contrato entre test y código (7 errores)

- [ ] `EncounterCaptureReviewPresenter::build()` recibe `false` donde espera un arreglo o
      `null`: decidir si el presentador tolera el caso o si el test está mal.
- [ ] Propiedad inexistente en `DeclarativePlanExecutionResult`: alinear el test con el DTO
      o agregar la propiedad si el producto la necesita.
- [ ] Propiedad desconocida en `Integrations`: mismo criterio.
- [ ] `file_get_contents` a rutas que ya no existen (2 tests): apuntar al path actual o
      reemplazar por el catálogo en memoria.
- [ ] `DomainOperationForbiddenException` y `InvalidArgumentException` ("Elegí al menos un dato
      editable") que el test no espera: el código las lanza por una regla; confirmar cuál es
      la correcta antes de tocar el test.

## Aceptación

- Los 13 errores del suite bajan a los que sean de producto, con motivo escrito.
- El guard de árbol espejo corre sin excepciones de carpetas.
- Ningún test nuevo depende de filas de la base de desarrollo.

## Riesgos

- **"Arreglar" el test en lugar del código.** Si la expectativa es correcta, el bug es del
  código: anotarlo y arreglarlo, no relajar la aserción.
- La base de desarrollo se usa como base de test: cualquier limpieza de datos puede afectar
  a quien esté probando la app a mano.
