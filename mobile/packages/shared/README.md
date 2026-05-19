# `shared` — Librería compartida BioEnlace (paciente + médico)

Paquete Flutter interno que vive en `mobile/packages/shared` y se consume desde
ambas apps (`mobile/paciente`, `mobile/medico`) vía `dependencies: shared:
path: ../packages/shared` en sus respectivos `pubspec.yaml`.

## Qué hay acá

```
lib/
├── shared.dart                 # Punto de entrada (barril). Re-exporta todo lo público.
├── auth/                       # Auth biométrica.
├── config/                     # ApiConfig (URLs, headers).
├── theme/                      # Design system "papel" — ver theme/README.md
│   ├── theme.dart              # AppTheme.lightTheme (Material 3 configurado).
│   └── tokens/                 # ← Tokens del design system (paper, intent, ...).
├── ui/                         # ← Widgets Bio* del design system — ver ui/README.md
└── ui_json/                    # Renderer de descriptores ui_json (flows del asistente).
```

## Cómo importar

Una sola línea expone todo lo público:

```dart
import 'package:shared/shared.dart';
```

Eso trae:

- `AppTheme.lightTheme` (configurado con Material 3 + tokens "papel").
- `PaperPalette`, `IntentPalette`, `UiIntent`, `BorderWidth`, `BioRadius`,
  `BioSpacing`, `BioShadow`, `BioMotion`, `BioTypography`, `BioBorder`,
  `BioTokens` y la extensión `context.bio`.
- Widgets: `BioAppBar`, `BioBottomNav`, `BioButton`, `BioBadge`, `BioCard`,
  `BioChip`, `BioDivider`, `BioInput`, `BioAlert`.
- `ApiConfig`, `LoginScreen`, `UiJsonScreen`, `FlowSnapshot`, etc.

## Design system "papel"

El sistema visual de BioEnlace ("papel" monocromático con acentos de marca)
está documentado en dos lugares:

- **[`lib/theme/README.md`](lib/theme/README.md)** — filosofía, paleta, intents,
  tokens, decisiones de diseño.
- **[`lib/ui/README.md`](lib/ui/README.md)** — catálogo de componentes `Bio*`
  con ejemplos de uso.

Para migrar una pantalla existente al sistema "papel" hay una guía paso a paso
en [`mobile/docs/design-system-papel.md`](../../../mobile/docs/design-system-papel.md).

## Estado de migración

Migración al sistema "papel" **cerrada** (Bloques A→D del plan
[`mobile/docs/migracion-medico-cierre.md`](../../../mobile/docs/migracion-medico-cierre.md)):

- `mobile/paciente`, `mobile/medico` y `mobile/packages/shared` solo dependen
  de `AppTheme.lightTheme`, `Bio*`, `context.bio`, `IntentPalette` y
  `PaperPalette`.
- Los archivos legacy `theme/color_palette.dart`, `theme/button_styles.dart` y
  los getters de compatibilidad `AppTheme.primaryColor / successColor /
  dangerColor / warningColor / infoColor / dark / backgroundColor / cardColor /
  titleStyle / subTitleStyle / h1Style…h6Style` fueron eliminados. Si necesitás
  un valor concreto, usá los tokens nuevos:
  - colores semánticos → `IntentPalette.of(UiIntent.X).{base|onBase|softBg|softFg|border}`.
  - colores neutros → `context.bio.{paperBackground|paperSurface|textTitle|textMuted|...}`
    o `PaperPalette.paperXXX` para constantes.
  - tipografía → `BioTypography.{h1..h3,title,body,bodySm,caption}`.

## Convenciones

- **No** importar `package:flutter/material.dart` con tema custom en cada
  pantalla; siempre `AppTheme.lightTheme` resuelto vía `MaterialApp(theme: ...)`.
- **No** usar colores literales (`Color(0xFF...)`) en pantallas; siempre
  `PaperPalette.*` o `IntentPalette.of(...)` o `context.bio.*`.
- **No** usar espaciados arbitrarios (`EdgeInsets.all(13)`); siempre
  `BioSpacing.*`.
- **No** usar radios arbitrarios; siempre `BioRadius.*`.
- **No** usar `withOpacity` (deprecado en Flutter 3.27); usar `withValues(alpha: ...)`.
