# Niveles de carga (audio/texto) y persistencia de "unmapped"

## Objetivo
Unificar criterios para organizar la interpretacion y el guardado (CRUD/registro clinico) cuando el sistema recibe audio/texto.

Definimos dos niveles:
- Nivel 1: carga clinica multi-modelo basada en `ConsultasConfiguracion` (multi-categoria).
- Nivel 2: carga para crear/actualizar un destino (single accion o single entidad, segun el caso) usando interfaz conversacional (chat/intents) + formulario precargado.

Nota de terminologia:
- En este sistema, **"Consulta" (en mayusculas, como concepto del dominio)** refiere a *carga clinica multimodelo* (no a "preguntar" por chat).

Adicionalmente definimos como persistir el contenido que "no mapea" (unmappable/unmapped) en un almacenamiento unico y consistente para ambos niveles.

## Definiciones
### Nivel 1 (Consulta clinica multi-modelo)
Una carga corresponde a Nivel 1 cuando el flujo utiliza una `consulta` configurada y su persistencia se basa en `ConsultasConfiguracion`:
- El motor de interpretacion y guardado es `ConsultaProcesamientoService`.
- La configuracion se construye desde `ConsultasConfiguracion::pasos_json`.
- Durante el guardado, se recorren categorias/modelos y se persisten entidades relacionadas (diagnosticos, sintomas, practicas, medicamentos, etc.).

Referencias en el repo:
- `web/frontend/modules/api/v1/controllers/ConsultaController.php` (endpoints `consulta/analizar` y `consulta/guardar`)
- `web/common/components/Services/Consulta/ConsultaProcesamientoService.php` (interpretacion y persistencia multi-modelo)
- `web/common/models/ConsultasConfiguracion.php` (contrato `pasos_json`)

Ejemplos:
- Medico (o enfermero/autorizado) carga "evolucion" de internacion: puede implicar diagnosticos/medicamentos/practicas. Mientras se persista mediante `ConsultaProcesamientoService` y `ConsultasConfiguracion`, es Nivel 1.

### Nivel 2 (Chat/intents + formulario precargado)
Una carga corresponde a Nivel 2 cuando:
- El sistema inicia con un chat generico y por intents determina una accion (ej: "necesito un turno ...").
- El output del intent dispara una UI (boton + formulario precargado) para que el usuario complete los campos.
- La persistencia real de la entidad ocurre cuando el usuario envia el formulario (o cuando el sistema guarda el estado/contenido del chat que representa ese borrador, ver politica A abajo).

Referencias en el repo:
- `web/frontend/views/site/asistente.php` (chat del asistente para CRUD guiado)
- `web/frontend/modules/api/v1/controllers/ChatController.php` (`chat/recibir`) (si el flujo usa dialogos/mensajes persistidos)
- `web/common/components/Chatbot/ConsultaIntentRouter.php` (orquestacion: clasifica, extrae parametros, enruta a handler)
- `web/common/components/Chatbot/IntentHandlers/Handlers/TurnosHandler.php` (ejemplo de handler de intents)

Nota importante sobre Turnos (ejemplo):
- En "Turnos", el intent devuelve un formulario precargado con el servicio y el resto de campos a cargar.
- No necesariamente existe el registro final `turnos` en el momento inicial del intent; puede existir el registro de chat/dialogo y/o el estado parcial.

## Regla de clasificacion (checklist)
Usar el siguiente criterio para decidir el nivel del flujo:

1. Si el guardado clinico multi-modelo se realiza via `ConsultaProcesamientoService` y `ConsultasConfiguracion` => Nivel 1.
2. Si el flujo se decide por intents (orquestador) y el guardado final es de un destino single-entity/single-accion (no multimodelo via `ConsultaProcesamientoService`) => Nivel 2.
3. El rol (paciente/medico/enfermero) **no define** el nivel; define permisos y que flujos estan disponibles.
4. Audio/texto no define el nivel; solo define el medio de entrada. El nivel lo define el motor de persistencia que termina ejecutandose.

## Definicion de "unmapped"
Unmapped es cualquier fragmento de informacion (texto/audio ya transcripto o JSON extraido) que:
- no puede mapearse a un destino estructurado esperado para esa carga (atributos del modelo o categorias/modelos del contrato de la `consulta`),
- o no encaja en el set de campos definidos por la configuracion/contrato del flujo.

## Persistencia de unmapped: tabla unica
En lugar de agregar columnas a cada tabla, se propone una tabla unica.

### Propuesta: `ai_unmapped_data`
Campos minimos:
- `id` (PK)
- `level` (int: 1 o 2)
- `scope_type` (string: tipo de destino persistido; ejemplo: `diagnostico_consultas`, `interaccion_chat_clinico`, `interaccion_motivos_consulta`, `turnos`, `asistente_interaccion`, `asistente_conversacion`)
- `scope_id` (string/int: id del registro destino persistido)
- `source` (json o string; ejemplo: `intent:crear_turno`, `categoria:Diagnosticos`, `modelo:ConsultaDiagnosticos`, `tab_id`, etc.)
- `raw` (json o text): payload/resto no mapeado
- `reason` (json o text): por que no mapeo (ej: "no existe atributo", "no existe modelo en pasos_json", "no hay match en contrato", "destino no disponible", etc.)
- `metadata` (json o text opcional): confidence, method (rules/ai), parameters_extracted, etc.
- `created_at` (timestamp)

### Regla de "scope" (destino)
Cada fila en `ai_unmapped_data` debe referenciar el "registro destino" que efectivamente existe en BD cuando se decide guardar unmapped.

