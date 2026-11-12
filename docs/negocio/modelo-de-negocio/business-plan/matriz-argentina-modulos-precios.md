# Matriz Argentina — precio por volumen de atenciones × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-11-11  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).  
COGS de referencia: [costos-api.md](../../costos/costos-api.md); el presupuesto comercial del chat AMB se distingue de los escenarios por llamada con context caching.

**Modelo vigente:** licencia = **Σ (atenciones_mes[clase] × precio por atención)**.  
El precio por atención = **COGS_por_atención × (1 + margen sobre costo)**. El margen de lista es **233 %** (~70 % bruto ≈ **42,9 %** después de IIBB + ganancias; ~42,5 % incluyendo IDCB); los add-ons opcionales tienen su propio margen (`addon_margin_on_cost_percent`). A más **atenciones totales** contratadas (suma ambulatorio + urgencia + internación), baja el margen según tramos. El cliente elige tipos de atención y un **volumen aproximado** (presets). **1 profesional a jornada completa = 500 atenciones AMB / mes** (25 atenciones/día × 20 días; 15 min por atención ≈ 6 h 15 de consulta + ~1 h 45 administrativas); **media jornada = 250** (12–13/día × 20 días). Los presets se ajustan por clase: AMB 500, EMER 700 (guardia de 12 h × 15 turnos) e IMP 400 (20 pases/día × 20 días). El **dictado está incluido**; en ambulatorio la **videollamada** es el único add-on opcional. **Motivos de consulta ya no usa audio ni STT**: es un cuestionario con opciones y su costo es el mismo en las tres clases (**0,0006**/atención). El chat paciente (§1) tiene **cupo de 10 mensajes** alrededor del turno (capacidad / headroom para prompts más largos o varias llamadas IA). Lo no contratado **se deshabilita** en sesión operativa y tableros.

> **Recalibración aplicada (2026-11-11):** estas cifras reflejan motivos sin STT. El catálogo backend
> (`PricingPesByEncounterClassCatalog` v16: `motivos` / `motivos_by_class` = 0,0006) y el sitio
> institucional, que lee `GET /licencia/planes`, ya cotizan con estos valores.

Catálogo backend: [`PricingPesByEncounterClassCatalog.php`](../../../../web/common/components/Domain/Organization/Pes/Domain/Catalog/PricingPesByEncounterClassCatalog.php).
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `billing_account` + `billing_account_encounter_entitlement` (pool de profesionales derivados del volumen). Legacy: `efector_encounter_entitlement` (backfill).  
Admin: Licencias / Contratos.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](modelos-pricing-diferenciados.md).

---

## Fórmula

```
COGS_atención = motivos + captura_ia + dictado_stt + infra
              + (AMB ? patient_chat_amb : 0)          # cupo 10 msgs (capacidad)
              + (videollamada ? videollamada : 0)
infra = infra_usd_per_professional_month / atenciones_de_referencia(clase)
atenciones_totales = Σ atenciones_mes[clase]
margen% = tramo(atenciones_totales).margin_on_cost_percent
precio_por_atención = COGS_atención × (1 + margen%/100)
USD/mes ≈ Σ_clase ( atenciones_mes[clase] × precio_por_atención )
```

| Componente COGS comercial (USD / atención) | USD | Notas |
|--------------------------------------------|----:|-------|
| Chat paciente AMB (cupo 10 mensajes) | **0,0048** | Solo ambulatorio · presupuesto conservador para preprocess + guide |
| Motivos (sin audio, mismas tres clases) | **0,0006** | Cuestionario pre-turno + 1 resumen + insights |
| Captura IA (sin STT profesional) | **0,0006** | Siempre |
| Dictado / STT profesional (−30 % on-device) | **0,0025** | **Incluido** en todas las clases · ~5 min × USD 0,0005/min. Con 4 min: **0,0020** (−20 % del STT) |
| Infra app (app + BD + backups + monitoreo) | **0,00006 · 0,000043 · 0,000075** | 0,03/prof/mes repartido en AMB 500 / EMER 700 / IMP 400. Base: 2–3 VPS KVM2 compartidos (~USD 90–160/mes total) |
| Videollamada | **+0,0044** | Solo ambulatorio (opcional) · **40 %** teleconsulta |

**Margen sobre costo (lista):** **233 %**.

