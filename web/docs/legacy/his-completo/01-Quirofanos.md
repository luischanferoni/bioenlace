# Quirófanos (cirugía)

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **1/4** (Prototipo / Ausente funcional específico).
- **Evidencia directa de módulo de quirófanos**: no aparece un paquete claro de modelos/controladores del tipo “Quirófano / Cirugía / Parte quirúrgico / Anestesia / Programación de sala”.
- **Apoyos reutilizables existentes** (que sirven como base, pero no reemplazan el módulo quirúrgico):
  - **Internación + prácticas + consumos**: `web/common/models/SegNivelInternacion*.php` (prácticas, consumos, medicamentos, suministros).
  - **Nomencladores/prácticas**: prácticas en `ConsultaPracticas*.php` y nomencladores (p.ej. SUMAR).
  - **Infraestructura** (parcial): `web/common/models/InfraestructuraPiso.php`, `InfraestructuraCama.php` (camas/pisos; quirófanos suelen requerir salas, turnos, recursos, equipamiento).
  - **Orquestador de intents**: se podrían sumar intents quirúrgicos con `ActionMappingService` + handler específico.

## Qué falta para un HIS “completo” en quirófanos

- **Planificación quirúrgica**
  - Lista de espera (electivas), priorización, preoperatorio.
  - Programación por sala, equipo quirúrgico, tiempos estimados, recursos.
- **Intraoperatorio**
  - Parte anestésico y parte quirúrgico estructurados.
  - Checklist (OMS/WHO), eventos, incidentes, tiempos (entrada, incisión, cierre, salida).
- **Postoperatorio**
  - Recuperación, evolución inmediata, indicaciones, pases (a sala/UCI).
- **Materiales y trazabilidad**
  - Consumo de insumos, implantes, lotes/series, trazabilidad (clave en cirugía).
  - Integración con farmacia/depósitos/logística.
- **Integración clínica**
  - Enlace con internación (episodio), diagnósticos, prácticas codificadas, facturación.

## Próximos pasos recomendados (técnico/producto)

- **Definir el modelo de dominio mínimo** (tablas/AR):
  - `QuirofanoSala`, `Cirugia`, `CirugiaEquipo`, `CirugiaTiempo`, `CirugiaChecklist`, `CirugiaInsumo`, `CirugiaImplante`, `ParteAnestesico`, `ParteQuirurgico`.
- **Resolver la agenda quirúrgica**
  - Integrar con `Turno`/agenda o crear agenda específica por sala.
- **Integración con internación**
  - Enlace 1:N `SegNivelInternacion` → `Cirugia` (o episodio quirúrgico).
- **Exposición API + intents**
  - Endpoints en `frontend/modules/api/v1/controllers` y `IntentHandler` `QuirofanosHandler`.

