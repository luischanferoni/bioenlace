import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/registration_service.dart';
import '../theme/paciente_theme_extensions.dart';
import 'chat_screen.dart';

class SignupScreen extends StatefulWidget {
  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _isSubmitting = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSubmitting = true;
    });

    try {
      final registrationService = RegistrationService();
      final result = await registrationService.submitRegistration();

      if (result['success'] == true) {
        final data = result['data'];
        final registro = data['registro'] ?? {};
        final persona = registro['data']?['persona'] ?? {};
        final userId = 'paciente_${persona['id_persona'] ?? DateTime.now().millisecondsSinceEpoch}';

        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim());
        await prefs.setString('dni_detected', persona['documento'] ?? '');
        await prefs.setString('name_detected', '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim());
        await prefs.setBool('biometric_enabled', true);

        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Registro completado exitosamente'),
            backgroundColor: context.pacienteSemantic.success,
          ),
        );

        final chatService = ChatService(
          currentUserId: userId,
          currentUserName: prefs.getString('user_name') ?? 'Usuario',
          authToken: prefs.getString('auth_token'),
        );

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => ChatScreen(chatService: chatService)),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Error en el registro'),
            backgroundColor: context.pacienteColors.error,
            duration: const Duration(seconds: 5),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error inesperado: ${e.toString()}'),
          backgroundColor: context.pacienteColors.error,
          duration: const Duration(seconds: 5),
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    return Scaffold(
      appBar: AppBar(
        iconTheme: IconThemeData(color: cs.onSurface),
        backgroundColor: cs.surface,
        elevation: 0,
      ),
      body: Container(
        color: cs.surface,
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Form(
            key: _formKey,
            child: ListView(
              children: [
                Text(
                  'Completa tu registro',
                  style: tt.headlineSmall?.copyWith(color: cs.primary),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Sube las fotos requeridas para verificar tu identidad',
                  style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                const SizedBox(height: 32),
                Container(
                  width: double.infinity,
                  height: 56,
                  color: cs.surface,
                  child: ElevatedButton(
                    style: _isSubmitting
                        ? ButtonStyles.primarySoft(context).copyWith(
                            backgroundColor: WidgetStateProperty.all(cs.primary.withValues(alpha: 0.3)),
                          )
                        : ButtonStyles.primary(context),
                    onPressed: _isSubmitting ? null : _submit,
                    child: _isSubmitting
                        ? Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(cs.onPrimary),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Text(
                                'Registrando...',
                                style: tt.titleMedium?.copyWith(
                                  color: cs.onPrimary,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          )
                        : const Text('Completar Registro'),
                  ),
                ),
                const SizedBox(height: 24),
                Center(
                  child: TextButton(
                    onPressed: _isSubmitting ? null : _skipRegistration,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.flash_on, size: 16, color: cs.tertiary),
                        const SizedBox(width: 8),
                        Text(
                          'Saltar registro (solo pruebas)',
                          style: tt.bodyMedium?.copyWith(
                            color: cs.tertiary,
                            decoration: TextDecoration.underline,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _skipRegistration() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final testUserId = 'test_user_${DateTime.now().millisecondsSinceEpoch}';
      const testUserName = 'Usuario de Prueba';

      await prefs.setBool('is_logged_in', true);
      await prefs.setString('user_id', testUserId);
      await prefs.setString('user_name', testUserName);
      await prefs.setString('dni_detected', '12345678');
      await prefs.setString('name_detected', testUserName);
      await prefs.setBool('biometric_enabled', false);
      await prefs.setDouble('face_match_score', 0.95);

      final chatService = ChatService(
        currentUserId: testUserId,
        currentUserName: testUserName,
        authToken: null,
      );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Modo de prueba activado - Registro omitido'),
          backgroundColor: context.pacienteSemantic.warning,
          duration: const Duration(seconds: 3),
        ),
      );

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => ChatScreen(chatService: chatService)),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error al saltar registro: ${e.toString()}'),
          backgroundColor: context.pacienteColors.error,
        ),
      );
    }
  }
}
