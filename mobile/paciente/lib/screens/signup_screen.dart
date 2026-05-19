import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/registration_service.dart';
import 'chat_screen.dart';

/// Registro del paciente: dispara el flujo de verificación de identidad.
class SignupScreen extends StatefulWidget {
  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isSubmitting = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSubmitting = true);

    try {
      final registrationService = RegistrationService();
      final result = await registrationService.submitRegistration();

      if (result['success'] == true) {
        final data = result['data'];
        final registro = data['registro'] ?? {};
        final persona = registro['data']?['persona'] ?? {};
        final userId =
            'paciente_${persona['id_persona'] ?? DateTime.now().millisecondsSinceEpoch}';
        final fullName =
            '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', fullName);
        await prefs.setString('dni_detected', persona['documento'] ?? '');
        await prefs.setString('name_detected', fullName);
        await prefs.setBool('biometric_enabled', true);

        if (!mounted) return;
        _snack('Registro completado exitosamente', UiIntent.success);

        final chatService = ChatService(
          currentUserId: userId,
          currentUserName: prefs.getString('user_name') ?? 'Usuario',
          authToken: prefs.getString('auth_token'),
        );

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => ChatScreen(chatService: chatService),
          ),
        );
      } else {
        if (!mounted) return;
        _snack(result['message'] ?? 'Error en el registro', UiIntent.danger);
      }
    } catch (e) {
      if (!mounted) return;
      _snack('Error inesperado: ${e.toString()}', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
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
      _snack('Modo de prueba activado — Registro omitido', UiIntent.warning);

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ChatScreen(chatService: chatService),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      _snack('Error al saltar registro: ${e.toString()}', UiIntent.danger);
    }
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg, style: TextStyle(color: palette.onBase)),
        backgroundColor: palette.base,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(),
      body: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: BioSpacing.xl,
          vertical: BioSpacing.lg,
        ),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
              Text(
                'Completá tu registro',
                style: BioTypography.h2,
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.sm),
              Text(
                'Subí las fotos requeridas para verificar tu identidad.',
                style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                textAlign: TextAlign.center,
              ),
              BioSpacing.gapH(BioSpacing.xxl),
              BioButton.primary(
                label: _isSubmitting ? 'Registrando…' : 'Completar Registro',
                size: BioButtonSize.lg,
                fullWidth: true,
                loading: _isSubmitting,
                onPressed: _isSubmitting ? null : _submit,
              ),
              BioSpacing.gapH(BioSpacing.lg),
              Center(
                child: BioButton(
                  label: 'Saltar registro (solo pruebas)',
                  intent: UiIntent.warning,
                  variant: BioButtonVariant.soft,
                  size: BioButtonSize.sm,
                  icon: Icons.flash_on,
                  onPressed: _isSubmitting ? null : _skipRegistration,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
