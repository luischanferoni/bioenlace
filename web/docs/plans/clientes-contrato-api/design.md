# Design — Clientes delgados y contrato de API

## Decisiones

| Tema | Decisión |
|------|----------|
| Fuente de verdad | El backend define textos, estados, vocabulario, esquema clínico y acciones permitidas |
| Clientes | Renderizan, navegan y acceden a hardware. Nunca traducen un código a texto ni comparan por texto de mensaje |
| Respuesta de error | Objeto con `code`, `message`, `intent`, `retryable` y `field_errors` opcional. El cliente muestra `message` tal cual |
| Dominio y mensajes | Los servicios lanzan excepciones con código. La redacción vive en un catálogo YAML con un loader único (regla source-of-truth) |
| Estados | Todo estado visible llega como `code`, `label`, `intent`. Aplica a capturas, episodios, turnos y tareas |
| Textos propios del cliente | Solo infraestructura: sin red, tiempo agotado, permiso del sistema denegado, error inesperado. Un archivo por cliente |
| Terminología | «Encounter» en código, rutas, clases y tablas. «Consulta» solo puede aparecer en copy español que defina el backend |
| Sin legacy | Rutas, clases y tablas viejas se borran. Despliegue coordinado de backend, web y apps |
| Apps móviles | Dos apps (Paciente y Personal de Salud), shells finos sobre `mobile/packages/shared` |
| Especialidad humana | Cada especialidad declara secciones, campos y validaciones por metadata. Odontología y oftalmología ya siguen este patrón y pasan a ser una definición más |
| Datos clínicos | Estructura genérica de secciones con campos (clave, etiqueta, valor, unidad, formato). Una sola vista las renderiza |
| Tipos de episodio | Son datos con código, etiqueta y capacidades. El cliente decide la UI por capacidades, no por el código |
| Triage | Niveles y colores los envía el backend como intents y tokens de diseño |
| Veterinaria | Vertical separada con dominio, API, pantallas y vocabulario propios. Se acepta duplicación. Lo compartido es solo infraestructura neutra |
| Qué se comparte con veterinaria | Formato de respuesta, cliente HTTP, componentes de UI, tokens, captura de audio y STT, cola offline |
| Qué NO se comparte | Modelos y pantallas clínicas humanas |
| Sujeto activo (app Paciente) | La representación elegida (persona o mascota) viaja en cada request. El backend decide qué acciones existen para ese tipo de sujeto |
| Rol del profesional | El backend informa el ámbito de atención del profesional (humano o veterinario) al iniciar sesión. El cliente elige el módulo por registro, sin cadenas de if |

## Estructura prevista del cliente Personal de Salud

Un registro de módulos por ámbito de atención. El módulo humano conserva lo actual, reorganizado. El módulo veterinario nace con carpetas propias (pantallas, modelos, servicios) aunque al principio tenga poco contenido, para crecer sin refactor. Los textos que obligatoriamente viven en cliente se ubican junto al módulo al que pertenecen.

## Estructura prevista en backend para veterinaria

Un dominio propio bajo Domain, separado de Clinical humano. Contiene sujeto animal, vínculo con tutor, catálogos de especie y raza, y la definición de encounter veterinario sobre el mismo motor de captura. Sin controllers ni rutas hasta que el MVP lo requiera. La fase 7 define el esqueleto y las reglas de frontera, no los endpoints.

## Garantías de CI

Contadores con tope descendente («ratchet») para: mensajes literales en controllers y servicios, textos literales en cliente, «consulta» en código, HTML concatenado en JS, ramas por tipo de episodio. El tope solo baja. Cuando llega a cero pasa a ser prohibición.

## Alternativas descartadas

- Generalizar el sujeto humano para incluir animales con un tipo de sujeto en todo el modelo clínico: aumenta la carga cognitiva y obliga a cada pantalla a ramificar. Se prefiere duplicar pantallas.
- Mantener alias de rutas y clases viejas: conserva las dos nomenclaturas y retrasa el cierre.
- Mensajes en el cliente con código de error mapeado a texto: reintroduce la desincronización.
- Una sola app móvil con perfil: se mantienen las dos apps actuales.
- Vocabulario por institución: no hay necesidad hoy; un glosario global alcanza.

## Decisiones sobre veterinaria y vocabulario

| Tema | Decisión |
|------|----------|
| Animal | Entidad propia (no es una persona ni una variante de ella), vinculada a un tutor. Se llama Animal y no Mascota, porque no todo animal atendido es una mascota |
| Alta de animal | Alcance básico. El formulario de representación pide los datos mínimos del animal al crearlo |
| Efectores | Solo efectores privados con el servicio de veterinaria. A partir de ahí reutilizan servicio, agenda y turnos del modelo existente. Si más adelante algo debe separarse, se decide en ese momento (aún no está diseñado) |
| Profesional veterinario | Mismo alta de personal y mismos roles. Ejemplo: rol Médico con servicio Veterinaria. El ámbito de atención se deduce del servicio |
| Motor de captura | Se reutiliza con una definición propia por especialidad. Los prompts cambian el sujeto (persona o animal) y agregan pocas instrucciones para animales |
| Término visible | «Consulta» en lo que ve el usuario; «encounter» en todo lo interno. «Atención» se reserva para otro concepto |

## Hallazgos que condicionan el diseño

- El motor de captura no es tan genérico como los prompts: el servicio de extracción trabaja con `subjectPersonaId`, guarda `id_persona` en el resultado y en la caché, y renderiza la vista del paciente. El sujeto del encounter debe pasar a ser una referencia genérica (tipo y identificador) antes de sumar animales.
- Los efectores ya tienen atributo de tipo público o privado, lo que habilita el recorte a efectores privados.
- El término «consulta» visible convive con «encounter» interno: el glosario debe fijar esa frontera y las guardas de CI deben aplicarla solo al código, no al copy.
