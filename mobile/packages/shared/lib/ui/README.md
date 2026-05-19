# Componentes `Bio*` — catálogo

Widgets del design system "papel". Todos:

- Consumen tokens (`PaperPalette`, `IntentPalette`, `BioRadius`, `BioSpacing`, ...).
- Aceptan `UiIntent` para variar color (estilo Bootstrap: `intent × variant × size`).
- Respetan `context.bio` (ThemeExtension `BioTokens`).
- No reciben `Color` crudo. Si necesitás otro intent, agregá uno a
  [`tokens/ui_intent.dart`](../theme/tokens/ui_intent.dart).

> Diseño y filosofía: [`../theme/README.md`](../theme/README.md).

## Índice

- [BioAppBar](#bioappbar)
- [BioBottomNav](#biobottomnav)
- [BioButton](#biobutton)
- [BioBadge](#biobadge)
- [BioCard](#biocard)
- [BioChip](#biochip)
- [BioDivider](#biodivider)
- [BioInput](#bioinput)
- [BioAlert](#bioalert)

---

## BioAppBar

AppBar "papel": fondo `paper50`, **título alineado a la izquierda**, borde
inferior visible (`paper400` medium). `PreferredSizeWidget`, listo para
`Scaffold.appBar`.

```dart
Scaffold(
  appBar: BioAppBar(
    title: 'Próximos turnos',
    actions: [
      IconButton(icon: const Icon(Icons.notifications_outlined), onPressed: ...),
    ],
  ),
  body: ...,
);
```

Parámetros relevantes:

- `title` (`String?`) o `titleWidget` (`Widget?`) — mutuamente excluyentes.
- `leading` — si no se pasa, infiere botón "atrás" cuando `Navigator.canPop`.
- `automaticallyImplyLeading` (default `true`).
- `bottomBorderWidth` (default `BorderWidth.medium`).
- `bottomBorderColor` (default `context.bio.paperBorderEmphasis`).

---

## BioBottomNav

Bottom navigation "papel" con borde superior visible. Color activo = primary,
ícono escala 1.05 al seleccionar (no hay switch filled/outline).

```dart
BioBottomNav(
  currentIndex: _index,
  onTap: (i) => setState(() => _index = i),
  items: const [
    BioBottomNavItem(icon: Icons.home_outlined, label: 'Inicio'),
    BioBottomNavItem(icon: Icons.calendar_today_outlined, label: 'Turnos'),
    BioBottomNavItem(icon: Icons.chat_bubble_outline, label: 'Asistente'),
    BioBottomNavItem(icon: Icons.settings_outlined, label: 'Configuración'),
  ],
);
```

---

## BioButton

Botón unificado: `intent × variant × size`.

| Variant | Equivalente Bootstrap |
|---|---|
| `filled`  | `btn-{intent}` |
| `outline` | `btn-outline-{intent}` |
| `soft`    | `btn-{intent}-subtle` (BS 5.3) |

| Size | Altura | Padding | Radius |
|---|---|---|---|
| `sm` | 32 px | 12 px | `sm` (6) |
| `md` | 44 px | 16 px | `sm` (6) |
| `lg` | 52 px | 24 px | `md` (10) |

```dart
BioButton(label: 'Guardar', onPressed: _save);                              // primary filled md
BioButton(label: 'Cancelar', intent: UiIntent.neutral,
          variant: BioButtonVariant.outline, onPressed: _cancel);
BioButton(label: 'Reservar', size: BioButtonSize.lg, fullWidth: true,
          icon: Icons.add, onPressed: _reservar);

// Factories tipo Bootstrap
BioButton.primary(label: 'Confirmar', onPressed: _ok);
BioButton.outlinePrimary(label: 'Ver más', onPressed: _ver);
BioButton.softPrimary(label: 'Detalle', onPressed: _detalle);
BioButton.danger(label: 'Eliminar', onPressed: _del);
BioButton.outlineDanger(label: 'Cancelar turno', onPressed: _cancel);
BioButton.neutral(label: 'Atrás', onPressed: _back);
```

Otros parámetros: `icon`, `iconRight`, `fullWidth` (estira al ancho del
padre), `loading` (muestra spinner del color foreground).

---

## BioBadge

Etiqueta de estado. Equivalente a `badge bg-{intent}` /
`badge text-bg-{intent}-subtle` / `badge border border-{intent}`.

| Variant | Aspecto |
|---|---|
| `filled`  | Color sólido. |
| `soft`    | **Default.** Fondo suave + borde + texto del soft. |
| `outline` | Transparente con borde sólido. |

```dart
BioBadge(label: 'Pendiente');                                    // neutral soft
BioBadge(label: 'Confirmado', intent: UiIntent.success);
BioBadge.warning('En resolución', icon: Icons.warning_amber_outlined);   // ← EN_RESOLUCION
BioBadge.danger('Cancelado', icon: Icons.cancel_outlined);
BioBadge.success('Confirmado');
BioBadge.info('Recordatorio');
BioBadge.neutral('Borrador');
```

> **Regla de oro**: `EN_RESOLUCION` se renderiza con `BioBadge.warning(...)`.

---

## BioCard

Contenedor "papel": fondo claro, borde sutil, sin sombra por default.

| Constructor | Aspecto |
|---|---|
| `BioCard(child: ...)`                                | Default: borde `paper300` thin. |
| `BioCard.emphasis(child: ...)`                       | Seleccionada/activa: borde `paper400` medium. |
| `BioCard.intent(child: ..., intent: UiIntent.x)`     | Post-it: cinta lateral izquierda del intent. |

```dart
BioCard(
  child: Column(
    children: [
      Text('Hoy 16:30 — Dr. Pérez', style: BioTypography.title),
      BioSpacing.gapH(BioSpacing.xs),
      Text('Cardiología', style: BioTypography.bodySm),
    ],
  ),
);

BioCard.intent(
  intent: UiIntent.warning,
  onTap: () => abrirFlujoResolucion(turno),
  child: Row(
    children: [
      const Icon(Icons.warning_amber_outlined),
      const SizedBox(width: BioSpacing.sm),
      Expanded(child: Text('Tu turno necesita resolución')),
      BioBadge.warning('En resolución'),
    ],
  ),
);
```

Otros parámetros: `padding` (default `BioSpacing.card`), `margin`,
`borderRadius`, `shadow` (lista de `BoxShadow` — combinar **shadow XOR
border**), `color`, `onTap`.

---

## BioChip

Chip de filtro / selección. Inactivo: fondo papel + borde `paper300`.
Activo: fondo + borde del intent (soft).

```dart
Wrap(
  spacing: BioSpacing.sm,
  children: [
    BioChip(label: 'Todos',      selected: _f == 'todos',   onTap: () => _set('todos')),
    BioChip(label: 'Próximos',   selected: _f == 'prox',    onTap: () => _set('prox'),
            icon: Icons.event_outlined),
    BioChip(label: 'Resolución', selected: _f == 'res',     onTap: () => _set('res'),
            intent: UiIntent.warning),
  ],
);
```

---

## BioDivider

Línea separadora.

```dart
const BioDivider();                  // default: paper200, hairline
BioDivider.subtle();                 // paper150, hairline
BioDivider.emphasis();               // paper400, medium
BioDivider(indent: 16, endIndent: 16);   // con sangría
```

---

## BioInput

Wrapper "papel" de `TextFormField`. El estilo sale del
`inputDecorationTheme` del `AppTheme`; este widget solo expone una API plana
con los campos que más usamos.

```dart
BioInput(
  controller: _emailCtrl,
  label: 'Email',
  hint: 'tu@correo.com',
  helper: 'Te enviaremos un código',
  keyboardType: TextInputType.emailAddress,
  textInputAction: TextInputAction.next,
  prefixIcon: const Icon(Icons.mail_outlined),
  validator: (v) => (v == null || v.isEmpty) ? 'Requerido' : null,
);
```

Parámetros: `controller`, `initialValue`, `label`, `hint`, `helper`,
`errorText`, `prefixIcon`, `suffixIcon`, `obscureText`, `keyboardType`,
`textInputAction`, `maxLines`, `minLines`, `maxLength`, `onChanged`,
`onSubmitted`, `onTap`, `enabled`, `autofocus`, `validator`, `focusNode`,
`readOnly`, `textCapitalization`.

---

## BioAlert

Banner inline para mensajes contextuales. `alert-{intent}` /
`alert-{intent}-subtle` de Bootstrap.

```dart
BioAlert(
  title: 'Atención',
  message: 'Tu turno del viernes necesita resolución.',
  intent: UiIntent.warning,
  icon: Icons.warning_amber_outlined,
  actions: [
    BioButton(label: 'Resolver ahora', size: BioButtonSize.sm,
              intent: UiIntent.warning, onPressed: _resolver),
  ],
  onClose: () => setState(() => _showAlert = false),
);

// Factories
BioAlert.danger(message: 'No pudimos conectar con el servidor.');
BioAlert.warning(message: 'Tenés un turno pendiente de confirmación.');
BioAlert.success(message: 'Turno confirmado.');
BioAlert.info(message: 'El consultorio cierra a las 18:00 los viernes.');
```

---

## Reglas para agregar / extender componentes

1. **Solo tokens, no literales.** Cero `Color(0xFF...)` / `EdgeInsets.all(13)`.
2. **API `intent × variant × size`** cuando aplique. Factories tipo
   Bootstrap (`.primary`, `.danger`, ...) para los casos comunes.
3. **No imponer `Material`/`Scaffold` propio.** Los widgets son
   composables; el contenedor de pantalla siempre lo arma quien los usa.
4. **No usar `withOpacity`.** Usar `withValues(alpha: ...)`.
5. **Animar con `BioMotion`**, no duraciones crudas.
6. **Dejar foco accesible** (no quitar `splashFactory`, no `Material` con
   `color: Colors.transparent` sin `InkWell`).
7. **Documentar acá**: cada widget nuevo entra al índice y al catálogo.
