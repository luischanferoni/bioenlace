# Implementación del Chat Híbrido para Consultas Médicas

## Descripción General

El chat híbrido combina procesamiento automático de lenguaje médico, aprendizaje asistido y validación humana. Cada análisis de consulta pasa por un pipeline que corrige ortografía, expande abreviaturas, estructura información por categorías clínicas, calcula similitudes semánticas y propone códigos SNOMED CT cuando corresponde.

El flujo completo está orquestado por `frontend/web/js/chat-inteligente.js` (interfaz) y `common/components/ProcesadorTextoMedico.php` (backend), integrando componentes auxiliares como `IAManager` (corrección con IA local), `CodificadorSnomedIA`, `EmbeddingsManager` y la base de datos semántica de abreviaturas.

## Flujo General del Chat

1. **Entrada del usuario**: El médico redacta la consulta en el textarea de `timeline.php` y presiona "Analizar".
2. **Preparación para IA**: `ProcesadorTextoMedico::prepararParaIA()` ejecuta corrección completa con IA local:
   - **Corrección con IA local** (`IAManager::corregirTextoCompletoConIA()`): usa Llama 3.1 70B Instruct para corrección ortográfica completa del texto médico.
   - Las correcciones se aplican automáticamente sin requerir validación del médico.
3. **Expansión y metadatos**: `ProcesadorTextoMedico::expandirAbreviaturas()` genera texto final; `generarMetadatos()` produce estadísticas de procesamiento (longitudes, frecuencias, categorías detectadas, abreviaturas no reconocidas) para dashboards y trazabilidad.
4. **Estructuración por categorías clínicas**: `ConsultaController::analizarConsultaConIA()` genera un prompt especializado y consume la respuesta de IA con el JSON estructurado (motivo, antecedentes, evolución, indicadores, diagnósticos, tratamientos, seguimiento, alertas). Estas secciones se cruzan con `ConsultasConfiguracion` y se visualizan mediante `generateAnalysisHtml()` en el panel de análisis.
5. **Similitudes Semanticas**: llamada al LLM para obtener embeddings (palabras iguales semánticamente) de los terminos para ser luego usados en el paso siguiente, en la búsqueda de codigos snomed.
6. **Codificación SNOMED**: `CodificadorSnomedIA::codificarDatos()` recibe conceptos estructurados y sugiere códigos SNOMED utilizando matching directo, fuzzy y semántico.
7. **Respuesta al usuario**: `chat-inteligente.js` renderiza observaciones, alertas y sugerencias. Las correcciones se aplican automáticamente.

## Flujo de Abreviaturas Médicas

- `AbreviaturasMedicas::expandirAbreviaturasConMedico()` prioriza expansiones según especialidad y uso histórico (`abreviaturas_rrhh`).
- `ProcesadorTextoMedico::detectarAbreviaturasNoReconocidas()` identifica abreviaturas nuevas con ventanas de contexto, bigramas y score médico.
- Las nuevas abreviaturas se guardan en `abreviaturas_medicas` con `origen = USUARIO` o `LLM`, quedando listas para aprobación.
- Metadatos (`generarMetadatos`) resumen número de abreviaturas encontradas, categorías y no reconocidas para el panel de análisis.
- Las abreviaturas se expanden automáticamente sin requerir intervención del médico.

## Correcciones Ortográficas y de Tipeo

- **Corrección con IA local**: `IAManager::corregirTextoCompletoConIA()` usa Llama 3.1 70B Instruct para corrección completa del texto médico.
- **Modelo**: Llama 3.1 70B Instruct con temperatura 0.0 para máxima precisión.
- **Procesamiento**: El texto completo se corrige de una vez, manteniendo abreviaturas médicas válidas.
- **Automático**: Las correcciones se aplican automáticamente sin requerir validación del médico.
- **Persistencia**: Los cambios se registran en `ProcesadorTextoMedico::guardarInfoCorrecciones` para estadísticas.

## Estructuración por Categorías Clínicas

- `ConsultaController::analizarConsultaConIA()` construye un prompt dinámico y llama a la IA para obtener un JSON higienizado con secciones clínicas (antecedentes, motivo, evolución, indicadores, diagnósticos, tratamientos, seguimiento, alertas, datos faltantes).
- `generateAnalysisHtml()` combina ese JSON con la configuración de `ConsultasConfiguracion` para marcar campos obligatorios, detectar faltantes y renderizar el panel de revisión.
- Los dashboards (`frontend/views/paciente/_resultado_analisis_consulta.php`) presentan la información estructurada junto con códigos SNOMED sugeridos cuando están disponibles.

## Similitudes Semánticas

- `EmbeddingsManager` genera embeddings especializados (PlanTL-GOB-ES y modelos clínicos) y calcula similitud coseno (`calcularSimilitudCoseno`).
- `CodificadorSnomedIA::buscarCodigoSemantico()` combina embeddings del término detectado con catálogos SNOMED por categoría.
- Se manejan umbrales de confianza (`CONFIANZA_SEMANTICA`, `SIMILITUD_MINIMA`) y se registran logs para auditoría (`	Yii::info`).
- El pipeline semántico amplía vocabularios y acelera la identificación de conceptos con bajo match léxico.

## Codificación Automática SNOMED CT

- `CodificadorSnomedIA` aplica tres estrategias: matching exacto, fuzzy y semántico.
- `CodificarDatos()` recorre segmentos de la consulta estructurada (motivo, antecedentes, diagnósticos, etc.) generando sugerencias por concepto.
- La interfaz muestra código, descripción y método (`frontend/views/paciente/_resultado_analisis_consulta.php`). Conceptos dudosos quedan marcados como "requieren validación" para revisión.

## Componentes Clave

- **Frontend**
  - `timeline.php`: UI del chat y panel de análisis.
  - `chat-inteligente.js`: coordinación de UI, llamadas a IA y render de resultados.

- **Backend**
  - `ProcesadorTextoMedico`: pipeline principal (limpieza, corrección con IA, expansión de abreviaturas, metadatos).
  - `IAManager`: interacción con modelos LLM locales (Ollama) para corrección ortográfica completa.
  - `CodificadorSnomedIA`: codificación SNOMED.
  - `EmbeddingsManager`: gestión de embeddings y similitud.
  - `AbreviaturasMedicas` / `abreviaturas_rrhh`: base de datos semántica y preferencias por médico.

## Próximos Pasos

1. Optimizar el rendimiento del modelo Llama 3.1 70B para procesamiento más rápido.
2. Parametrizar umbrales de similitud y confianza desde configuración por servicio.
3. Exponer panel de auditoría con logs de decisiones de IA/SNOMED.
4. Evaluar modelos alternativos para mejorar aún más la precisión.