Mix porcentual de estos componentes sobre el COGS de cada clase: [resumen-costos-bioenlace.md § Composición del COGS](../../costos/resumen-costos-bioenlace.md#composición-del-cogs-qué-pesa-en-el-costo).

### Descuento por volumen (atenciones / mes)

| Tramo | Atenciones / mes | Margen sobre costo | Descuento vs lista | Margen después IIBB + ganancias (orientativo) |
|-------|------------------|--------------------|--------------------|-----------------------------------------------|
| Precio base | 1–4.999 | **233 %** | 0 % | ~42,9 % |
| Mediano | 5.000–14.999 | **163 %** | **−21 %** | ~37,7 % |
| Grande | 15.000–39.999 | **134 %** | **−30 %** | ~34,6 % |
| Enterprise | 40.000+ | **117 %** | **−35 %** | ~32,4 % |

El margen después de impuestos **se deriva** de los `tax_assumptions` del catálogo (`iibb_rate_percent`, `income_tax_rate_percent`, `bank_debit_credit_tax_percent`), no se escribe a mano. La tabla muestra el escenario **sin IDCB** (IIBB 4 % Santiago del Estero + ganancias 35 %); con IDCB 0,6 %: 42,5 / 37,3 / 34,2 / 32,1. Detalle: [impuestos-argentina.md](../../costos/impuestos-argentina.md).

Presets **AMB**: media jornada (250) · jornada completa (500) · 2 profesionales (1.000) · 4 profesionales (2.000) · clínica chica (5.000) · … · enterprise+ (100.000). **EMER**: 350 · 700 · 1.400 · 2.800 · 7.000 · … · 140.000. **IMP**: 200 · 400 · 800 · 1.600 · 4.000 · … · 80.000.

### Referencia de capacidad (de dónde salen los presets)

- **500 AMB** = 25 atenciones atendidas/día × 20 días; 15 min por atención ≈ **375 min (6 h 15) de consulta** dentro de una jornada de 8 h.
- **250 AMB (media jornada)** = 12–13 atenciones/día × 20 días ≈ **187 min (3 h 07) de consulta** dentro de una media jornada de 4 h.
- **700 EMER** = guardia de 12 h × 15 turnos × ~4 atenciones/hora (15 min).
- **400 IMP** = 20 pases o evoluciones/día × 20 días.

### Precio por atención (lista, sin descuento de tramo)

| Configuración | COGS / atención | Precio lista / atención |
|---------------|----------------:|------------------------:|
| Ambulatorio (chat + motivos + captura + dictado + infra) | 0,0087 | **~0,0288** |
| Ambulatorio + videollamada | 0,0131 | **~0,0435** |
| Urgencia (motivos + captura + dictado + infra) | 0,0038 | **~0,0128** |
| Internación (motivos + captura + dictado + infra) | 0,0039 | **~0,0129** |

### Lectura rápida

| Perfil | Volumen | USD / mes (orientativo) |
|--------|---------|------------------------:|
| **Media jornada** | **250** | **~7,20** |
| **1 profesional (jornada completa)** | **500** | **~14,40** |
| 2 profesionales | 1.000 | **~28,80** |
| CIS ejemplo | 20.000 | **~406** (tramo Grande) |

Ejemplo CIS: **20.000** atenciones ambulatorio (dictado + infra incluidos) → tramo Grande (−30 %) → **~USD 406 / mes**.

**Regla comercial:** videollamada no duplica el STT (el dictado ya está en el COGS base). Motivos no aporta minutos de STT.

---

## Clases vendibles (gates de producto)

| Clase | Label | Qué habilita |
|-------|-------|--------------|
| **AMB** | Ambulatorio | Agenda cupos, turnos paciente, captura AMB |
| **EMER** | Urgencia / guardia | Horarios de presencia, tablero guardia, captura EMER |
| **IMP** | Internación | Cobertura piso, mapa camas, captura IMP |

---

## Deshabilitado de lo no contratado

| Capa | Comportamiento |
|------|----------------|
| Wizard / opciones sesión | Solo clases contratadas en la **cuenta** del efector (`billing_account_encounter_entitlement`; si no hay cuenta/filas: `default_when_empty: allow_all`) |
| `set-session` | Rechaza `encounter_class` no contratada |
| Agenda AMB / horario EMER·IMP | Operan solo si la clase está contratada (vía sesión) |
| Alta PES | Tope `max_pes` del **pool de la cuenta de facturación** (personas distintas en efectores con rol **POOL**; excluye AdminEfector) |
| Baja PES | Operativa inmediata; el mes en curso se cobra con el `max_pes` vigente. Pending a nivel cuenta, efectivo el **1º del mes siguiente** (`php yii entitlement/apply-pending-downgrades`). |
| Admin | [`/billing-account`](../../../../web/admin/) — cuentas Ministerio/Red/Efector, miembros con rol, editar `max_pes` / dictado / videollamada por clase. Tab **Licencia** en ficha de efector. |

### Cuentas y pool

```
billing_account (MINISTERIO | RED | EFECTOR)
  ├── billing_account_efector (rol_membresia: POOL | AFILIADO)
  └── billing_account_encounter_entitlement (AMB/EMER/IMP + max_pes + pending + flags)
```

| Rol | Significado |
|-----|-------------|
| **POOL** | Consume `max_pes` de esa cuenta (facturación). Máx. **una** membresía POOL por efector. |
| **AFILIADO** | Solo jerarquía (ministerio/red). No consume cupo. Compatible con POOL en otra cuenta (efector autárquico). |

Un efector sin membresía POOL: sin tope (compat). Cupo compartido: el uso suma profesionales billable solo de miembros **POOL**.

---

## Compradores típicos (lectura rápida)

| Comprador | Selección típica en el calculador |
|-----------|-----------------------------------|
| Independiente | Ambulatorio · media jornada (250) o jornada completa (500) |
| Clínica ambulatoria | Ambulatorio × volumen mensual |
| Sanatorio con guardia | Ambulatorio + urgencia |
| Sanatorio con camas | Ambulatorio + urgencia + internación |
| Teleconsulta | Misma selección + add-on videollamada |

---

## Add-ons / fuera de la fórmula

Cada add-on declara su estado **por tipo de atención** (`incluido`, `opcional`, `no disponible`). El **ámbito** se deriva de esa matriz: si todos los tipos vendibles comparten el mismo estado elegible, el add-on aplica a todos y se elige **una sola vez** (no se tilda tipo por tipo); si difiere, aplica solo a algunos tipos. Los add-ons incluidos se informan en el bloque *Funcionalidades y add-ons* del calculador; los opcionales se tildan ahí mismo y mueven el precio.

Los add-ons **opcionales** tienen su propio margen (`addon_margin_on_cost_percent`), separado del margen de la licencia. Así se puede abaratar la videollamada sin tocar el precio base. Escenario sobre AMB (COGS video **0,0044**/atención, 500 atenciones):

| Margen del add-on | Precio video / atención | USD/prof/mes (solo el add-on) | Margen neto del add-on (IIBB + IDCB + ganancias) |
|---|---:|---:|---:|
| 233 % (actual) | **0,0147** | **7,33** | 42,5 % |
| 150 % | **0,0110** | **5,50** | 36,0 % |
| 100 % | **0,0088** | **4,40** | 29,5 % |

El margen de la licencia (tabla de tramos) no cambia: solo se mueve la porción del add-on.

| Add-on | Estado | Precio orientativo |
|--------|--------|-------------------|
| Autogestión del paciente desde la app | Incluido en ambulatorio | — |
| Autogestión del personal de salud | Incluido en todas las clases | — |
| Informe clínico con dictado por voz | Incluido en todas las clases | En la fórmula (COGS STT) |
| Videollamada | Opcional solo en ambulatorio | COGS + margen (ver tabla arriba) |
| Implementación + capacitación | One-shot | USD 3–40k según tamaño |
| Integración LIS | One-shot + mant. | USD 2–8k + 200–800/mes |
| Autorización OS / prepaga | Otro ciclo | USD 2–15k/mes |
| API / white-label | Volumen API | USD 10–40k/mes |

---

## Priorización comercial

1. Publicar calculador institucional (COGS + margen + presets de volumen).  
2. Cargar cuentas (`billing_account`) y entitlements de pool al firmar; asociar efectores como **POOL** o **AFILIADO** (autárquicos: afiliados al ministerio + pool en cuenta propia).  
3. Enforce alta PES + downgrade diferido (`entitlement/apply-pending-downgrades`).  
4. Admin Licencias / Contratos para editar `max_pes` y miembros.  
5. **Self-service institucional** (AdminEfector + pasarela **simulada**) y solicitud asistida AdminMinisterio — ver [alta-cuenta-licencia.md](../../../producto/alta-cuenta-licencia.md).  
6. Usage report para renovación; pasarela real (Mercado Pago / Stripe) sustituyendo el gateway simulado.

---

## Referencias

- [costos-api.md](../../costos/costos-api.md)
- [impuestos-argentina.md](../../costos/impuestos-argentina.md)
- [modelos-pricing-diferenciados.md](modelos-pricing-diferenciados.md)
