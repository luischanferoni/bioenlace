import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'biometric_auth.dart';
import 'biometric_session_prefs.dart';

/// Ofrece activar huella/Face ID del dispositivo para futuros ingresos.
abstract final class BiometricEnrollmentPrompt {
  /// Devuelve `true` si el usuario activó la biometría con éxito.
  static Future<bool> show(
    BuildContext context, {
    required String appTitle,
    required String biometricType,
  }) async {
    if (biometricType.isEmpty) return false;

    final accept = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        final tokens = ctx.bio;
        return AlertDialog(
          backgroundColor: tokens.paperBackground,
          title: Text('Activar $biometricType', style: BioTypography.h2),
          content: Text(
            'Para ingresar más rápido a $appTitle, podés usar '
            '$biometricType en este dispositivo. También te la pediremos '
            'si la app estuvo inactiva unos minutos.',
            style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Ahora no'),
            ),
            BioButton.primary(
              label: 'Activar $biometricType',
              size: BioButtonSize.sm,
              onPressed: () => Navigator.pop(ctx, true),
            ),
          ],
        );
      },
    );

    if (accept != true || !context.mounted) return false;

    final auth = BiometricAuth();
    final result = await auth.authenticate(
      'Confirmá con $biometricType para activar el acceso rápido',
    );
    if (result['success'] == true) {
      await BiometricSessionPrefs.setUnlockEnabled(true);
      await BiometricSessionPrefs.touchActivity();
      return true;
    }

    if (context.mounted && result['isUserCancel'] != true) {
      final error = result['error']?.toString();
      if (error != null && error.isNotEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error)),
        );
      }
    }

    return false;
  }
}
