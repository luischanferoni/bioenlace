import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'paciente_post_login.dart';

/// User id de desarrollo para JWT rápido (solo `kDebugMode`).
const int kPacienteDebugJwtUserId = 5748;

/// Emite JWT vía `POST /auth/generar-token-prueba` (API solo con YII_DEBUG).
class PacienteDebugJwtAuth {
  PacienteDebugJwtAuth._();

  static Future<Map<String, dynamic>> loginAsUser(int userId) async {
    final uri = Uri.parse('${AppConfig.apiUrl}/auth/generar-token-prueba');
    final response = await http
        .post(
          uri,
          headers: AppConfig.jsonHeaders(
            appClient: BearerSessionAuth.appClientPaciente,
          ),
          body: jsonEncode({
            'user_id': userId,
            'auto_pes': 0,
          }),
        )
        .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

    final decoded = jsonDecode(utf8.decode(response.bodyBytes));
    if (response.statusCode >= 200 &&
        response.statusCode < 300 &&
        decoded is Map &&
        decoded['success'] == true) {
      return Map<String, dynamic>.from(decoded);
    }

    final message = decoded is Map
        ? (decoded['message'] ?? 'No se pudo generar el JWT de prueba')
        : 'No se pudo generar el JWT de prueba';
    throw Exception(message.toString());
  }
}

/// Botón visible solo en builds debug: entra como [kPacienteDebugJwtUserId].
class PacienteDebugJwtLoginButton extends StatefulWidget {
  const PacienteDebugJwtLoginButton({super.key});

  @override
  State<PacienteDebugJwtLoginButton> createState() =>
      _PacienteDebugJwtLoginButtonState();
}

class _PacienteDebugJwtLoginButtonState extends State<PacienteDebugJwtLoginButton> {
  bool _loading = false;

  Future<void> _login() async {
    if (_loading || !kDebugMode) return;
    setState(() => _loading = true);
    try {
      final data = await PacienteDebugJwtAuth.loginAsUser(kPacienteDebugJwtUserId);
      final payload = data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : <String, dynamic>{};
      final user = payload['user'] is Map
          ? Map<String, dynamic>.from(payload['user'] as Map)
          : <String, dynamic>{};
      final persona = payload['persona'] is Map
          ? Map<String, dynamic>.from(payload['persona'] as Map)
          : <String, dynamic>{};
      final token = payload['token']?.toString() ?? '';

      final userId = (user['id'] ?? persona['id_persona'] ?? kPacienteDebugJwtUserId)
          .toString();
      final userName = user['name']?.toString().trim() ??
          '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('is_logged_in', true);
      await prefs.setString('user_id', userId);
      await prefs.setString(
        'user_name',
        userName.isNotEmpty ? userName : 'Debug $userId',
      );
      if (persona['documento'] != null) {
        await prefs.setString('dni_detected', persona['documento'].toString());
      }
      if (token.isNotEmpty) {
        await prefs.setString('auth_token', token);
      }
      // Evita el enrolamiento biométrico obligatorio en el atajo de debug.
      await BiometricSessionPrefs.setUnlockEnabled(true);

      if (!mounted) return;
      await enterPacienteAuthenticatedApp(
        userId: userId,
        userName: userName.isNotEmpty ? userName : 'Debug $userId',
        authToken: token.isNotEmpty ? token : null,
        fallbackContext: context,
        forceBiometricEnrollment: false,
        waitForNativeReturn: false,
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceFirst('Exception: ', '')),
          backgroundColor: IntentPalette.of(UiIntent.danger).base,
        ),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!kDebugMode) return const SizedBox.shrink();
    return BioButton.outlinePrimary(
      label: _loading
          ? 'Generando JWT…'
          : 'Debug JWT (user $kPacienteDebugJwtUserId)',
      icon: Icons.bug_report_outlined,
      fullWidth: true,
      onPressed: _loading ? null : _login,
    );
  }
}
