# Matriz Argentina — precio por volumen de atenciones × encounter_class

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-07-23  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA. Impuestos: [impuestos-argentina.md](../../costos/impuestos-argentina.md).  
COGS de referencia: [costos-api.md](../../costos/costos-api.md) (**columna con context caching**).

**Modelo vigente:** licencia = **Σ (atenciones_mes[clase] × precio por atención)**.  
El precio por atención = **COGS_por_atención × (1 + margen sobre costo)**. El margen de lista es **233 %** (~70 % bruto ≈ ~49 % después de IIBB + ganancias). A más **atenciones totales** contratadas (suma ambulatorio + urgencia + internación), baja el margen según tramos. El cliente elige tipos de atención y un **volumen aproximado** (presets). **1 profesional = 200 atenciones / mes**. El **dictado está incluido**; en ambulatorio la **videollamada** es el único add-on opcional. Motivos con audio se presupuestan al **~30 %** de las atenciones (resto texto). El chat paciente (§1) tiene **cupo de 10 mensajes** alrededor del turno (capacidad / headroom para prompts más largos o varias llamadas IA). Lo no contratado **se deshabilita** en sesión operativa y tableros.

Metadata producto: [`pricing-pes-by-encounter-class.yaml`](../../../common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml).  
Calculador público: sitio [`institucional/#precios`](../../../../institucional/index.html).  
Entitlements BD: `billing_account` + `billing_account_encounter_entitlement` (pool de profesionales derivados del volumen). Legacy: `efector_encounter_entitlement` (backfill).  
Admin: Licencias / Contratos.

**Fuera de este modelo (siguen cotizándose aparte):** pathway fees / PMPM al financiador, integraciones one-shot, autorización OS enterprise, proyectos farmacia/quirófano. Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

## Fórmula

```
COGS_atención = motivos_blend + captura_ia + dictado_stt
              + (AMB ? patient_chat_amb : 0)          # cupo 10 msgs (capacidad)
              + (videollamada ? videollamada : 0)
atenciones_totales = Σ atenciones_mes[clase]
margen% = tramo(atenciones_totales).margin_on_cost_percent
precio_por_atención = COGS_atención × (1 + margen%/100)
USD/mes ≈ Σ_clase ( atenciones_mes[clase] × precio_por_atención )
```

| Componente COGS (USD / atención, **con context caching**) | USD | Notas |
|-----------------------------------------------------------|----:|-------|
| Chat paciente AMB (cupo 10 mensajes) | **0,0019** | Solo ambulatorio · capacidad / headroom |
| Motivos (blend 30 % audio + 70 % texto) | **0,0014** | No todas las especialidades usan audio igual |
| Captura IA (sin STT profesional) | **0,0006** | Siempre |
| Dictado / STT profesional (−30 % on-device) | **0,0025** | **Incluido** en todas las clases |
| Videollamada | **+0,0044** | Solo ambulatorio (opcional) · **40 %** teleconsulta |

**Margen sobre costo (lista):** **233 %**.

### Descuento por volumen (atenciones / mes)

| Tramo | Atenciones / mes | Margen sobre costo | Descuento vs lista | Margen después IIBB + ganancias (orientativo) |
|-------|------------------|--------------------|--------------------|-----------------------------------------------|
| Precio base | 1–4.999 | **233 %** | 0 % | ~49 % |
| Mediano | 5.000–14.999 | **163 %** | **−21 %** | ~43,5 % |
| Grande | 15.000–39.999 | **134 %** | **−30 %** | ~40 % |
| Enterprise | 40.000+ | **117 %** | **−35 %** | ~37,5 % |

Presets: **1 profesional (200)** · 2 (400) · 4 (800) · Clínica chica (2.000) · … · Enterprise+ (100.000).

### Precio por atención (lista, sin descuento de tramo)

| Configuración | COGS / atención | Precio lista / atención |
|---------------|----------------:|------------------------:|
| Ambulatorio (chat + motivos blend + captura + **dictado**) | 0,0064 | **~0,0213** |
| Ambulatorio + videollamada | 0,0108 | **~0,0360** |
| Urgencia / internación (motivos blend + dictado) | 0,0045 | **~0,0150** |

### Lectura rápida

| Perfil | Volumen | USD / mes (orientativo) |
|--------|---------|------------------------:|
| **1 profesional** | **200** | **~4,26** |
| 2 profesionales | 400 | **~8,52** |
| CIS ejemplo | 20.000 | **~300** (tramo Grande) |

Ejemplo CIS: **20.000** atenciones ambulatorio (dictado incluido) → tramo Grande (−30 %) → **~USD 300 / mes**.

**Regla comercial:** videollamada no duplica el STT (el dictado ya está en el COGS base).

---

## Clases vendibles (gates de producto)

| Clase | Label | Qué habilita |
|-------|-------|--------------|
| **AMB** | Ambulatorio | Agenda cupos, turnos paciente, captura AMB |
| **EMER** | Urgencia / guardia | Cobertura roster, tablero guardia, captura EMER |
| **IMP** | Internación | Cobertura piso, mapa camas, captura IMP |

---

## Deshabilitado de lo no contratado

| Capa | Comportamiento |
|------|----------------|
| Wizard / opciones sesión | Solo clases contratadas en la **cuenta** del efector (`billing_account_encounter_entitlement`; si no hay cuenta/filas: `default_when_empty: allow_all`) |
| `set-session` | Rechaza `encounter_class` no contratada |
| Agenda AMB / cobertura EMER·IMP | Operan solo si la clase está contratada (vía sesión) |
| Alta PES | Tope `max_pes` del **pool de la cuenta de facturación** (personas distintas en efectores con rol **POOL**; excluye AdminEfector) |
| Baja PES | Operativa inmediata; el mes en curso se cobra con el `max_pes` vigente. Pending a nivel cuenta, efectivo el **1º del mes siguiente** (`php yii entitlement/apply-pending-downgrades`). |
| Admin | [`/billing-account`](../../../../admin/) — cuentas Ministerio/Red/Efector, miembros con rol, editar `max_pes` / dictado / videollamada por clase. Tab **Licencia** en ficha de efector. |

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
| Independiente | Ambulatorio · preset **1 profesional (200)** |
| Clínica ambulatoria | Ambulatorio × volumen mensual |
| Sanatorio con guardia | Ambulatorio + urgencia |
| Sanatorio con camas | Ambulatorio + urgencia + internación |
| Teleconsulta | Misma selección + add-on videollamada |

---

## Add-ons / fuera de la fórmula

| Ítem | Notas | Precio orientativo |
|------|-------|-------------------|
| Videollamada | En la fórmula (COGS + margen) | Ver tabla arriba |
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
5. **Self-service institucional** (AdminEfector + pasarela **simulada**) y solicitud asistida AdminMinisterio — ver [alta-cuenta-licencia.md](../../producto/alta-cuenta-licencia.md).  
6. Usage report para renovación; pasarela real (Mercado Pago / Stripe) sustituyendo el gateway simulado.

---

## Referencias

- [costos-api.md](../../costos/costos-api.md)
- [impuestos-argentina.md](../../costos/impuestos-argentina.md)
- [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md)
