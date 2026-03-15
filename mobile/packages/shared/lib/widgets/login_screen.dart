import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:didit_sdk/sdk_flutter.dart';
import '../auth/biometric_auth.dart';
import '../theme/theme.dart';
import '../config/api_config.dart';

/// Pantalla de login compartida que acepta callbacks para navegación personalizada
class LoginScreen extends StatefulWidget {
  /// Título de la app (ej: "Bienvenido a BioEnlace")
  final String appTitle;
  
  /// Subtítulo de la app (ej: "Tu asistente de salud personal")
  final String appSubtitle;
  
  /// Callback cuando el login es exitoso
  /// Recibe userId, userName y BuildContext del LoginScreen
  final Function(String userId, String userName, BuildContext context) onLoginSuccess;
  
  /// Callback para navegar a la pantalla de registro
  /// Recibe BuildContext del LoginScreen
  final Function(BuildContext context)? onNavigateToSignup;
  
  /// Callback para navegar al inicio de la app sin registrarse (modo visitante)
  /// Recibe BuildContext del LoginScreen
  final Function(BuildContext context)? onNavigateToHome;
  
  // Textos personalizables
  /// Mensaje cuando la biometría no está disponible
  final String? biometricNotAvailableMessage;
  
  /// Mensaje de bienvenida después del login (se puede usar {userName} como placeholder)
  final String? welcomeMessage;
  
  /// Texto del botón mientras se autentica
  final String? authenticatingText;
  
  /// Texto del botón cuando la biometría no está disponible
  final String? biometricUnavailableButtonText;
  
  /// Texto del botón de registro
  final String? signupButtonText;
  
  /// Texto del botón para ir al inicio
  final String? goToHomeButtonText;
  
  /// Texto cuando la biometría está disponible
  final String? biometricAvailableText;

  /// Workflow ID de Didit para autenticación biométrica ("Ya tengo cuenta").
  /// Si es null, se usa solo la biometría local del dispositivo.
  final String? diditBiometricWorkflowId;

  /// Callback opcional para una acción de simulación (por ejemplo, crear paciente de prueba).
  final Function(BuildContext context)? onSimulateCreatePaciente;

  const LoginScreen({
    Key? key,
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
    this.onSimulateCreatePaciente,
  }) : super(key: key);

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
    
