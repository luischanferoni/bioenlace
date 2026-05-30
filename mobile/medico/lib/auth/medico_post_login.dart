import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../main.dart';
import '../screens/config_wizard_screen.dart';
import 'medico_session_prefs.dart';

/// Tras login biométrico: wizard de efector/servicio/área (sesión operativa).
Future<void> navigateMedicoAfterLogin(
  String userId,
  String userName,
  BuildContext loginContext,
) async {
  final prefs = await SharedPreferences.getInstance();
  final loginToken = prefs.getString('auth_token');

  await MedicoSessionPrefs.clearOperationalContext(keepAuthToken: true);
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('user_id', userId);
  await prefs.setString('user_name', userName);
  if (loginToken != null && loginToken.isNotEmpty) {
    await prefs.setString('auth_token', loginToken);
  }

  if (!loginContext.mounted) return;
  openMedicoSessionWizard(
    userId: userId,
    userName: userName,
    authToken: loginToken,
  );
}

/// Abre el wizard de sesión operativa (efector / servicio / área).
void openMedicoSessionWizard({
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
    MaterialPageRoute(builder: (_) => wizard),
    (route) => false,
  );
}

/// Si falta encounter en API o en prefs, limpia contexto y vuelve al wizard.
Future<void> recoverMedicoOperationalSession({
  required String userId,
  required String userName,
  String? authToken,
}) async {
  final prefs = await SharedPreferences.getInstance();
  final token = authToken ?? prefs.getString('auth_token');
  await MedicoSessionPrefs.clearOperationalContext(keepAuthToken: true);
  if (token != null && token.isNotEmpty) {
    await prefs.setString('auth_token', token);
  }
  openMedicoSessionWizard(
    userId: userId,
    userName: userName,
    authToken: token,
  );
}

bool isMedicoEncounterSessionError(Object error) {
  final msg = error.toString().toLowerCase();
  return msg.contains('encounter configurado') ||
      msg.contains('encounter_class') ||
      msg.contains('área de atención configurada');
}
