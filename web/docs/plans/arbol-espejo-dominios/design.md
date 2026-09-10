# Design

## 1. La invariante de forma

```text
<capa>/<dominio|platform>/<entidad>/<accion o archivo>
```

| Capa | Forma objetivo |
|------|----------------|
| Servicios | `common/components/Domain/<Dominio>/…` · `common/components/Platform/<Área>/…` |
| Modelos | `common/models/<Dominio>/…` · `common/models/Platform/…` |
| Controllers API | `frontend/modules/api/v1/controllers/<dominio>/…` |
| UI JSON | `frontend/modules/api/v1/views/json/<dominio>/<entidad>/<accion>.json` |
| Metadata | `common/metadata/bioenlace/<dominio>/…` · `common/metadata/bioenlace/platform/<área>/…` |
| Tests | `common/tests/unit/<dominio>/…` · `common/tests/unit/platform/<área>/…` |

Dos reglas, en este orden:

1. **Si pertenece a un dominio, espeja.** Vive bajo la carpeta de ese dominio, con la misma grafía, en todas las capas.
2. **Lo transversal no se disfraza de dominio.** Autenticación, asistente, notificaciones, infra de apps: no se les inventa un dominio. En `controllers/` quedan en la raíz de la capa; en `metadata/` y `tests/`, donde el volumen lo justifica, se agrupan bajo `platform/` — que ocupa la misma posición que un dominio, no un eje aparte.

Así la pregunta "¿esto es dominio o plataforma?" se responde mirando, no recordando.

## 2. Conjunto de dominios

Fuente de verdad: las carpetas de `common/components/Domain/`.

`clinical` · `content` · `geo` · `integrations` · `organization` · `person` · `programs` · `scheduling` · `terminology`

Crear una carpeta ahí = declarar un dominio. No hay lista paralela que actualizar.

`geo` y `programs` los declaró la Fase 2, al quedar en evidencia que ya tenían dueño propio y estaban dispersos: los servicios de geo vivían repartidos entre `Domain/Person/` y `Domain/Organization/`, y el padrón/nomenclador de los programas de salud no encajaba en perfil, oferta ni acto codificado. Cada uno lleva README con su límite.

El eje ya está decidido en [`common-components-organizacion.mdc`](../../../../.cursor/rules/common-components-organizacion.mdc): `Platform/` (motores: Assistant, Core, Ai, Ui, Infra, Legacy) vs `Domain/` (rubro salud). Este plan **no inventa el eje**: corrige el árbol que se desvió de esa regla y lo extiende a las capas que todavía no la siguen.

Correcciones de grafía (una sola forma por dominio):

| Hoy | Objetivo |
|-----|----------|
| `views/json/persona/` | `views/json/person/` |
| `models/Integration/` | `models/Integrations/` |
| `views/json/core/`, `views/json/common/` | `views/json/platform/` |
| `components/Ai/`, `components/Core/` | `components/Platform/Ai/`, `components/Platform/Core/` — ya lo pide la regla |
| `components/Domain/Platform/` | `components/Platform/…` (no puede haber plataforma dentro de un dominio) |

## 3. Excepciones declaradas

No se reorganizan, y el motivo se documenta una vez:

| Qué | Por qué |
|-----|---------|
| `common/config/` | Arranque de Yii (DB, secretos, params) |
| `common/migrations/` | El esquema SQL es un namespace único; `turnos` no se renombra por vivir en `scheduling` |
| `common/tests/_bootstrap.php`, suites | Infra de test |
| `common/web/template/` | Assets de terceros |

## 4. Criterio de asignación de dominio

El dominio se decide por **quién persiste / quién es dueño**, no por el nombre del archivo. Evidencia práctica: qué servicios importa.

| Duda frecuente | Criterio |
|----------------|----------|
| `person` vs `clinical` | `person` = **perfil** de cualquier rol (paciente, staff, profesional): identidad, registro, representación, contexto, ventanilla. `clinical` = lo específico de **salud del paciente** (HC, encounter, captura). Por eso `PersonaController` (incl. signos vitales) queda en `person` y `PacientesController` va a `clinical` |
| `organization` vs `scheduling` | `organization` = **oferta y estructura** del efector (servicios, PES, horarios, sesión operativa, licencia). `scheduling` = **agenda y turnos** que consumen esa oferta |
| Motor vs dominio | Si el endpoint es del rubro salud, va al dominio; el motor genérico que usa (STT, UI JSON, DataAccess) se queda en `Platform/` |

## 5. Modelos: dueño y dependencias

Un modelo vive en el dominio que **lo persiste**. Los demás dominios entran por servicio o interfaz, no leyendo el AR ajeno. Las carpetas actuales que no son dominios (`busquedas`, `file`, `forms`, `fullcalendar`, `rbac`, `search`, `sumar`) se reparten entre el dominio dueño y `models/Platform/`.

Los `*Input` de captura clínica siguen siendo el borde de integridad (`rules()`); solo cambia su ubicación.

## 6. URLs y RBAC: por qué esto es de bajo riesgo

`BioenlaceApiAccessControl::beforeAction()` pide las rutas a chequear a `ApiRoutePermissionResolver::checkedRoutesForAction($pathInfo, $action->uniqueId)`, que devuelve **dos**: la derivada del path HTTP público y la derivada del route del controller. El chequeo pasa si **alguna** está concedida.

