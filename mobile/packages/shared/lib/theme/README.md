# Design system "papel" — BioEnlace

Sistema visual unificado para las apps móviles de BioEnlace.

## Filosofía

> **Papel monocromático con acentos de marca usados con criterio.**

- **Base neutra**: una escala de grises cálidos ("papel") desde blanco hueso
  hasta negro suave. Es la mayor parte de la UI.
- **Color con criterio**: los acentos BioEnlace (primary, secondary, danger,
  warning, etc.) aparecen solo en CTAs, estados y feedback. **Nunca** como
  decoración.
- **Bordes sobre sombras**: en lugar de elevation/sombras pesadas, usamos
  bordes finos `paper300/paper400`. Si hay sombra, va sola (sombra **XOR**
  borde, no ambos).
- **Bootstrap-like**: el API expone `intent × variant × size` para que un
  componente se sienta como `btn-primary` / `btn-outline-danger` /
  `badge bg-warning`.
- **Una sola fuente**: Open Sans (vía Google Fonts), escala fija
  ([`BioTypography`](tokens/bio_typography.dart)).
- **Sin dark mode**: por ahora la app es claro-único. Los tokens están
  preparados para extender más adelante.

## Estructura

```
lib/theme/
├── theme.dart                  # AppTheme.lightTheme — ThemeData configurado.
└── tokens/
    ├── tokens.dart             # Re-export único (importar éste).
    ├── paper_palette.dart      # Escala monocroma cálida (paper50..paper900).
    ├── ui_intent.dart          # enum UiIntent + IntentPalette (8 intents).
    ├── border_width.dart       # Anchos: hairline / thin / medium / thick / heavy.
    ├── bio_radius.dart         # Radios: xs / sm / md / lg / pill.
    ├── bio_spacing.dart        # Múltiplos de 4 (xs..xxxl) + presets.
    ├── bio_shadow.dart         # Sombras cálidas y bajas (xs / sm / md).
    ├── bio_motion.dart         # Duraciones y curvas estándar.
    ├── bio_typography.dart     # Escala tipográfica + materialTextTheme().
    ├── bio_border.dart         # Helper para construir Border.
    └── bio_tokens.dart         # ThemeExtension agregador + context.bio.
```

## Paleta papel — [`PaperPalette`](tokens/paper_palette.dart)

Escala monocroma cálida. **No usar negro ni blanco puros.**

| Token | Hex | Uso principal |
|---|---|---|
| `paper50`  | `#FAF8F3` | Fondo de pantalla, surface principal. |
| `paper100` | `#F2EFE8` | Surface "hundida" (cards secundarias, soft bg). |
| `paper150` | `#ECE8E0` | Divider interno ultra suave. |
| `paper200` | `#E7E3DA` | Divider default (separadores, listas). |
| `paper300` | `#D5D0C6` | Borde default (cards, inputs, badges outline). |
| `paper400` | `#A8A29A` | Borde de énfasis (AppBar bottom, BottomNav top, card seleccionada). |
| `paper500` | `#6E6A63` | Texto secundario / muted. |
| `paper600` | `#4A4742` | Texto cuerpo. |
| `paper700` | `#2E2C28` | Títulos. |
| `paper900` | `#1A1916` | Texto enfático, sombras (nunca `#000000`). |

## Intents — [`UiIntent`](tokens/ui_intent.dart)

Equivalente a las clases `*-primary / *-danger / *-warning` de Bootstrap.

| Intent | Base hex | Cuándo usar |
|---|---|---|
| `primary`   | `#0081A7` | CTA principal, links, foco de input. |
| `secondary` | `#00AFB9` | CTA secundario, badge "info-like". |
| `success`   | `#28A745` | Confirmaciones, turno confirmado. |
| `danger`    | `#F07167` | Errores, cancelar, eliminar. |
| `warning`   | `#E08A3F` | Atención requerida — **`EN_RESOLUCION` usa éste**. |
| `info`      | `#00AFB9` | Tips, alertas informativas. |
| `neutral`   | `paper600` | Botones secundarios sin carga emocional. |
| `dark`      | `paper900` | Tag/badge de énfasis sobre fondo claro. |

Cada `UiIntent` resuelve a un [`IntentPalette`](tokens/ui_intent.dart) con
cinco roles:

