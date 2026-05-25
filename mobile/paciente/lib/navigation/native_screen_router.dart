import 'package:flutter/material.dart';

import '../screens/configuracion_screen.dart';

/// Traduce `client_open.mobile.screen_id` del asistente a pantallas Flutter.
class NativeScreenRouter {
  static Future<void> open(
    BuildContext context, {
    required String screenId,
    required String userId,
    required String userName,
    String? authToken,
    VoidCallback? onOpenAlertas,
    int alertasNoLeidas = 0,
  }) async {
    final route = _resolve(
      screenId,
      userId: userId,
      userName: userName,
      authToken: authToken,
      onOpenAlertas: onOpenAlertas,
      alertasNoLeidas: alertasNoLeidas,
    );
    if (route == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Pantalla no implementada: $screenId')),
      );
      return;
    }
    await Navigator.of(context).push(route);
  }

  static MaterialPageRoute<void>? _resolve(
    String screenId, {
    required String userId,
    required String userName,
    String? authToken,
    VoidCallback? onOpenAlertas,
    int alertasNoLeidas = 0,
  }) {
    switch (screenId.trim().toLowerCase()) {
      case 'care_plan_reminders_settings':
        return MaterialPageRoute(
          builder: (_) => ConfiguracionScreen(
            userId: userId,
            userName: userName,
            authToken: authToken,
            onOpenAlertas: onOpenAlertas,
            alertasNoLeidas: alertasNoLeidas,
          ),
        );
      default:
        return null;
    }
  }
}
