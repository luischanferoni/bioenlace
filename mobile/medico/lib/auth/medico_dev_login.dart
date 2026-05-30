import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../screens/config_wizard_screen.dart';
import '../screens/main_screen.dart';

/// «Ir al inicio»: token de prueba → wizard o MainScreen solo si la API responde OK.
Future<void> navigateMedicoDevHome(BuildContext loginContext) async {
  await withDevLoginLoading(loginContext, () async {
    final result = await fetchDevTestSession(autoPes: true);
    if (!loginContext.mounted) return;

    if (!result.success || result.payload == null) {
      showDevLoginError(
        loginContext,
        result.errorMessage ?? 'No se pudo simular el acceso de prueba.',
      );
      return;
    }

    final payload = result.payload!;
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

    final sesion = payload.sesionOperativa;
    final pesResuelto = payload.pesResuelto;
    if (sesion != null) {
      final pes = sesion['id_profesional_efector_servicio'];
      if (pes != null) {
        await prefs.setInt('id_profesional_efector_servicio', (pes as num).toInt());
      }
      final ef = sesion['id_efector'];
      if (ef != null) {
        await prefs.setInt('id_efector', (ef as num).toInt());
      }
    } else if (pesResuelto != null) {
      final pes = pesResuelto['id'];
      if (pes != null) {
        await prefs.setInt('id_profesional_efector_servicio', (pes as num).toInt());
      }
      final ef = pesResuelto['id_efector'];
      if (ef != null) {
        await prefs.setInt('id_efector', (ef as num).toInt());
      }
    }

    final tieneSesionCompleta = payload.tieneSesionOperativaCompleta;
    if (tieneSesionCompleta) {
      await prefs.setString('encounter_class', payload.encounterClass!);
    } else {
      await prefs.remove('encounter_class');
      await prefs.remove('encounter_class_label');
    }
    await prefs.setBool('config_completed', tieneSesionCompleta);

    if (!loginContext.mounted) return;

    ScaffoldMessenger.of(loginContext).showSnackBar(
      SnackBar(
        content: Text(
          'Sesión de prueba: ${payload.userName}'
          '${payload.idPersona != null ? ' (persona ${payload.idPersona})' : ''}',
        ),
        backgroundColor: IntentPalette.of(UiIntent.success).base,
        duration: const Duration(seconds: 2),
      ),
    );

    if (tieneSesionCompleta) {
      Navigator.pushReplacement(
        loginContext,
        MaterialPageRoute(
          builder: (_) => MainScreen(
            userId: payload.userId,
            userName: payload.userName,
            authToken: payload.token,
            idProfesionalEfectorServicio: prefs
                    .getInt('id_profesional_efector_servicio')
                    ?.toString() ??
                '0',
          ),
        ),
      );
    } else {
      Navigator.pushReplacement(
        loginContext,
        MaterialPageRoute(
          builder: (_) => ConfigWizardScreen(
            userId: payload.userId,
            userName: payload.userName,
            authToken: payload.token,
          ),
        ),
      );
    }
  });
}
