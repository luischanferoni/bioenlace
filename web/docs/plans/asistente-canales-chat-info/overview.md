# Overview

## Problema

1. **Canales mal nombrados / mezclados.** `conversational` hoy es casi solo charla clínica + CTA `atencion.necesito-atencion`. `informational` a veces dump de artículo, a veces cae a conversational. No hay casa para ambigüedad ni para desvíos de dominio.
2. **PHP pisaba el canal de la IA** (`resolveUserGoal`, forzar operational por “turno”, historial clínico). Producto acordó: **el preprocess IA decide el canal**; como mucho se mejora el prompt. Sin fallback heurístico si la IA falla.
3. **Prompts dispersos** (`conversational-channel.yaml`, prompt embebido en PHP preprocess, `channel-copy.yaml`) sin carpeta clara ni prompts por canal (falta informational anclado a fuente).
4. **`info_content_article`** sirve artículos, pero: match frágil, dump sin IA, se consulta también desde conversational, no hay `intent_id`/CTA, visibilidad no alineada a RBAC del intent.
5. **Chat “plano”:** un solo historial por usuario+BOT; síntomas viejos contaminan follow-ups de producto; no hay detección de desvío de hilo ni score de certeza sobre la necesidad del usuario.
6. **`intent_semantics`** se usó como ficha de oferta para la IA; en el modelo destino la IA no elige intents — PHP matchea / adjunta botones. La ficha corta sobra o migra a BD junto al contenido.

## Objetivo

Un asistente paciente donde:

- Preprocess IA clasifica canal con **alcance positivo** (salud/gestiones del paciente autenticado o representación formal).
- Canales renombrados y con contrato claro + botones.
- Metadata de prompts centralizada.
- Contenido editorial ligado a intents, administrable (producto / provincia / efector), con CTA y RBAC coherente.
- Módulo de chat que entiende hilos, desvíos y certeza (preguntas libres ponderadas).

## Fuera de alcance de este plan

- Reescribir SubIntentEngine ni el catálogo completo de intents staff.
- Care pack: preguntas concretas por cohorte (siguen en su dominio); solo el **concepto** pre-consulta como artículo.
- WhatsApp-specific product (salvo reutilizar mismos canales).
- Volver a meter clasificación NL por IA entre `intent_id` (sigue keywords + desambiguación PHP).
