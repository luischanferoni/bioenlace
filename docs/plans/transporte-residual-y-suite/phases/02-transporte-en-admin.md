# Fase 02 — Transporte en admin y brecha del invariante

## Objetivo

Cerrar el hueco por el que el admin quedó fuera del invariante: no alcanza con migrar los
tres archivos, hay que hacer que el test (y el hook) miren `admin/` y `frontend/`, o el mismo
hueco se reabre en el próximo cambio.

Al terminar, `git grep "new Client(" "curl_init("` sobre `web/` sólo devuelve
`Shared/Infrastructure/Http/` y los tests.

## Tamaño estimado

**Medio (1 día).** `FormController` tiene 30 requests, pero son repetitivos: casi todos son
el mismo patrón (`createRequest → setUrl($host . '/ruta') → send`).

## Archivos

| Archivo | Hoy | Qué hacer |
|---------|-----|-----------|
| `admin/controllers/FormController.php` | 8 factories de `Client`, 30 requests a `Yii::$app->params['hostFormsAPI']` | Un ACL `FormsApiClient` en `Infrastructure/` del BC que corresponda (formularios dinámicos), con un método por endpoint. El controller deja de hablar HTTP. |
| `admin/views/form/form.php` | 1 request en la vista, `echo` de un mensaje de error | La opción del campo la resuelve el controller (o el ACL) y la vista recibe el arreglo. Sin request en la vista: viola vistas delgadas. |
| `admin/controllers/PersonasController.php` | `caller()` con `curl_*` a `http://170.254.60.17/SumarApi/…` y credenciales literales | Ver "SumarApi" abajo. |

## SumarApi (decidir antes de migrar)

`caller()` apunta a una IP fija por HTTP plano, con usuario y contraseña en el código, y lo
único que lo usa es `token()`, que **no se llama desde ningún lado**. Antes de envolverlo en
un ACL hay que decidir:

- si `SumarApi` sigue siendo una integración viva → ACL nuevo, URL y credenciales a config
  (params o `.env`, nunca en el código), y verificar si el endpoint expone HTTPS;
- si está muerto (parece) → se borra `caller()` y `token()`.

No migrar código muerto con credenciales a un cliente nuevo y prolijo: eso lo perpetúa.

## Tareas

- [ ] ACL de la API de formularios: un método por operación (crear form, secciones,
      preguntas, reglas, instancias, tipos, conteos), con la URL base desde config.
- [ ] `FormController`: usar el ACL. Mismo comportamiento y mismos mensajes al usuario; los
      `isOk` sobre la respuesta se traducen a las mismas ramas.
- [ ] `form.php`: sacar el request y el `echo` de error; que la vista reciba datos.
- [ ] Resolver `SumarApi` según la decisión de arriba.
- [ ] **Ampliar `ArchitectureHttpUsageTest`** a `admin/` y `frontend/`: ajustar `scan()` para
      recorrer también esos roots (las rutas permitidas siguen siendo las mismas).
- [ ] Ampliar el hook `.github/hooks/http-guard` al mismo alcance, o dejar anotado por qué el
      hook queda más angosto que el test.
- [ ] Test del ACL con transporte falso: éxito y error de la API de formularios. Si `caller()`
      sobrevive, test de que la URL y el verbo salen como hoy.

## Aceptación

- `git grep` limpio en todo `web/` (sólo base y tests).
- El invariante falla si alguien agrega un `new Client(` en `admin/` — comprobarlo
  introduciendo uno a propósito y revirtiéndolo.
- La vista de formularios no hace I/O.
- `php -l` limpio y `codecept run unit platform/core/ArchitectureHttpUsageTest.php` verde.

## Riesgos

- **El admin es producción.** `hostFormsAPI` apunta a una IP interna: un cambio de URL base
  puede dejar el alta de formularios caída. Mantener el param tal cual, sólo centralizarlo.
- **30 llamadas en un solo controller.** No hacer un refactor de la pantalla en el mismo
  cambio: primero el transporte, después (si hace falta) la estructura.
- **Credenciales.** Si `SumarApi` está vivo, sacarlas del código es parte del trabajo, no una
  mejora opcional.
