import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// AppBar "papel": fondo `paper50`, título alineado a la izquierda, borde
/// inferior visible. Reemplaza al `AppBar` de Material en pantallas BioEnlace.
class BioAppBar extends StatelessWidget implements PreferredSizeWidget {
  const BioAppBar({
    super.key,
    this.title,
    this.titleWidget,
    this.leading,
    this.actions,
    this.automaticallyImplyLeading = true,
    this.bottomBorderWidth = BorderWidth.medium,
    this.bottomBorderColor,
    this.height = kToolbarHeight,
  });

  final String? title;
  final Widget? titleWidget;
  final Widget? leading;
  final List<Widget>? actions;
  final bool automaticallyImplyLeading;

  /// Ancho del borde inferior (token).
  final double bottomBorderWidth;

  /// Color del borde inferior; por defecto `bio.paperBorderEmphasis`.
  final Color? bottomBorderColor;

  final double height;

  @override
  Size get preferredSize => Size.fromHeight(height + bottomBorderWidth);

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final borderColor = bottomBorderColor ?? tokens.paperBorderEmphasis;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        border: BioBorder.bottom(bottomBorderWidth, borderColor),
      ),
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: height,
          child: NavigationToolbar(
            leading: _resolveLeading(context),
            middle: titleWidget ??
                (title != null
                    ? Text(
                        title!,
                        style: BioTypography.h3,
                        overflow: TextOverflow.ellipsis,
                      )
                    : null),
            trailing: actions == null || actions!.isEmpty
                ? null
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: actions!,
                  ),
            centerMiddle: false,
            middleSpacing: BioSpacing.md,
          ),
        ),
      ),
    );
  }

  Widget? _resolveLeading(BuildContext context) {
    if (leading != null) return leading;
    if (!automaticallyImplyLeading) return null;
    final canPop = Navigator.of(context).canPop();
    if (!canPop) return null;
    return IconButton(
      icon: const Icon(Icons.arrow_back),
      onPressed: () => Navigator.of(context).maybePop(),
      tooltip: MaterialLocalizations.of(context).backButtonTooltip,
    );
  }
}
