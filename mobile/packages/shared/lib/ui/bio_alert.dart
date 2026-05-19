import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Banner inline para mensajes contextuales (info, error, warning, etc.).
/// Equivalente a `alert-{intent}` / `alert-{intent}-subtle` de Bootstrap.
class BioAlert extends StatelessWidget {
  const BioAlert({
    super.key,
    this.title,
    required this.message,
    this.intent = UiIntent.info,
    this.icon,
    this.onClose,
    this.actions,
  });

  factory BioAlert.danger({
    Key? key,
    String? title,
    required String message,
    IconData? icon = Icons.error_outline,
    VoidCallback? onClose,
    List<Widget>? actions,
  }) =>
      BioAlert(
        key: key,
        title: title,
        message: message,
        intent: UiIntent.danger,
        icon: icon,
        onClose: onClose,
        actions: actions,
      );

  factory BioAlert.warning({
    Key? key,
    String? title,
    required String message,
    IconData? icon = Icons.warning_amber_outlined,
    VoidCallback? onClose,
    List<Widget>? actions,
  }) =>
      BioAlert(
        key: key,
        title: title,
        message: message,
        intent: UiIntent.warning,
        icon: icon,
        onClose: onClose,
        actions: actions,
      );

  factory BioAlert.success({
    Key? key,
    String? title,
    required String message,
    IconData? icon = Icons.check_circle_outline,
    VoidCallback? onClose,
    List<Widget>? actions,
  }) =>
      BioAlert(
        key: key,
        title: title,
        message: message,
        intent: UiIntent.success,
        icon: icon,
        onClose: onClose,
        actions: actions,
      );

  factory BioAlert.info({
    Key? key,
    String? title,
    required String message,
    IconData? icon = Icons.info_outline,
    VoidCallback? onClose,
    List<Widget>? actions,
  }) =>
      BioAlert(
        key: key,
        title: title,
        message: message,
        intent: UiIntent.info,
        icon: icon,
        onClose: onClose,
        actions: actions,
      );

  final String? title;
  final String message;
  final UiIntent intent;
  final IconData? icon;
  final VoidCallback? onClose;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) {
    final palette = IntentPalette.of(intent);
    return Container(
      decoration: BoxDecoration(
        color: palette.softBg,
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(color: palette.border, width: BorderWidth.thin),
      ),
      padding: const EdgeInsets.symmetric(
        horizontal: BioSpacing.md,
        vertical: BioSpacing.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 20, color: palette.softFg),
            const SizedBox(width: BioSpacing.sm),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null) ...[
                  Text(
                    title!,
                    style: BioTypography.title.copyWith(color: palette.softFg),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  message,
                  style: BioTypography.bodySm.copyWith(color: palette.softFg),
                ),
                if (actions != null && actions!.isNotEmpty) ...[
                  const SizedBox(height: BioSpacing.sm),
                  Wrap(
                    spacing: BioSpacing.sm,
                    runSpacing: BioSpacing.xs,
                    children: actions!,
                  ),
                ],
              ],
            ),
          ),
          if (onClose != null)
            IconButton(
              icon: Icon(Icons.close, size: 18, color: palette.softFg),
              onPressed: onClose,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(
                minWidth: 28,
                minHeight: 28,
              ),
              tooltip: 'Cerrar',
            ),
        ],
      ),
    );
  }
}