| Rol | Significado |
|---|---|
| `base`     | Color sólido (botón filled, ícono, badge). |
| `onBase`   | Texto/ícono sobre `base`. |
| `softBg`   | Fondo "soft" (12-14% del base). |
| `softFg`   | Texto sobre `softBg` (más oscuro que `base`). |
| `border`   | Color de borde (outline / softBorder). |

```dart
final palette = IntentPalette.of(UiIntent.warning);
// palette.base   → #E08A3F
// palette.softBg → #FAEAD4
// palette.softFg → #8A4F18 (legible sobre softBg)
```

## Bordes — [`BorderWidth`](tokens/border_width.dart) + [`BioBorder`](tokens/bio_border.dart)

| Token | Px | Cuándo |
|---|---|---|
| `hairline` | 0.5 | Separadores en listas densas. |
| `thin`     | 1.0 | **Default**: cards, inputs, badges outline. |
| `medium`   | 1.5 | Énfasis: AppBar bottom, BottomNav top, foco de input. |
| `thick`    | 2.5 | Outline de botones grandes, error con foco. |
| `heavy`    | 4.0 | Alerta crítica (raro). |

Helper `BioBorder` para construirlos sin repetir:

```dart
BioBorder.bottom(BorderWidth.medium, PaperPalette.paper400)
BioBorder.intent(UiIntent.primary)            // borde sólido primary
BioBorder.intentSoft(UiIntent.warning)        // borde del soft del intent
BioBorder.paperDefault                        // paper300 thin
BioBorder.paperEmphasis                       // paper400 medium
```

## Radios — [`BioRadius`](tokens/bio_radius.dart)

| Token | Px | Cuándo |
|---|---|---|
| `none` | 0  | Bordes "papel" recto (raro). |
| `xs`   | 4  | Chips, badges. |
| `sm`   | 6  | **Default**: inputs, cards, botones. |
| `md`   | 10 | CTAs grandes, contenedores hero. |
| `lg`   | 14 | Hero / banners. |
| `pill` | 999 | Avatares, tags muy pequeños. **Evitar** en botones. |

> En el sistema "papel" preferimos `sm` y `md`. No usar `pill` salvo casos
> específicos.

## Espaciado — [`BioSpacing`](tokens/bio_spacing.dart)

Múltiplos de 4 (escala web estándar). **Nunca** usar números crudos.

| Token | Px | Uso típico |
|---|---|---|
| `xs`   | 4  | Gap interno chiquito (icon ↔ label). |
| `sm`   | 8  | Separación pequeña. |
| `md`   | 12 | Padding de cards / botones small. |
| `lg`   | 16 | **Padding default de pantalla**. |
| `xl`   | 24 | Separación entre secciones. |
| `xxl`  | 32 | Hero spacing. |
| `xxxl` | 48 | Vertical breathing en pantallas grandes. |

Presets:

```dart
BioSpacing.pageHorizontal   // EdgeInsets.symmetric(horizontal: 16)
BioSpacing.pageAll          // EdgeInsets.all(16)
BioSpacing.card             // EdgeInsets.all(12)
BioSpacing.sectionGapY      // EdgeInsets.symmetric(vertical: 16)
BioSpacing.gapH(12)         // SizedBox(height: 12)
BioSpacing.gapW(8)          // SizedBox(width: 8)
```

## Sombras — [`BioShadow`](tokens/bio_shadow.dart)

Sombras bajas y cálidas (basadas en `paper900` con alpha bajo). Preferir
bordes sobre sombras; **shadow XOR border**, no ambos.

| Token | Blur / Offset | Uso |
|---|---|---|
| `xs` | blur 4, y 1, α 0.04 | Hint sutil. |
| `sm` | blur 8, y 2, α 0.06 | Card flotante, sheet. |
| `md` | blur 16, y 4, α 0.08 | Modal, popover. |

## Motion — [`BioMotion`](tokens/bio_motion.dart)

| Token | Duración | Cuándo |
|---|---|---|
| `instant` |  80 ms | Feedback inmediato (chip select). |
| `fast`    | 150 ms | Hover, focus, micro-interacción. |
| `normal`  | 220 ms | Cambio de panel, AnimatedSwitcher. |
| `slow`    | 350 ms | Hero, route transition lenta. |

Curvas: `standard` (`easeOutCubic`), `emphasized`, `decelerate`.

## Tipografía — [`BioTypography`](tokens/bio_typography.dart)

