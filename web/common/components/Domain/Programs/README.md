# Programs

Programas de salud pública (hoy: SUMAR y el programa de diabetes): padrón de beneficiarios, inscripción de la persona al programa, dispensa y nomenclador propio con su autofacturación.

Existe como dominio porque las tres piezas tienen dueño común y no encajan en otro: el **padrón** no es perfil (`Person`), el **nomenclador** no es la oferta del efector (`Organization`) ni un acto codificado (`Terminology`), y la **autofacturación** al programa no es la facturación de la licencia (`Organization/Billing`).

| Qué | Dónde |
|-----|-------|
| Modelos (AR) | `common/models/Programs/` |

## Límite con Clinical

`Domain/Clinical/Service/EncounterSumarAutofacturacionContext` se queda en `Clinical`: su sujeto es el encounter y solo consulta si corresponde autofacturar. La regla del programa (padrón, nomenclador, elegibilidad) es de este dominio.
