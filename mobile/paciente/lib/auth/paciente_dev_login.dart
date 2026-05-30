import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/paciente_dev_config.dart';
import '../screens/main_screen.dart';
import '../services/chat_service.dart';

/// «Ir al inicio»: token de prueba → MainScreen solo si la API responde OK.
Future<void> navigatePacienteDevHome(BuildContext loginContext) async {
  final result = await withDevLoginLoading(
    loginContext,
    () => fetchDevTestSession(
      userId: PacienteDevConfig.testUserId,
      autoPes: false,
    ),
  );

  if (!loginContext.mounted || result == null) return;

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

  final chatService = ChatService(
    currentUserId: payload.userId,
    currentUserName: payload.userName,
    authToken: payload.token,
  );

  showDevLoginSuccess(
    loginContext,
    'Sesión iniciada como ${payload.userName}',
  );

  replaceAppRoot(
    loginContext,
    MainScreen(
      chatService: chatService,
      authToken: payload.token,
    ),
  );
}
