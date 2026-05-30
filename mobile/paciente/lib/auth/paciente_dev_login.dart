import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../screens/main_screen.dart';
import '../services/chat_service.dart';

/// «Ir al inicio»: token de prueba → MainScreen solo si la API responde OK.
Future<void> navigatePacienteDevHome(BuildContext loginContext) async {
  await withDevLoginLoading(loginContext, () async {
    final result = await fetchDevTestSession(autoPes: false);
    if (!loginContext.mounted) return;

    if (!result.success || result.payload == null) {
      showDevLoginError(
        loginContext,
        result.errorMessage ?? 'No se pudo iniciar sesión de prueba.',
      );
      return;
    }

    final payload = result.payload!;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', true);
    await prefs.setString('auth_token', payload.token);
    await prefs.setString('user_id', payload.userId);
    await prefs.setString('user_name', payload.userName);
    if (payload.documento != null && payload.documento!.isNotEmpty) {
      await prefs.setString('dni_detected', payload.documento!);
    }

    if (!loginContext.mounted) return;

    final chatService = ChatService(
      currentUserId: payload.userId,
      currentUserName: payload.userName,
      authToken: payload.token,
    );

    ScaffoldMessenger.of(loginContext).showSnackBar(
      SnackBar(
        content: Text('Sesión iniciada como ${payload.userName}'),
        backgroundColor: IntentPalette.of(UiIntent.success).base,
        duration: const Duration(seconds: 2),
      ),
    );

    Navigator.pushReplacement(
      loginContext,
      MaterialPageRoute(
        builder: (_) => MainScreen(
          chatService: chatService,
          authToken: payload.token,
        ),
      ),
    );
  });
}
