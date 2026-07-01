import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'biometric_auth.dart';
import 'biometric_session_prefs.dart';

/// Resultado del diálogo de activación biométrica.
enum BiometricEnrollmentResult {
  success,
  skipped,
  failed,
}

/// Ofrece activar huella/Face ID del dispositivo para futuros ingresos.
abstract final class BiometricEnrollmentPrompt {
  /// Devuelve el resultado del flujo (activación, rechazo explícito o fallo).
  static Future<BiometricEnrollmentResult> show(
    BuildContext context, {
    required String appTitle,
    required String biometricType,
  }) async {
    if (biometricType.isEmpty) return BiometricEnrollmentResult.failed;

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

    if (accept != true || !context.mounted) {
      return BiometricEnrollmentResult.skipped;
    }

    final auth = BiometricAuth();
    final result = await auth.authenticate(
      'Confirmá con $biometricType para activar el acceso rápido',
    );
    if (result['success'] == true) {
      await BiometricSessionPrefs.setUnlockEnabled(true);
      await BiometricSessionPrefs.touchActivity();
      return BiometricEnrollmentResult.success;
    }

    if (context.mounted && result['isUserCancel'] != true) {
      final error = result['error']?.toString();
      if (error != null && error.isNotEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error)),
        );
      }
    }

    return BiometricEnrollmentResult.failed;
  }
}

/// Tras el primer acceso exitoso, ofrece registrar huella/Face ID para ingresos rápidos.
///
/// [context] opcional; si no está montado usa [navigatorKey].
Future<void> maybeOfferBiometricEnrollment({
  BuildContext? context,
  GlobalKey<NavigatorState>? navigatorKey,
  required String appTitle,
}) async {
  if (await BiometricSessionPrefs.isUnlockEnabled()) {
    return;
  }

  final prefs = await SharedPreferences.getInstance();
  if (prefs.getBool(BiometricSessionPrefs.enrollmentDeclinedKey) ?? false) {
    return;
  }

  final bio = BiometricAuth();
  if (!await bio.isAvailable()) {
    return;
  }

  final biometricType = await bio.getBiometricType();
  final label = biometricType.isNotEmpty ? biometricType : 'Huella digital';

  final ctx = (context != null && context.mounted)
      ? context
      : navigatorKey?.currentContext;
  if (ctx == null || !ctx.mounted) {
    return;
  }

  final result = await BiometricEnrollmentPrompt.show(
    ctx,
    appTitle: appTitle,
    biometricType: label,
  );

  if (result == BiometricEnrollmentResult.skipped) {
    await prefs.setBool(BiometricSessionPrefs.enrollmentDeclinedKey, true);
  }
}
