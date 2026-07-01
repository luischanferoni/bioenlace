import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../screens/config_wizard_screen.dart';
import 'personalsalud_authenticated_shell.dart';
import 'personalsalud_session_prefs.dart';

/// Ofrece activar huella/Face ID tras el primer acceso exitoso (login + wizard).
///
/// [context] opcional; si no está montado usa [navigatorKey].
Future<void> maybeOfferPersonalsaludBiometricEnrollment({
  BuildContext? context,
}) async {
  final prefs = await SharedPreferences.getInstance();
  if (prefs.getBool(PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey) ??
      false) {
    return;
  }
  if (prefs.getBool(
        PersonalsaludSessionPrefs.staffBiometricEnrollmentDeclinedKey,
      ) ??
      false) {
    return;
  }
  if (await BiometricSessionPrefs.isUnlockEnabled()) {
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
      : navigatorKey.currentContext;
  if (ctx == null || !ctx.mounted) {
    return;
  }

  final result = await BiometricEnrollmentPrompt.show(
    ctx,
    appTitle: 'Personal de Salud',
    biometricType: label,
  );

  if (result == BiometricEnrollmentResult.success) {
    await prefs.setBool(
      PersonalsaludSessionPrefs.staffMobileLoginEstablishedKey,
      true,
    );
    return;
  }

  if (result == BiometricEnrollmentResult.skipped) {
    await prefs.setBool(
      PersonalsaludSessionPrefs.staffBiometricEnrollmentDeclinedKey,
      true,
    );
  }
}

/// Tras login con credenciales: wizard de efector/servicio/área (sesión operativa).
Future<void> navigatePersonalsaludAfterLogin(
  String userId,
  String userName,
  BuildContext loginContext,
) async {
  final prefs = await SharedPreferences.getInstance();
  final loginToken = prefs.getString('auth_token');

  await PersonalsaludSessionPrefs.clearOperationalContext(keepAuthToken: true);
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('user_id', userId);
  await prefs.setString('user_name', userName);
  await CrashlyticsBootstrap.setUserId(userId);
  if (loginToken != null && loginToken.isNotEmpty) {
    await prefs.setString('auth_token', loginToken);
  }

  if (!loginContext.mounted) return;
  openPersonalsaludSessionWizard(
    userId: userId,
    userName: userName,
    authToken: loginToken,
  );
}

/// Abre el wizard de sesión operativa (efector / servicio / área).
void openPersonalsaludSessionWizard({
  required String userId,
  required String userName,
  String? authToken,
}) {
  final wizard = ConfigWizardScreen(
    userId: userId,
    userName: userName,
    authToken: authToken,
  );

  navigatorKey.currentState?.pushAndRemoveUntil(
    MaterialPageRoute(
      builder: (_) => wrapPersonalsaludAuthenticatedShell(child: wizard),
    ),
    (route) => false,
  );
}

/// Si falta encounter en API o en prefs, limpia contexto y vuelve al wizard.
Future<void> recoverPersonalsaludOperationalSession({
  required String userId,
  required String userName,
  String? authToken,
}) async {
  final prefs = await SharedPreferences.getInstance();
  final token = authToken ?? prefs.getString('auth_token');
  await PersonalsaludSessionPrefs.clearOperationalContext(keepAuthToken: true);
  if (token != null && token.isNotEmpty) {
    await prefs.setString('auth_token', token);
  }
  openPersonalsaludSessionWizard(
    userId: userId,
    userName: userName,
    authToken: token,
  );
}

bool isPersonalsaludEncounterSessionError(Object error) {
  final msg = error.toString().toLowerCase();
  return msg.contains('encounter configurado') ||
      msg.contains('encounter_class') ||
      msg.contains('área de atención configurada');
}