Open Sans, escala única. Colores por defecto: títulos `paper700`, body
`paper600`, caption/overline `paper500`.

| Token | Size / Weight | Uso |
|---|---|---|
| `display`  | 36 / 700 | Pantallas hero, splash. |
| `h1`       | 28 / 700 | Título principal de pantalla (no AppBar). |
| `h2`       | 22 / 600 | Sección. |
| `h3`       | 18 / 600 | **Título del AppBar**. |
| `title`    | 16 / 600 | Card title, alert title. |
| `body`     | 14 / 400 | **Default** cuerpo de texto. |
| `bodySm`   | 13 / 400 | Texto auxiliar denso. |
| `caption`  | 12 / 400 | Helper text, badge text, etiquetas. |
| `overline` | 11 / 600 | Etiquetas all-caps, separadores. |

`BioTypography.materialTextTheme()` genera el `TextTheme` que consume
`ThemeData`. No hay que tocarlo a mano.

## Acceso desde el contexto — `context.bio`

Para no acoplarse a constantes globales, usar la extensión:

```dart
final tokens = context.bio;
Container(
  color: tokens.paperSurface,
  decoration: BoxDecoration(
    border: Border.all(color: tokens.paperBorderDefault),
  ),
  child: Text('hola', style: TextStyle(color: tokens.textBody)),
);
```

`BioTokens` (registrado como `ThemeExtension`) expone:

| Campo | Default |
|---|---|
| `paperBackground`     | `paper50` |
| `paperSurface`        | `paper50` |
| `paperSurfaceSunken`  | `paper100` |
| `paperBorderDefault`  | `paper300` |
| `paperBorderEmphasis` | `paper400` |
| `paperDividerSubtle`  | `paper150` |
| `paperDividerDefault` | `paper200` |
| `textTitle`           | `paper700` |
| `textBody`            | `paper600` |
| `textMuted`           | `paper500` |
| `textDisabled`        | `paper400` |
| `backdropColor`       | `paper900 @ 30%` (cálido translúcido) |

Y un helper:

```dart
context.bio.intentPalette(UiIntent.warning)  // == IntentPalette.of(...)
```

## `AppTheme.lightTheme` — decisiones concretas

Lo que ya viene aplicado por el theme global:

- **Fondo**: `scaffoldBackgroundColor: paper50`.
- **AppBar**: fondo `paper50`, sin elevation/scrolledUnderElevation/surfaceTint,
  `centerTitle: false` (título a la izquierda), título `BioTypography.h3`,
  `iconTheme` 22 px. El borde inferior lo dibuja [`BioAppBar`](../ui/bio_app_bar.dart).
- **Splash**: `splashColor: paper300 @ 40%`, `highlightColor: paper200`,
  `splashFactory: InkRipple` (no tinta primary).
- **Cards**: borde `paper300` thin, radio `sm`, sin elevation.
- **Inputs**: relleno `paper50`, borde `paper300` thin, focus primary medium,
  error danger thin → medium en focus.
- **Dialogs / BottomSheets**: backdrop `paper900 @ 30%`, borde `paper400` thin,
  radio `md`.
- **SnackBar**: fondo `paper700`, texto `paper50`, floating, sin elevation.
- **Chips**: borde `paper300` thin, radio `xs`, selectedColor = softBg primary.
- **Page transitions**: `FadeForwardsPageTransitionsBuilder` en Android,
  `CupertinoPageTransitionsBuilder` en iOS.

## Reglas de oro

1. **Tokens, no literales.** Si necesitás un color/espacio/radio, debe salir
   de los tokens. Si no existe, agregalo al token correspondiente.
2. **Intent declarativo.** Pensá en términos de "este botón es primary
   filled" o "este badge es warning soft", no en colores.
3. **AppBar `BioAppBar`, body en `paper50`.** Nunca un `AppBar` Material
   suelto en pantallas del paciente.
4. **Borde XOR sombra.** No combinar.
5. **Componentes `Bio*` antes que widgets Material directos.** Si te falta
   uno, agregalo al sistema (no a una pantalla).
6. **Sin `withOpacity`.** Usar `color.withValues(alpha: 0.x)`.
7. **`EN_RESOLUCION` se renderiza con `UiIntent.warning`** (decisión cerrada).

## Migración

Ver [`mobile/docs/design-system-papel.md`](../../../../mobile/docs/design-system-papel.md).
