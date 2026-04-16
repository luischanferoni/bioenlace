import 'package:flutter/material.dart';

/// Router de pantallas nativas del asistente (móvil médico).
///
/// El backend envía `client_open.mobile.screen_id` y esta capa lo traduce a una pantalla Flutter.
class NativeScreenRouter {
  static Future<void> open(
    BuildContext context, {
    required String screenId,
    Map<String, dynamic>? args,
    String? title,
  }) async {
    final route = _resolve(screenId, args: args, title: title);
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
    Map<String, dynamic>? args,
    String? title,
  }) {
    final id = screenId.trim().toLowerCase();
    switch (id) {
      case 'agenda.crear':
        return MaterialPageRoute(
          builder: (_) => _PlaceholderNativeScreen(
            title: title ?? 'Agenda laboral',
            body:
                'Obsoleto: usar UI JSON /api/v1/rrhh/editar-agenda (UiJsonWizardScreen). Ajustar el intent o implementar redirección.',
          ),
        );
      default:
        return null;
    }
  }
}

class _PlaceholderNativeScreen extends StatelessWidget {
  final String title;
  final String body;

  const _PlaceholderNativeScreen({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(body),
      ),
    );
  }
}

