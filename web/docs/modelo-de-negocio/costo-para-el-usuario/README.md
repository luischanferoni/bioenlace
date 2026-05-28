# Costo para el usuario (paciente) — variables y sensibilidad

**Tipo:** modelo · experiencia paciente  
**Última actualización:** 2026-05-28

Este módulo no usa montos. Modela **qué variables suelen dominar** el costo total para el paciente y cómo calcular el **porcentaje** que representa cada variable sobre el total.

No es asesoramiento legal ni normativo. El objetivo es **comparar drivers** y diseñar UX/flows que reduzcan los más pesados.

## 1) Variables de costo (sin montos)

Definimos el costo total como:

\( C_{total} = C_{atencion} + C_{diagnostico} + C_{tratamiento} + C_{traslado} + C_{tiempo} + C_{administrativo} + C_{acompanante} + C_{estadia} + C_{oportunidad} \)

Donde:

- **C_atencion**: consulta (guardia/ambulatorio/teleconsulta), honorarios, copago/coseguro si aplica.
- **C_diagnostico**: laboratorio, imágenes, procedimientos diagnósticos (incluye repetición si hay duplicación).
- **C_tratamiento**: medicamentos Rx/OTC, insumos (curaciones), rehabilitación.
- **C_traslado**: transporte (ida y vuelta), combustible, peajes.
- **C_tiempo**: tiempo del paciente (y del acompañante) por viaje + espera + atención (pérdida de jornada).
- **C_administrativo**: autorizaciones, trámites, tickets, impresiones, llamadas, “idas y vueltas”.
- **C_acompanante**: costos del acompañante (si requiere).
- **C_estadia**: si hay que pernoctar (comida/alojamiento) por distancia o turnos.
- **C_oportunidad**: riesgo/costo de posponer (complicación por no atenderse a tiempo). Se usa como “penalidad” conceptual para urgencias.

### Variables opcionales (si se quiere más granularidad)

Algunos casos usan variables más específicas. Para mantener consistencia, se mapean así:

- **C_copago**: parte “out-of-pocket” del paciente por consulta o práctica. Puede considerarse dentro de \(C_{atencion}\) o desglosarse aparte.
- **C_procedimiento**: prácticas terapéuticas (p. ej. odontología, cirugía ambulatoria). Puede considerarse dentro de \(C_{tratamiento}\).
- **C_insumos**: materiales/consumibles (curaciones, odontología, dispositivos). Puede considerarse dentro de \(C_{tratamiento}\).

## 2) Porcentaje relativo de cada variable

Para cualquier variable \(X\) del total:

\( \%X = \frac{C_X}{C_{total}} \times 100 \)

Esto permite responder “qué pesa más” sin poner montos absolutos.

## 3) Distancia al centro de salud (cerca / no tan cerca / lejos)

La distancia no solo afecta traslado: multiplica tiempo, acompañante y estadía.

Definimos categorías:

- **D0 (cerca)**: viaje simple (sin transbordos complejos), no requiere estadía.
- **D1 (no tan cerca)**: viaje medio (transbordo o tiempo significativo), potencial pérdida de media jornada.
- **D2 (lejos)**: viaje largo (posible pernocte), casi siempre pérdida de jornada, puede requerir acompañante.

Impacto típico:

- \( C_{traslado}(D2) \gg C_{traslado}(D0) \)
- \( C_{tiempo}(D2) \gg C_{tiempo}(D0) \)
- \( C_{estadia}(D2) \) puede aparecer (en D0 suele ser ~0)
- D2 aumenta el costo de “idas y vueltas” (administrativo) de manera no lineal.

## 4) Regla de etiqueta (bajo / medio / alto / muy alto) sin montos

En vez de monto absoluto, la etiqueta se asigna por **dominancia de drivers** y **cantidad de componentes activados**:

- **Bajo**: 1 driver principal; sin diagnóstico costoso; sin medicación crónica; distancia D0.
- **Medio**: 2 drivers relevantes (ej. atención + Rx) o aparece diagnóstico leve; distancia D0–D1.
- **Alto**: aparece diagnóstico/imágenes o tratamiento prolongado; o distancia D2; o requiere acompañante.
- **Muy alto**: internación/procedimiento; o múltiples estudios + medicación; o urgencia con riesgo alto + D2 + estadía.

Ver casos y fórmulas en `formulas-por-tipo-consulta.md`.