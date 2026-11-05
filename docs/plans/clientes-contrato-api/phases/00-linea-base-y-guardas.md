# Fase 00 — Línea base y guardas

## Objetivo

Medir el estado actual con contadores reproducibles y bloquear que empeore mientras dura el programa.

## Tareas

- Script de auditoría que cuente, por carpeta: mensajes literales en controllers API y servicios de dominio; textos literales en Dart (`Text`, `SnackBar`, mensajes de aviso); HTML concatenado y textos literales en JS; lógica de request, rol o URL en vistas PHP; apariciones de «consulta» en código y rutas; ramas por tipo de episodio.
- Guardar los topes actuales en un archivo versionado. El tope solo puede bajar.
- Integrar el script en el flujo de CI y en un comando local.
- Definir la lista de excepciones permitidas (archivo de textos de infraestructura de cada cliente, tests y fixtures).

## Criterio de salida

El script corre en CI, falla si un contador sube y está documentado cómo bajar un tope.

## Estado

Parcial. No hay CI configurado en el repositorio, así que la guarda es un comando local. El script y el baseline existen, pero la verificación actual no pasa: `domain_literal_messages` marca 764 frente al tope 701. No se debe elevar el baseline para ocultar el exceso; la fase queda abierta hasta reducir el contador a su tope o menos.

- Script: `web/tools/audit-thin-clients.php`. Tope versionado: `web/tools/thin-clients-baseline.json`.
- Comandos: `composer audit:thin-clients` (verifica; sale con 1 si un contador sube), `composer audit:thin-clients:report` (contadores y archivos con más ocurrencias).
- Bajar un tope: tras una limpieza, `php tools/audit-thin-clients.php --write`. Nunca sube un valor; para el tope inicial se usa `--write --init`.
- Los contadores son por línea. Un texto partido en varias líneas (por ejemplo, un aviso con el literal en la línea siguiente) no se cuenta, por lo que los valores son una cota inferior.
- «consulta» en Dart se cuenta fuera de strings y comentarios (identificadores); el copy visible no cuenta.
- Excepción de textos de cliente: `user_friendly_error.dart` de shared, hasta que se reemplace por el archivo de infraestructura de la fase 5.

Topes iniciales: mensajes en controllers API 68; en dominio 702; textos literales en Dart 211; «consulta» en código Dart 394 y 10 archivos; ramas por episodio en Dart 10 y en JS 1; «consulta» en archivos PHP 60 y 25 rutas; HTML concatenado en JS 226; textos literales en JS 65; lógica en vistas 39.

Pendiente: conectar la verificación a CI cuando el repositorio tenga uno.

Actualización: `domain_literal_messages` volvió a 697 (tope) y el guard local sale con 0. Los mensajes de `ResolveTurno`, `EstablishVerifiedGuardianship` y `DesignatePatientDelegation` salieron a YAML por caso de uso, cargados con `DomainMessageCatalog` (`Platform/Core/Product`), con placeholders `{nombre}` y falla explícita ante claves inexistentes. El texto visible no cambió. Siguen siendo candidatos para bajar el tope: `SignUpInstitutionalEfector`, `ProfesionalEfectorServicioAgendaUiService`, `DemoSandboxClinicalSeedService` y `CareProtocolAdminService`. Bajar el tope con `--write` recién cuando haya margen real.
