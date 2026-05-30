import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../theme/tokens/ui_intent.dart';

/// Datos de sesión devueltos por `/auth/generar-token-prueba`.
class DevTestSessionPayload {
  const DevTestSessionPayload({
    required this.token,
    required this.userId,
    required this.userName,
    this.idPersona,
    this.documento,
    this.sesionOperativa,
    this.pesResuelto,
    this.encounterClass,
  });

  final String token;
  final String userId;
  final String userName;
  final int? idPersona;
  final String? documento;
  final Map<String, dynamic>? sesionOperativa;
  final Map<String, dynamic>? pesResuelto;
  final String? encounterClass;

  bool get tieneSesionOperativaCompleta =>
      encounterClass != null && encounterClass!.isNotEmpty;

  factory DevTestSessionPayload.fromApiData(Map<String, dynamic> data) {
    final user = data['user'] as Map<String, dynamic>;
    final persona = data['persona'] as Map<String, dynamic>;
    final sesion = data['sesion_operativa'];
    final pes = data['pes_resuelto'];
    final displayName = '${persona['apellido']}, ${persona['nombre']}';
    String? encounterClass;
    if (sesion is Map<String, dynamic>) {
      encounterClass = sesion['encounter_class']?.toString();
    }

    return DevTestSessionPayload(
      token: data['token'] as String,
      userId: user['id'].toString(),
      userName: displayName,
      idPersona: (persona['id_persona'] as num?)?.toInt(),
      documento: persona['documento']?.toString(),
      sesionOperativa: sesion is Map<String, dynamic> ? sesion : null,
      pesResuelto: pes is Map<String, dynamic> ? pes : null,
      encounterClass: encounterClass,
    );
  }
}

class DevTestSessionResult {
  const DevTestSessionResult.success(this.payload)
      : errorMessage = null,
        success = true;

  const DevTestSessionResult.failure(this.errorMessage)
      : payload = null,
        success = false;

  final bool success;
  final DevTestSessionPayload? payload;
  final String? errorMessage;
}

/// Token de prueba vía API (sin user_id hardcodeado en código fuente).
Future<DevTestSessionResult> fetchDevTestSession({
  bool autoPes = false,
  String? userIdOverride,
}) async {
  final userId = (userIdOverride ?? AppConfig.devTestUserId).trim();
  if (userId.isEmpty) {
    return const DevTestSessionResult.failure(
      'Usuario de prueba no configurado. Definí DEV_TEST_USER_ID al compilar '
      '(p. ej. --dart-define=DEV_TEST_USER_ID=5749).',
    );
  }

  final uri = Uri.parse('${AppConfig.apiUrl}/auth/generar-token-prueba').replace(
    queryParameters: {
      'user_id': userId,
      'auto_pes': autoPes ? '1' : '0',
    },
  );

  try {
    final response = await http
        .get(uri)
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    Map<String, dynamic>? body;
    try {
      final decoded = json.decode(response.body);
      if (decoded is Map<String, dynamic>) {
        body = decoded;
      }
    } catch (_) {
      // cuerpo no JSON
    }

    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        body?['success'] == true &&
        body?['data'] is Map<String, dynamic>) {
      return DevTestSessionResult.success(
        DevTestSessionPayload.fromApiData(body!['data'] as Map<String, dynamic>),
      );
    }

    final message = body?['message']?.toString();
    if (message != null && message.isNotEmpty) {
      return DevTestSessionResult.failure(message);
    }
    return DevTestSessionResult.failure(
      'No se pudo obtener token de prueba (HTTP ${response.statusCode}).',
    );
  } catch (e) {
    return DevTestSessionResult.failure('Error de conexión: $e');
  }
}

Future<T?> withDevLoginLoading<T>(
  BuildContext context,
  Future<T> Function() action,
) async {
  showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (context) => const Center(child: CircularProgressIndicator()),
  );
  try {
    return await action();
  } finally {
    if (context.mounted && Navigator.canPop(context)) {
      Navigator.pop(context);
    }
  }
}

void showDevLoginError(BuildContext context, String message) {
  if (!context.mounted) return;
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(message),
      backgroundColor: IntentPalette.of(UiIntent.danger).base,
      duration: const Duration(seconds: 5),
    ),
  );
}
