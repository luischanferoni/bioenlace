# Fase 03 — Metadata clínica humana

## Objetivo

Que secciones clínicas, tipos de episodio, triage y acciones permitidas lleguen del backend, de modo que agregar una especialidad no obligue a tocar clientes.

## Tareas

- Generalizar `EncounterDefinitionSpecialtyRegistry`: cada especialidad humana declara secciones, campos, unidades, orden y validaciones por metadata. Odontología y oftalmología son la primera prueba.
- API que entrega secciones clínicas con campos (clave, etiqueta, valor, unidad, formato).
- Tipos de episodio (turno, cirugía, guardia, internación) como datos con código, etiqueta y capacidades.
- Triage: niveles, etiquetas e intents desde el backend; se elimina la paleta fija del cliente.
- Acciones permitidas por episodio y por rol desde el backend.
- Ámbito de atención del profesional (humano o veterinario) informado en el inicio de sesión.
- Reemplazar los modelos fijos de signos vitales, evolución de internación y agenda de cirugía por el modelo genérico de secciones.

## Avance

El catálogo público, las opciones del wizard y la respuesta al establecer la sesión incluyen `capabilities` junto con `code` y `label`. Las clases FHIR que no están activas para selección no reciben capacidades de producto. Personal de Salud resuelve la metadata del catálogo y usa las capacidades para elegir contenido, acciones, filtros y polling de la pantalla inicial, sin ramificar por código de clase. Las respuestas de los endpoints de odontología y oftalmología incluyen `specialtyMetadata`, con secciones, campos, formatos y unidades (dioptrías y grados para recetas); se conserva `enabledSpecialties` como lista de códigos. El catálogo Manchester entrega niveles, etiqueta, color, intent e identifica el nivel predeterminado; los formularios web y móvil usan ese catálogo para presentar y seleccionar las opciones, y el tablero recibe metadata por nivel para pintar sus filas. La urgencia por demora de triage se publica aparte mediante un intent del SLA, sin confundirla con el nivel Manchester. Los banners de guardia e internación entregan su etiqueta e intent visual desde el backend. En web y Personal de Salud, el timeline consume filtros, etiquetas e intents de items y signos vitales desde el backend; los chips de métricas recientes y series históricas también respetan su intent. Las acciones de episodio publican `kind`, ruta, método e intención visual: el cliente abre triage con su UI nativa para `native_triage` y reserva `ui_json` para formularios JSON.

La documentación clínica del profesional ahora publica secciones genéricas con `key`, `label` y `fields` (`key`, `label`, `value`, `unit`, `format`), además de mantener `titulo` e `items` para clientes anteriores. Web y Personal de Salud prefieren los campos estructurados y conservan lectura de la forma anterior; el test verifica ambos contratos.

La serie de signos vitales publica también `metric_metadata` completo aunque el episodio todavía no tenga puntos, conservando `series` y `ultimos` para los clientes actuales. Web y Personal de Salud usan esa metadata como respaldo de etiqueta, unidad e intent cuando los datos de una serie o último valor no los repiten. El mapeo de fuentes y métricas sigue siendo explícito; esto mejora la disponibilidad de metadata, pero no completa la migración a secciones genéricas.

La metadata de especialidades publica validaciones alternativas mediante `validation.type = at_least_one` y las claves de los campos participantes. El registro reutiliza las claves de los contratos de captura: odontología acepta tipo o código, y estudios oftalmológicos acepta código o informe. No se convierten en booleanos `required`, porque eso sería más restrictivo que las reglas reales.

Las acciones actuales del banner ya declaran presentación y se condicionan al estado del episodio, pero todavía no representan permisos por rol. No se considera cumplida la tarea de acciones permitidas por episodio y rol hasta que esa política se publique de forma autorizada desde el backend.

## Criterio de salida

Una especialidad nueva se agrega con metadata y un servicio, sin cambios en móvil ni web. El timeline del piloto se pinta solo con secciones genéricas.
