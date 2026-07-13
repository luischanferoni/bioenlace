import 'dart:async';
import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:didit_sdk/sdk_flutter.dart';

import '../auth/biometric_auth.dart';
import '../auth/biometric_session_prefs.dart';
import '../auth/staff_session_auth.dart';
import '../auth/person_display_name.dart';
import '../config/api_config.dart';
import '../config/didit_config_resolver.dart';
import '../http/bioenlace_http_trace.dart';
import '../platform/didit_platform.dart';
import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'privacy_policy_link.dart';
import 'play_review_login_sheet.dart';

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

  // Textos personalizables
  final String? biometricNotAvailableMessage;

  /// Mensaje de bienvenida después del login (acepta `{userName}` como placeholder).
  final String? welcomeMessage;
  final String? authenticatingText;
  final String? biometricUnavailableButtonText;
  final String? signupButtonText;
  final String? biometricAvailableText;

  /// Workflow Didit remoto explícito. Si es null y [diditRemoteLoginAfterLogout] es true,
  /// el workflow se resuelve vía DiditConfigResolver (API o dart-define).
  final String? diditBiometricWorkflowId;

  /// Tras cerrar sesión: verificación remota Didit (selfie + face match) en lugar de
  /// desbloquear una sesión JWT local (que ya no existe).
  final bool diditRemoteLoginAfterLogout;

  /// Cabecera X-App-Client para llamadas API desde esta pantalla.
  final String appClient;

  const LoginScreen({
    super.key,
    this.appTitle = 'Bienvenido a BioEnlace',
    this.appSubtitle = 'Tu asistente de salud personal',
    required this.onLoginSuccess,
    this.onNavigateToSignup,
    this.biometricNotAvailableMessage,
    this.welcomeMessage,
    this.authenticatingText,
    this.biometricUnavailableButtonText,
    this.signupButtonText,
    this.biometricAvailableText,
    this.diditBiometricWorkflowId,
    this.diditRemoteLoginAfterLogout = false,
    this.appClient = 'bioenlace-flutter',
  });

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final BiometricAuth _biometricAuth = BiometricAuth();
  bool _isAuthenticating = false;
  bool _biometricAvailable = false;
  String _biometricType = '';
  int _logoTapCount = 0;
  DateTime? _lastLogoTap;

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

  bool get _usesDiditRemoteLogin {
    if (widget.diditRemoteLoginAfterLogout) return true;
    final id = widget.diditBiometricWorkflowId;
    if (id == null || id.trim().isEmpty) return false;
    return !AppConfig.isDiditWorkflowPlaceholder(id);
  }

  bool get _canAttemptLogin {
    if (_isAuthenticating) return false;
    if (_usesDiditRemoteLogin) return isDiditSupported;
    return _biometricAvailable;
  }

  Future<void> _loginWithBiometrics() async {
    if (!_usesDiditRemoteLogin && !_biometricAvailable) {
      _snack(
        widget.biometricNotAvailableMessage ??
            'La autenticación biométrica no está disponible en este dispositivo',
        UiIntent.warning,
      );
      return;
    }

    if (_usesDiditRemoteLogin && !isDiditSupported) {
      _snack(diditUnsupportedPlatformMessage, UiIntent.warning);
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      // Huella/Face ID del teléfono: desbloquea la sesión guardada en el dispositivo.
      if (!_usesDiditRemoteLogin) {
        final localResult = await _biometricAuth.authenticate(
          'Autenticación con $_biometricType para ingresar a ${widget.appTitle}',
        );

        if (localResult['success'] == true) {
          final prefs = await SharedPreferences.getInstance();
          final token = (prefs.getString('auth_token') ?? '').trim();
          if (token.isEmpty) {
            _snack(
              'Volvé a ingresar con tu cuenta.',
              UiIntent.warning,
            );
            return;
          }

          final sessionCheck = await BearerSessionAuth.checkBearerToken(
            token,
            appClient: widget.appClient,
          );
          if (sessionCheck == BearerSessionCheckResult.invalid) {
            await prefs.setBool('is_logged_in', false);
            await prefs.remove('auth_token');
            _snack(
              'Tu sesión expiró. Ingresá de nuevo.',
              UiIntent.warning,
            );
            return;
          }
          if (sessionCheck == BearerSessionCheckResult.networkError) {
            _snack(
              'No pudimos verificar tu sesión. Revisá la conexión e intentá de nuevo.',
              UiIntent.warning,
            );
            return;
          }

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
            await BiometricSessionPrefs.touchActivity();
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

      final workflowId =
          await DiditConfigResolver.resolvePacienteBiometricWorkflowId();
      if (workflowId == null) {
        _snack(
          'El login biométrico con Didit no está configurado. Contactá soporte.',
          UiIntent.warning,
        );
        return;
      }

      if (!isDiditSupported) {
        _snack(diditUnsupportedPlatformMessage, UiIntent.warning);
        return;
      }

      // 1) Autenticación biométrica remota con Didit.
      final diditResult = await DiditSdk.startVerificationWithWorkflow(
        workflowId,
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
                headers: AppConfig.jsonHeaders(appClient: widget.appClient),
                body: jsonEncode({
                  'biometric_verification_id': session.sessionId,
                  'device_id': deviceId,
                  'platform': platform,
                }),
              )
              .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

          BioenlaceHttpTrace.logResponse('auth/login-biometrico', response);

          final data = jsonDecode(response.body);

          if (response.statusCode >= 200 &&
              response.statusCode < 300 &&
              data['success'] == true) {
            final payload = data['data'] ?? {};
            final user = payload['user'] ?? {};
            final persona = payload['persona'] ?? {};
            final token = payload['token'] as String?;

            final userId = (user['id'] ?? '').toString();
            final userMap = user is Map<String, dynamic>
                ? user
                : Map<String, dynamic>.from(user as Map);
            final personaMap = persona is Map<String, dynamic>
                ? persona
                : Map<String, dynamic>.from(persona as Map);
            final userName = PersonDisplayName.resolveForLogin(
              user: userMap,
              persona: personaMap,
            );

            await prefs.setBool('is_logged_in', true);
            if (token != null) {
              await prefs.setString('auth_token', token);
            }
            await prefs.setString('user_id', userId);
            await prefs.setString('user_name', userName);
            if (userName.isNotEmpty) {
              await prefs.setString('name_detected', userName);
            }
            if (persona['documento'] != null) {
              await prefs.setString('dni_detected', persona['documento']);
            }

            final welcomeMsg =
                widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
            _snack(
              welcomeMsg.replaceAll('{userName}', userName),
              UiIntent.success,
            );

            await Future.delayed(const Duration(milliseconds: 200));
            if (mounted) {
              await BiometricSessionPrefs.touchActivity();
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
          _snack(
            'No se pudo completar la verificación biométrica: ${error.message}',
            UiIntent.danger,
          );
          break;
      }
    } on MissingPluginException {
      _snack(diditMissingPluginMessage, UiIntent.danger);
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

  void _onLogoTap() {
    final now = DateTime.now();
    if (_lastLogoTap == null || now.difference(_lastLogoTap!) > const Duration(seconds: 3)) {
      _logoTapCount = 0;
    }
    _lastLogoTap = now;
    _logoTapCount++;
    if (_logoTapCount >= 5) {
      _logoTapCount = 0;
      PlayReviewLoginSheet.show(
        context,
        appClient: widget.appClient,
        onSuccess: (userId, userName) async {
          if (!mounted) return;
          widget.onLoginSuccess(userId, userName, context);
        },
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;

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
                GestureDetector(
                  onTap: _onLogoTap,
                  child: const BioLogo(height: 56),
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
                if (_usesDiditRemoteLogin) ...[
                  BioAlert.info(
                    message:
                        'Verificá tu identidad con una selfie para volver a entrar.',
                  ),
                  BioSpacing.gapH(BioSpacing.lg),
                ] else if (_biometricAvailable && _biometricType.isNotEmpty) ...[
                  BioAlert.success(
                    message: widget.biometricAvailableText ??
                        '$_biometricType configurada y lista para usar',
                  ),
                  BioSpacing.gapH(BioSpacing.lg),
                ],
                BioButton.primary(
                  label: _resolveLoginLabel(),
                  icon: _usesDiditRemoteLogin
                      ? Icons.face_retouching_natural
                      : Icons.fingerprint,
                  size: BioButtonSize.lg,
                  fullWidth: true,
                  loading: _isAuthenticating,
                  onPressed: _canAttemptLogin ? _loginWithBiometrics : null,
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
                BioSpacing.gapH(BioSpacing.lg),
                const PrivacyPolicyLink(),
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
    if (_usesDiditRemoteLogin) {
      return 'Verificar identidad e ingresar';
    }
    if (_biometricAvailable) {
      return 'Ingresar con $_biometricType';
    }
    return widget.biometricUnavailableButtonText ?? 'Biometría no disponible';
  }
}
