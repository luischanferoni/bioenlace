import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/medico_dev_config.dart';
import 'medico_post_login.dart';
import 'medico_session_prefs.dart';

/// «Ir al inicio» dev: token de prueba → wizard (misma regla que login real).
Future<void> navigateMedicoDevHome(BuildContext loginContext) async {
  final result = await withDevLoginLoading(
    loginContext,
    () => fetchDevTestSession(
      userId: MedicoDevConfig.testUserId,
      autoPes: true,
    ),
  );

  if (!loginContext.mounted || result == null) return;

  if (!result.success || result.payload == null) {
    showDevLoginError(
      loginContext,
      result.errorMessage ?? 'No se pudo simular el acceso de prueba.',
    );
    return;
  }

  final payload = result.payload!;
  await MedicoSessionPrefs.clearOperationalContext(keepAuthToken: false);

  final prefs = await SharedPreferences.getInstance();
  await prefs.setBool('is_logged_in', true);
  await prefs.setString('auth_token', payload.token);
  await prefs.setString('user_id', payload.userId);
  await prefs.setString('user_name', payload.userName);
  if (payload.idPersona != null) {
    await prefs.setInt('id_persona', payload.idPersona!);
  }
  if (payload.documento != null && payload.documento!.isNotEmpty) {
    await prefs.setString('dni_detected', payload.documento!);
  }

  if (loginContext.mounted) {
    showDevLoginSuccess(
      loginContext,
      'Sesión de prueba: ${payload.userName}'
      '${payload.idPersona != null ? ' (persona ${payload.idPersona})' : ''}',
    );
  }

  openMedicoSessionWizard(
    userId: payload.userId,
    userName: payload.userName,
    authToken: payload.token,
  );
}