    setState(() {
      _biometricAvailable = isAvailable;
      _biometricType = biometricType;
    });
  }

  Future<void> _loginWithBiometrics() async {
    if (!_biometricAvailable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(widget.biometricNotAvailableMessage ?? 
              'La autenticación biométrica no está disponible en este dispositivo'),
          backgroundColor: AppTheme.warningColor,
        ),
      );
      return;
    }

    setState(() {
      _isAuthenticating = true;
    });

    try {
      // Si no hay workflow de Didit configurado, usar solo biometría local como fallback
      if (widget.diditBiometricWorkflowId == null) {
        final localResult = await _biometricAuth.authenticate(
          'Autenticación con $_biometricType para ingresar a ${widget.appTitle}',
        );

        if (localResult['success'] == true) {
          final prefs = await SharedPreferences.getInstance();
          final userId = prefs.getString('user_id') ?? 'user_${DateTime.now().millisecondsSinceEpoch}';
          final userName = prefs.getString('user_name') ?? 'Usuario';

          final welcomeMsg = widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(welcomeMsg.replaceAll('{userName}', userName)),
              backgroundColor: AppTheme.successColor,
            ),
          );

          await Future.delayed(const Duration(milliseconds: 200));
          if (mounted) {
            widget.onLoginSuccess(userId, userName, context);
          }
        } else {
          final isUserCancel = localResult['isUserCancel'] == true;
          final error = localResult['error'] as String?;
          if (!isUserCancel && error != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(error),
                backgroundColor: AppTheme.dangerColor,
              ),
            );
          }
        }
        return;
      }

      // 1) Autenticación biométrica remota con Didit
      final diditResult = await DiditSdk.startVerificationWithWorkflow(
        widget.diditBiometricWorkflowId!,
        config: const DiditConfig(
          languageCode: 'es',
          loggingEnabled: true,
        ),
      );

      switch (diditResult) {
        case VerificationCompleted(:final session):
          // 2) Llamar al backend para login biométrico
          final prefs = await SharedPreferences.getInstance();
          String deviceId = prefs.getString('device_id') ??
              'device_${DateTime.now().millisecondsSinceEpoch}';
          if (!prefs.containsKey('device_id')) {
            await prefs.setString('device_id', deviceId);
          }

          final platform = Platform.isAndroid
              ? 'android'
              : (Platform.isIOS ? 'ios' : 'otro');

          final uri = Uri.parse('${AppConfig.apiUrl}/auth/biometric-login');
          final response = await http
              .post(
                uri,
                headers: {'Content-Type': 'application/json'},
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
            final userName =
                user['name'] ?? '${persona['nombre'] ?? ''} ${persona['apellido'] ?? ''}'.trim();

            // Guardar en SharedPreferences
            await prefs.setBool('is_logged_in', true);
            if (token != null) {
              await prefs.setString('auth_token', token);
            }
            await prefs.setString('user_id', userId);
            await prefs.setString('user_name', userName);
            if (persona['documento'] != null) {
              await prefs.setString('dni_detected', persona['documento']);
            }

            // 3) Biometría local opcional como segundo factor
            final localResult = await _biometricAuth.authenticate(
              'Confirma con $_biometricType para ingresar a ${widget.appTitle}',
            );
            if (localResult['success'] != true) {
              final error = localResult['error'] as String?;
              if (error != null) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(error),
                    backgroundColor: AppTheme.dangerColor,
                  ),
                );
              }
              return;
            }

            final welcomeMsg =
                widget.welcomeMessage ?? '¡Bienvenido de vuelta, {userName}!';
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(welcomeMsg.replaceAll('{userName}', userName)),
                backgroundColor: AppTheme.successColor,
              ),
            );

            await Future.delayed(const Duration(milliseconds: 200));
            if (mounted) {
              widget.onLoginSuccess(userId, userName, context);
            }
          } else {
            final message = data['message'] ?? 'Error en login biométrico';
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(message),
                backgroundColor: AppTheme.dangerColor,
              ),
            );
          }
          break;

        case VerificationCancelled():
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Verificación cancelada por el usuario'),
              backgroundColor: AppTheme.warningColor,
            ),
          );
          break;

        case VerificationFailed(:final error):
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Error en Didit: ${error.message}'),
              backgroundColor: AppTheme.dangerColor,
            ),
          );
          break;
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error en la autenticación: ${e.toString()}'),
          backgroundColor: AppTheme.dangerColor,
        ),
      );
    } finally {
      setState(() {
        _isAuthenticating = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        backgroundColor: AppTheme.backgroundColor,
        elevation: 0,
        iconTheme: IconThemeData(color: AppTheme.dark),
      ),
      body: Center(
        child: SingleChildScrollView(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
              // Icono de huella digital grande
              Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.fingerprint,
                  size: 60,
                  color: AppTheme.primaryColor,
                ),
              ),
              
              const SizedBox(height: 32),
              
              // Título principal
              Text(
                widget.appTitle,
                style: AppTheme.h1Style.copyWith(
                  color: AppTheme.dark,
                  fontSize: 28,
                ),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 8),
              
              Text(
                widget.appSubtitle,
                style: AppTheme.subTitleStyle.copyWith(fontSize: 16),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 48),
              
              // Información sobre biometría
              if (_biometricAvailable && _biometricType.isNotEmpty) ...[
                Container(
                  padding: EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.successColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppTheme.successColor.withOpacity(0.3),
                      width: 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle,
                        color: AppTheme.successColor,
                        size: 20,
                      ),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          widget.biometricAvailableText ?? 
                              '$_biometricType configurada y lista para usar',
                          style: AppTheme.h6Style.copyWith(
                            color: AppTheme.successColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
              ],
              
              // Botón principal de autenticación
              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _biometricAvailable 
                        ? AppTheme.primaryColor 
                        : AppTheme.secondaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 2,
                  ),
                  onPressed: _biometricAvailable && !_isAuthenticating 
                      ? _loginWithBiometrics 
                      : null,
                  child: _isAuthenticating
                      ? Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            ),
                            SizedBox(width: 12),
                            Text(
                              widget.authenticatingText ?? 'Autenticando...',
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        )
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.fingerprint, size: 24),
                            SizedBox(width: 12),
                            Text(
                              _biometricAvailable 
                                  ? 'Ingresar con $_biometricType'
                                  : (widget.biometricUnavailableButtonText ?? 'Biometría no disponible'),
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        ),
                ),
              ),
              
              const SizedBox(height: 32),
              
              // Botón de registro (solo si se proporciona callback)
              if (widget.onNavigateToSignup != null)
                TextButton(
                  style: TextButton.styleFrom(
                    foregroundColor: AppTheme.primaryColor,
                  ),
                  child: Text(
                    widget.signupButtonText ?? '¿No tienes cuenta? Regístrate aquí',
                    style: AppTheme.subTitleStyle.copyWith(
                      color: AppTheme.primaryColor,
                      fontSize: 14,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                  onPressed: () {
                    if (widget.onNavigateToSignup != null) {
                      widget.onNavigateToSignup!(context);
                    }
                  },
                ),
              
              // Botón para ir al inicio sin registrarse (solo si se proporciona callback)
              if (widget.onNavigateToHome != null) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      if (widget.onNavigateToHome != null) {
                        widget.onNavigateToHome!(context);
                      }
                    },
                    icon: Icon(Icons.home, size: 22),
                    label: Text(
                      widget.goToHomeButtonText ?? 'Ir al inicio de la app',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.infoColor,
                      foregroundColor: Colors.white,
                      padding: EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      elevation: 3,
                    ),
                  ),
                ),
              ],

              // Botón de simulación de paciente (solo si se proporciona callback)
              if (widget.onSimulateCreatePaciente != null) ...[
                const SizedBox(height: 12),
                TextButton(
                  style: TextButton.styleFrom(
                    foregroundColor: AppTheme.warningColor,
                  ),
                  onPressed: () => widget.onSimulateCreatePaciente!(context),
                  child: Text(
                    'Crear paciente (simulación)',
                    style: AppTheme.subTitleStyle.copyWith(
                      color: AppTheme.warningColor,
                      fontSize: 14,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
        ),
      ),
    );
  }
}

