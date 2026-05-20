import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:didit_sdk/sdk_flutter.dart';

import '../auth/biometric_auth.dart';
import '../config/api_config.dart';
import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';

/// Pantalla de login compartida con design system "papel".
/// Acepta callbacks para navegación personalizada por app (paciente / médico).
class LoginScreen extends StatefulWidget {
  /// Título de la app (ej: "Bienvenido a BioEnlace")
  final String appTitle;

  /// Subtítulo de la app (ej: "Tu asistente de salud personal")
  final String appSubtitle;

  /// Callback cuando el login es exitoso.
  /// Recibe userId, userName y BuildContext del LoginScreen.
  final Function(String userId, String userName, BuildContext context)
      onLoginSuccess;

  /// Callback para navegar a la pantalla de registro.
  /// Recibe BuildContext del LoginScreen.
  final Function(BuildContext context)? onNavigateToSignup;

  /// Callback para navegar al inicio de la app sin registrarse (modo visitante).
  /// Recibe BuildContext del LoginScreen.
  final Function(BuildContext context)? onNavigateToHome;

  // Textos personalizables
  final String? biometricNotAvailableMessage;

  /// Mensaje de bienvenida después del login (acepta `{userName}` como placeholder).
  final String? welcomeMessage;
  final String? authenticatingText;
  final String? biometricUnavailableButtonText;
  final String? signupButtonText;
  final String? goToHomeButtonText;
  final String? biometricAvailableText;

  /// Workflow ID de Didit para autenticación biométrica ("Ya tengo cuenta").
  /// Si es `null`, se usa solo la biometría local del dispositivo.
  final String? diditBiometricWorkflowId;

  const LoginScreen({
    super.key,
    this.appTitle = 'Bienvenido a BioEnlace',
    this.appSubtitle = 'Tu asistente de salud personal',
    required this.onLoginSuccess,
    this.onNavigateToSignup,
    this.onNavigateToHome,
    this.biometricNotAvailableMessage,
    this.welcomeMessage,
    this.authenticatingText,
    this.biometricUnavailableButtonText,
    this.signupButtonText,
    this.goToHomeButtonText,
    this.biometricAvailableText,
    this.diditBiometricWorkflowId,
  });

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final BiometricAuth _biometricAuth = BiometricAuth();
  bool _isAuthenticating = false;
  bool _biometricAvailable = false;
  String _biometricType = '';

  @override
  void initState() {
    super.initState();
    _checkBiometricAvailability();
  }

  Future<void> _checkBiometricAvailability() async {
    final isAvailable = await _biometricAuth.isAvailable();
    final biometricType = await _biometricAuth.getBiometricType();

    if (!mounted) return;
    setState(() {
      _biometricAvailable = isAvailable;
      _biometricType = biometricType;
    });
  }

