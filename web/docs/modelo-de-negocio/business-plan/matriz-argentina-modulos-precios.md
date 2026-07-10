# Matriz Argentina — precio por PES × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-07-10  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).

**Modelo vigente:** licencia = **Σ (PES contratados × precio de la clase de encounter)**.  
El cliente elige qué `encounter_class` contrata (AMB / EMER / IMP) y cuántas asignaciones PES por cada una. Lo no contratado **se deshabilita** en sesión operativa y tableros.

Metadata producto: [`pricing-pes-by-encounter-class.yaml`](../../../common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).  
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `efector_encounter_entitlement`.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

## Fórmula

```
USD/mes ≈ Σ_clase ( cantidad_PES[clase] × price_per_pes[clase] )
```

| Clase | Label | USD / PES / mes (orientativo) | Qué habilita |
|-------|-------|-------------------------------|--------------|
| **AMB** | Ambulatorio | **18** | Agenda cupos, turnos paciente, captura AMB |
| **EMER** | Urgencia / guardia | **55** | Cobertura roster, tablero guardia, captura EMER |
| **IMP** | Internación | **42** | Cobertura piso, mapa camas, captura IMP |

Ejemplo: 10 PES AMB + 4 PES EMER → `(10×18) + (4×55) = 180 + 220 = **USD 400/mes**`.

---

## Deshabilitado de lo no contratado

| Capa | Comportamiento |
|------|----------------|
| Wizard / opciones sesión | Solo clases en `efector_encounter_entitlement` (si no hay filas: `default_when_empty: allow_all`) |
| `set-session` | Rechaza `encounter_class` no contratada |
| Agenda AMB / cobertura EMER·IMP | Operan solo si la clase está contratada (vía sesión) |

Tope `max_pes` por clase: declarado en contrato; enforcement estricto de conteo = fase siguiente.

---

## Compradores típicos (lectura rápida)

| Comprador | Selección típica en el calculador |
|-----------|-----------------------------------|
| Clínica ambulatoria | Solo AMB × N PES |
| Sanatorio con guardia | AMB + EMER |
| Sanatorio con camas | AMB + EMER + IMP |

---

## Add-ons / fuera de la fórmula PES

| Ítem | Notas | Precio orientativo |
|------|-------|-------------------|
| Implementación + capacitación | One-shot | USD 3–40k según tamaño |
| Integración LIS | One-shot + mant. | USD 2–8k + 200–800/mes |
| IA captura premium (fair use excedido) | Variable | A cotizar / cuota |
| Autorización OS / prepaga | Otro ciclo | USD 2–15k/mes |
| API / white-label | Volumen API | USD 10–40k/mes |

---

## Priorización comercial

1. Publicar calculador institucional y cotizar con fórmula PES × clase.  
2. Cargar entitlements por efector al firmar.  
3. Usage report (encounters / PES activos) para renovación.  
4. Pasarela / seña (fase posterior).

---

## Referencias

- [Agenda por encounter class](../../producto/agenda-por-encounter-class.md)
- [Mapa vías × Bioenlace](./mapa-vias-ingreso-bioenlace.md)
- [Modelos de pricing](./modelos-pricing-diferenciados.md)