Ese doble chequeo era la red de seguridad prevista, pero al implementar la Fase 1 resultó innecesaria: `urlManager` tiene catch-all genéricos (`GET|POST api/<version>/<controller>/<action>`), así que reapuntar reglas obligaba a enumerar todos los endpoints. En su lugar, [`DomainControllerMap`](../../../frontend/modules/api/v1/DomainControllerMap.php) registra cada controller de `controllers/<dominio>/` en el `controllerMap` del módulo con su **id público sin dominio**.

Consecuencia: mover `TurnosController` a `controllers/scheduling/` **no cambia** el `uniqueId` (`v1/turnos/crear-como-paciente`) ni la URL ni la ruta RBAC. No hay reseed de permisos y no se toca ninguna regla de `urlManager`. El único mapa a mano que queda en config son los alias cuyo id público no coincide con la clase (`servicio-teleconsulta`, `whatsapp`, `consulta-chat`).

Poner el dominio en la URL pública (`/api/v1/scheduling/turnos/...`) queda como decisión aparte: hoy solo `clinical` lo hace, y unificarlo toca clientes móviles y seeds de permisos.

## 7. Muerte de los mapas intermedios

Cuando todos los controllers llevan su dominio en el namespace, el dominio de una entidad se **deriva** y estos dejan de tener función:

- `UiJsonDomainMetadata::ENTITY_DOMAINS` (24 entradas)
- `UiJsonDomainMetadata::CLINICAL_PREFIX` + la rama especial en `UiJsonDomain::parseActionId()` + las dos regex de `parseApiV1UiRoute()`
- Los prefijos `clinical.` / `scheduling.` / `organization.` escritos a mano en `product-registries.php` (los aporta la carpeta del fragmento)

## 8. Catálogos: invertir la dirección, no generar archivos

**No** se generan YAML en deploy. Dos verdades en el repo (fuente + generado) es más carga, divergen dev y prod, y choca con la regla de que los prompts los edita solo el humano.

La dirección correcta ya existe en el código: `PreprocessTagVocabularyCatalog` **compone en runtime** los tags desde los triggers del smart-catalog. Se replica ese patrón:

| Pieza | Dónde vive |
|-------|-----------|
| **Ids** de entidades resolubles | Los declara cada dominio (fragmento de registry en su carpeta) |
| **Texto** para la IA | YAML del catálogo, indexado por esos ids |
| Unión para el prompt | Loader PHP (`listForPrompt()`) |
| Cierre del círculo | Test: id sin texto → falla; texto sin id → falla |

Generar artefactos solo se justifica para consumidores que no ejecutan PHP (constantes Flutter, JSON Schema externo, tablas de docs), y esos van a una ubicación marcada como generada, nunca dentro de `metadata/bioenlace/`.

## 9. Área = carpeta del intent

Si los intents del asistente viven bajo `metadata/bioenlace/<dominio>/intents/`, el área **es** la carpeta:

- desaparece el campo `his_areas` (hoy declarado en 4 de ~55 intents)
- `context-his-areas.yaml` queda solo con textos, validados contra los dominios que tienen intents
- se habilita dejar de pedirle `context_areas` a la 1ª IA y derivarlas del match del smart-catalog → intent → carpeta, lo que elimina el reconcile de `clinical_record` en síntomas

Un intent que cruza dominios (`atencion.necesito-atencion` toca scheduling y clinical) se archiva en el dominio que **persiste** el resultado.

## 10. Nomenclatura: `Consulta` → `Encounter`

Misma idea que espejar el dominio, aplicada a una palabra: `Consulta` se usaba para la **atención médica** y chocaba con "consulta" en el sentido de *pregunta*. La atención médica es `Encounter`.

Hay **94 archivos PHP** con `Consulta` en el nombre, en familias que no se resuelven igual:

| Familia | Archivos (aprox.) | Criterio |
|---------|-------------------|----------|
| Tablas hijas del encounter (`ConsultaMedicamentos`, `ConsultaIndicaciones`, `ConsultaPracticas`, `DiagnosticoConsulta`, …) | ~25 modelos | Renombrar clase a `Encounter*`; **la tabla no se renombra** (ya hay traits legacy: `EncounterIdLegacyConsultaColumnTrait`, `LegacyConsultaIdAsEncounterFkTrait`) |
| `ConsultaAsync*` (bandeja, chat, lifecycle, push) | 24 | Decidir si `Async` sigue siendo el nombre de producto o pasa a `EncounterAsync*` |
| `ConsultasSeguimiento*` | 9 | Es el producto "Control/Seguimiento"; probablemente se queda |
| `Teleconsulta*` | 10 | Término legítimo de telemedicina, **no** es el uso confuso; se queda |
| `AsistenteConsultasQa*` | 3 | Acá "consultas" = **preguntas** del usuario; si molesta, renombrar a `Preguntas`/`Qa` |

**Alcance en este plan:** solo `ConsultaChatController` → `EncounterChatController` (Fase 1) y los modelos que se muevan en Fase 2 se renombran al pasar. El barrido completo es un programa aparte, porque toca migraciones, RBAC, metadata y clientes.

Nota: el plan [`captura-actor-enfermeria`](../captura-actor-enfermeria/README.md) ya está eliminando el alias `ConsultasConfiguracion` → `EncounterDefinition`; conviene no cruzarse con esos archivos.

## 11. Orden y criterio de corte

Cada fase deja el árbol más parejo y **no depende de las siguientes** para tener valor. Las fases 1–3 son mecánicas y de bajo riesgo; 4–5 cambian comportamiento del asistente y llevan sus propios tests de QA.