## Politica A (recomendada): guardar unmapped solo cuando el destino este persistido
La politica A se aplica a ambos niveles:
- No guardar unmapped en una fase donde el destino (registro de BD asociado) aun no existe.
- Guardar unmapped cuando el sistema ya persistio el registro base que representa esa carga (o el registro clinico creado/actualizado).

### Como aplicar Politica A en Nivel 1
En Nivel 1, el destino persistido existe porque `ConsultaProcesamientoService::guardar()` crea/actualiza entidades clinicas.

Regla:
- Cuando el mapper intente crear/actualizar una entidad clinica (diagnostico/sintoma/practica/medicamento/motivo, etc.) y no pueda mapear parte del contenido:
  - insertar una fila en `ai_unmapped_data` con:
    - `level = 1`
    - `scope_type` = tipo de la entidad clinica destino (o `consultas` como anchor si aplica)
    - `scope_id` = id del destino clinico persistido (o `id_consulta` como anchor)
    - `raw` y `reason` segun el resto no mapeado

Fallback:
- Si no hay un sub-destino clinico determinado (no existe relacion/categoria/modelo para esa parte), usar `scope_type = consultas` y `scope_id = id_consulta`.

### Como aplicar Politica A en Nivel 2 (Turnos y cualquier entity guiada por formulario)
En Nivel 2, puede ocurrir que:
- se persista un registro de chat/interacción/estado (ej. `asistente_interaccion`, `interaccion_chat_clinico`), o
- no persista chat como entidad (ej. un CRUD guiado por “acciones” donde el guardado ocurre en el submit del formulario).

Regla (robusta):
- El "scope" principal para guardar unmapped en Nivel 2 es el primer registro destino persistido disponible en el flujo:
  - Si existe chat/interacción/estado persistido, usarlo: `asistente_interaccion` (ej: `common\models\AsistenteInteraccion`) o `interaccion_chat_clinico` / `interaccion_motivos_consulta`.
  - Si no existe un registro chat persistido en esa etapa, usar el destino final una vez que se guarda (ej: `turnos`, `cirugia` u otro registro destino).

Referencias en el repo:
- `web/frontend/modules/api/v1/controllers/ChatController.php` (crea/usa `Dialogo` y guarda mensajes)
- `web/common/models/AsistenteConversacion.php` (`asistente_conversacion`)
- `web/common/models/AsistenteInteraccion.php` (`asistente_interaccion`)
- `web/common/models/ConsultaChatMessage.php` y `web/common/models/ConsultaMotivosMessage.php` (chat medico y motivos)

Cuando la entidad final se cree (en el submit/guardar del formulario):
- Se puede insertar adicionalmente una fila con `scope_type`/`scope_id` apuntando al registro final (opcional pero recomendado para trazabilidad).

En esta doc se fija lo minimo:
- Asegurar que siempre exista un destino persistido para registrar unmapped: en Nivel 2, ese destino minimo es el registro de mensaje/chat (o el dialogo, si es donde guardan el estado del borrador).

## Reglas para "no mezclar" responsabilidades
1. Nivel 1: la logica pesada de interpretacion + persistencia se implementa en `ConsultaProcesamientoService` y se basa en `ConsultasConfiguracion`.
2. Nivel 2: la logica conversacional de intents y el armado de formulario precargado se implementa en handlers (ej: `TurnosHandler`) y en el endpoint UI JSON (`/api/v1/ui/...`) usando `UiDefinitionTemplateManager`.
3. El unmapped se guarda en `ai_unmapped_data`, pero el origen (que parte no mapea) se detecta en:
   - Nivel 1: en el mapper de `guardarDatosCategoria` / `mapearDatosAModelo`.
   - Nivel 2: en el parser que arma params del intent y/o en el armado del formulario precargado (segun que "resto" no se convierte en campos esperados).

## Casos de uso (resumen)
1. Paciente manda audio/texto: chat generico (Nivel 2) -> intent determina "necesito un turno ..." -> app arma formulario precargado.
   - El unmapped se registra contra el registro de mensaje/chat (Politica A).
2. Medico (o rol habilitado) carga una consulta clinica configurada (Nivel 1) -> se interpreta con IA -> se persisten entidades clinicas.
   - El unmapped se registra contra la entidad clinica destino que no pudo mapearse, con fallback a `consultas`.
3. Quirófano - "Redactar lo que pasó" (informe quirúrgico clínico):
   - Siempre cae en `@web/frontend/modules/api/v1/controllers/ConsultaController.php::actionGuardar` (persistencia via `ConsultaProcesamientoService`) => Nivel 1.
4. Quirófano - "Agendar cirugía":
   - El texto se usa para armar campos del destino de agenda/procedimiento y se guarda como registro destino => Nivel 2.
5. Evolucion en internacion:
   - Aunque "sea seguimiento", si termina persistiendo multi-modelo clinico via `ConsultaProcesamientoService` => Nivel 1.

## Recomendaciones practicas para el equipo
1. Unmapped debe incluir SIEMPRE:
   - `raw` (resto) y
   - `reason` (motivo de no mapeo).
2. En `source`, guardar el "contexto" minimo:
   - Nivel 1: categoria/modelo (los nombres usados por `ConsultasConfiguracion`).
   - Nivel 2: intent y parametros extraidos (si estan disponibles).
3. Mantener consistencia en `scope_type`:
  - usar los nombres de clase o tabla (ej: `diagnostico_consultas`, `consultas_medicamentos`, `interaccion_chat_clinico`) para que sea consultable.