  void _snack(String message, UiIntent intent) {
    if (!mounted) return;
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: palette.base),
    );
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      _snack(
        widget.biometricNotAvailableMessage ??
            'La autenticación biométrica no está disponible en este dispositivo',
        UiIntent.warning,
      );
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      // Si no hay workflow de Didit configurado, usar solo biometría local como fallback.
      if (widget.diditBiometricWorkflowId == null) {
        final localResult = await _biometricAuth.authenticate(
          'Autenticación con $_biometricType para ingresar a ${widget.appTitle}',
        );

        if (localResult['success'] == true) {
          final prefs = await SharedPreferences.getInstance();
          final userId = prefs.getString('user_id') ??
              'user_${DateTime.now().millisecondsSinceEpoch}';
          final userName = prefs.getString('user_name') ?? 'Usuario';

          final welcomeMsg =
              widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
          _snack(
            welcomeMsg.replaceAll('{userName}', userName),
            UiIntent.success,
          );

          await Future.delayed(const Duration(milliseconds: 200));
          if (mounted) {
            widget.onLoginSuccess(userId, userName, context);
          }
        } else {
          final isUserCancel = localResult['isUserCancel'] == true;
          final error = localResult['error'] as String?;
          if (!isUserCancel && error != null) {
            _snack(error, UiIntent.danger);
          }
        }
        return;
      }

      // 1) Autenticación biométrica remota con Didit.
      final diditResult = await DiditSdk.startVerificationWithWorkflow(
        widget.diditBiometricWorkflowId!,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      );

      switch (diditResult) {
        case VerificationCompleted(:final session):
          // 2) Llamar al backend para login biométrico.
          final prefs = await SharedPreferences.getInstance();
          String deviceId = prefs.getString('device_id') ??
              'device_${DateTime.now().millisecondsSinceEpoch}';
          if (!prefs.containsKey('device_id')) {
            await prefs.setString('device_id', deviceId);
          }

          final platform = Platform.isAndroid
              ? 'android'
              : (Platform.isIOS ? 'ios' : 'otro');

          final uri = Uri.parse('${AppConfig.apiUrl}/auth/login-biometrico');
          final response = await http
              .post(
                uri,
                headers: AppConfig.jsonHeaders(appClient: 'bioenlace-flutter'),
                body: jsonEncode({
                  'biometric_verification_id': session.sessionId,
                  'device_id': deviceId,
                  'platform': platform,
                }),
              )
              .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

          final data = jsonDecode(response.body);

          if (response.statusCode >= 200 &&
              response.statusCode < 300 &&
              data['success'] == true) {
            final payload = data['data'] ?? {};
            final user = payload['user'] ?? {};
            final persona = payload['persona'] ?? {};
            final token = payload['token'] as String?;

            final userId = (user['id'] ?? '').toString();
            final userName = user['name'] ??
                '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

            await prefs.setBool('is_logged_in', true);
            if (token != null) {
              await prefs.setString('auth_token', token);
            }
            await prefs.setString('user_id', userId);
            await prefs.setString('user_name', userName);
            if (persona['documento'] != null) {
              await prefs.setString('dni_detected', persona['documento']);
            }

            // 3) Biometría local opcional como segundo factor.
            final localResult = await _biometricAuth.authenticate(
              'Confirmá con $_biometricType para ingresar a ${widget.appTitle}',
            );
            if (localResult['success'] != true) {
              final error = localResult['error'] as String?;
              if (error != null) {
                _snack(error, UiIntent.danger);
              }
              return;
            }

            final welcomeMsg =
                widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
            _snack(
              welcomeMsg.replaceAll('{userName}', userName),
              UiIntent.success,
            );

            await Future.delayed(const Duration(milliseconds: 200));
            if (mounted) {
              widget.onLoginSuccess(userId, userName, context);
            }
          } else {
            final message = data['message'] ?? 'Error en login biométrico';
            _snack(message.toString(), UiIntent.danger);
          }
          break;

        case VerificationCancelled():
          _snack('Verificación cancelada por el usuario', UiIntent.warning);
          break;

        case VerificationFailed(:final error):
          _snack('Error en Didit: ${error.message}', UiIntent.danger);
          break;
      }
    } catch (e) {
      _snack('Error en la autenticación: ${e.toString()}', UiIntent.danger);
    } finally {
      if (mounted) {
        setState(() {
          _isAuthenticating = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final primary = IntentPalette.of(UiIntent.primary);

    return Scaffold(
      backgroundColor: tokens.paperBackground,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: BioSpacing.pageAll,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    color: primary.softBg,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: primary.border,
                      width: BorderWidth.thin,
                    ),
                  ),
                  child: Icon(
                    Icons.fingerprint,
                    size: 60,
                    color: primary.base,
                  ),
                ),
                BioSpacing.gapH(BioSpacing.xl),
                Text(
                  widget.appTitle,
                  style: BioTypography.h1,
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.sm),
                Text(
                  widget.appSubtitle,
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                  textAlign: TextAlign.center,
                ),
                BioSpacing.gapH(BioSpacing.xxl),
                if (_biometricAvailable && _biometricType.isNotEmpty) ...[
                  BioAlert.success(
                    message: widget.biometricAvailableText ??
                        '$_biometricType configurada y lista para usar',
                  ),
                  BioSpacing.gapH(BioSpacing.lg),
                ],
                BioButton.primary(
                  label: _resolveLoginLabel(),
                  icon: Icons.fingerprint,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _isAuthenticating,
                  onPressed: _biometricAvailable && !_isAuthenticating
                      ? _loginWithBiometrics
                      : null,
                ),
                if (widget.onNavigateToSignup != null) ...[
                  BioSpacing.gapH(BioSpacing.lg),
                  BioButton.softPrimary(
                    label: widget.signupButtonText ??
                        '¿No tienes cuenta? Registrate aquí',
                    icon: Icons.person_add_alt,
                    fullWidth: true,
                    onPressed: () => widget.onNavigateToSignup!(context),
                  ),
                ],
                if (widget.onNavigateToHome != null) ...[
                  BioSpacing.gapH(BioSpacing.md),
                  BioButton(
                    label: widget.goToHomeButtonText ?? 'Ir al inicio de la app',
                    icon: Icons.home_outlined,
                    intent: UiIntent.info,
                    variant: BioButtonVariant.soft,
                    fullWidth: true,
                    onPressed: () => widget.onNavigateToHome!(context),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _resolveLoginLabel() {
    if (_isAuthenticating) {
      return widget.authenticatingText ?? 'Autenticando…';
    }
    if (_biometricAvailable) {
      return 'Ingresar con $_biometricType';
    }
    return widget.biometricUnavailableButtonText ?? 'Biometría no disponible';
  }
}
