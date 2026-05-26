// lib/main.dart
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'firebase/firebase_bootstrap.dart';
import 'screens/main_screen.dart';
import 'screens/config_wizard_screen.dart';
import 'screens/medico_signup_screen.dart';

/// Usuario de prueba — botón «Ir al inicio». PES/efector los resuelve la API desde BD.
const int _kSimulacionMedicoUserId = 5748;

Uri _simulacionMedicoTokenUri() {
  return Uri.parse('${AppConfig.apiUrl}/auth/generar-token-prueba').replace(
    queryParameters: {'user_id': '$_kSimulacionMedicoUserId'},
  );
}

// Clave global para el Navigator
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

Future<Map<String, dynamic>> _getUserData() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Contexto PES (profesional_efector_servicio) persistido tras el wizard.
    String idProfesionalEfectorServicio = '0';
    final pesInt = prefs.getInt('id_profesional_efector_servicio');
    if (pesInt != null) {
      idProfesionalEfectorServicio = pesInt.toString();
    }
    
    final configCompleted = prefs.getBool('config_completed') ?? false;
    
    print('[DEBUG] _getUserData() - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
    print('[DEBUG] _getUserData() - idProfesionalEfectorServicio: $idProfesionalEfectorServicio');
    print('[DEBUG] _getUserData() - configCompleted: $configCompleted');
    
    return {
      'authToken': authToken,
      'idProfesionalEfectorServicio': idProfesionalEfectorServicio,
      'configCompleted': configCompleted,
    };
  } catch (e) {
    print('[ERROR] _getUserData() - Error: $e');
    // Si hay error, retornar valores por defecto
    return {
      'authToken': null,
      'idProfesionalEfectorServicio': '0',
      'configCompleted': false,
    };
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await FirebaseBootstrap.ensureInitialized();

  // Inicializar formato de fechas localizado para español
  await initializeDateFormatting('es', null);
  
  bool isLoggedIn = false;
  String userId = '';
  String userName = 'Usuario Médico';
  
  try {
    final prefs = await SharedPreferences.getInstance();
    isLoggedIn = prefs.getBool('is_logged_in') ?? false;
    userId = prefs.getString('user_id') ?? '';
    userName = prefs.getString('user_name') ?? 'Usuario Médico';
  } catch (e) {
    // En caso de error, continuar con valores por defecto
    print('[ERROR] main() - Error al inicializar SharedPreferences: $e');
  }

  runApp(MyApp(
    isLoggedIn: isLoggedIn,
    userId: userId,
    userName: userName,
  ));
}

class MyApp extends StatelessWidget {
  final bool isLoggedIn;
  final String userId;
  final String userName;

  const MyApp({
    Key? key,
    required this.isLoggedIn,
    required this.userId,
    required this.userName,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BioEnlace Médico',
      theme: AppTheme.lightTheme,
      navigatorKey: navigatorKey,
      home: isLoggedIn
          ? FutureBuilder<Map<String, dynamic>>(
              future: _getUserData(),
              builder: (context, snapshot) {
                // Manejar estado de conexión
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Scaffold(
                    body: Center(child: CircularProgressIndicator()),
                  );
                }
                
                // Manejar errores
                if (snapshot.hasError) {
                  return Scaffold(
                    body: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.error_outline, size: 64, color: Colors.red),
                          const SizedBox(height: 16),
                          Text('Error al cargar datos: ${snapshot.error}'),
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              // Recargar la app
                              runApp(MyApp(
                                isLoggedIn: isLoggedIn,
                                userId: userId,
                                userName: userName,
                              ));
                            },
                            child: const Text('Reintentar'),
                          ),
                        ],
                      ),
                    ),
                  );
                }
                
                if (!snapshot.hasData) {
                  return const Scaffold(
                    body: Center(child: Text('No se pudieron cargar datos de usuario')),
                  );
                }
                
                final data = snapshot.data!;
                final authToken = data['authToken'] as String?;
                final configCompleted = data['configCompleted'] as bool? ?? false;
                
                // Si la configuración no está completa, mostrar wizard
                if (!configCompleted) {
                  return ConfigWizardScreen(
                    userId: userId,
                    userName: userName,
                    authToken: authToken,
                  );
                }
                
                // Todo configurado, mostrar MainScreen
                return MainScreen(
                  userId: userId,
                  userName: userName,
                  authToken: authToken,
                  idProfesionalEfectorServicio:
                      data['idProfesionalEfectorServicio'] as String? ?? '0',
                );
              },
            )
          : LoginScreen(
              appTitle: 'BioEnlace Médico',
              appSubtitle: 'Acceso para profesionales de la salud',
              welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
              goToHomeButtonText: 'Ir al inicio de la app',
              biometricAvailableText: 'Biometría configurada y lista para usar',
              diditBiometricWorkflowId: AppConfig.diditMedicoBiometricWorkflowId,
              onLoginSuccess: (userId, userName, loginContext) async {
                final prefs = await SharedPreferences.getInstance();
                final token = prefs.getString('auth_token');
                if (!loginContext.mounted) return;
                Navigator.pushReplacement(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => ConfigWizardScreen(
                      userId: userId,
                      userName: userName,
                      authToken: token,
                    ),
                  ),
                );
              },
              onNavigateToSignup: (loginContext) {
                Navigator.push(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => const MedicoSignupScreen(),
                  ),
                );
              },
              onNavigateToHome: (loginContext) async {
                showDialog(
                  context: loginContext,
                  barrierDismissible: false,
                  builder: (context) => const Center(
                    child: CircularProgressIndicator(),
                  ),
                );

                try {
                  final tokenResponse = await http
                      .get(_simulacionMedicoTokenUri())
                      .timeout(const Duration(seconds: 10));

                  if (tokenResponse.statusCode == 200) {
                    final responseData =
                        json.decode(tokenResponse.body) as Map<String, dynamic>;

                    if (responseData['success'] == true) {
                      final data = responseData['data'] as Map<String, dynamic>;
                      final token = data['token'] as String;
                      final user = data['user'] as Map<String, dynamic>;
                      final persona = data['persona'] as Map<String, dynamic>;
                      final displayName =
                          '${persona['apellido']}, ${persona['nombre']}';

                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('auth_token', token);
                      await prefs.setString('user_id', user['id'].toString());
                      final idPersona = (persona['id_persona'] as num?)?.toInt();
                      if (idPersona != null) {
                        await prefs.setInt('id_persona', idPersona);
                      }
                      await prefs.setString('user_name', displayName);
                      if (persona['documento'] != null) {
                        await prefs.setString(
                          'dni_detected',
                          persona['documento'].toString(),
                        );
                      }
                      final sesion = data['sesion_operativa'];
                      final pesResuelto = data['pes_resuelto'];
                      if (sesion is Map<String, dynamic>) {
                        final pes = sesion['id_profesional_efector_servicio'];
                        if (pes != null) {
                          await prefs.setInt(
                            'id_profesional_efector_servicio',
                            (pes as num).toInt(),
                          );
                        }
                        final ef = sesion['id_efector'];
                        if (ef != null) {
                          await prefs.setInt('id_efector', (ef as num).toInt());
                        }
                      } else if (pesResuelto is Map<String, dynamic>) {
                        final pes = pesResuelto['id'];
                        if (pes != null) {
                          await prefs.setInt(
                            'id_profesional_efector_servicio',
                            (pes as num).toInt(),
                          );
                        }
                        final ef = pesResuelto['id_efector'];
                        if (ef != null) {
                          await prefs.setInt('id_efector', (ef as num).toInt());
                        }
                      }
                      // Con PES en el JWT puede omitirse el wizard; si no hay contexto, forzarlo.
                      final tieneContextoOperativo =
                          (sesion is Map &&
                              sesion['id_profesional_efector_servicio'] != null) ||
                          (pesResuelto is Map && pesResuelto['id'] != null);
                      await prefs.setBool('config_completed', tieneContextoOperativo);

                      if (!loginContext.mounted) return;
                      Navigator.pop(loginContext);

                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text(
                            'Sesión de prueba: ${user['name'] ?? displayName}'
                            '${idPersona != null ? ' (persona $idPersona)' : ''}',
                          ),
                          backgroundColor: IntentPalette.of(UiIntent.success).base,
                          duration: const Duration(seconds: 2),
                        ),
                      );

                      if (tieneContextoOperativo) {
                        Navigator.pushReplacement(
                          loginContext,
                          MaterialPageRoute(
                            builder: (_) => MainScreen(
                              userId: user['id'].toString(),
                              userName: displayName,
                              authToken: token,
                              idProfesionalEfectorServicio: prefs
                                      .getInt('id_profesional_efector_servicio')
                                      ?.toString() ??
                                  '0',
                            ),
                          ),
                        );
                      } else {
                        Navigator.pushReplacement(
                          loginContext,
                          MaterialPageRoute(
                            builder: (_) => ConfigWizardScreen(
                              userId: user['id'].toString(),
                              userName: displayName,
                              authToken: token,
                            ),
                          ),
                        );
                      }
                      return;
                    }
                  }

                  if (loginContext.mounted) {
                    Navigator.pop(loginContext);
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: const Text(
                          'No se pudo simular el acceso del médico de prueba. Revisá la API o el user_id.',
                        ),
                        backgroundColor: IntentPalette.of(UiIntent.warning).base,
                        duration: const Duration(seconds: 4),
                      ),
                    );
                  }
                } catch (e) {
                  if (!loginContext.mounted) return;
                  if (Navigator.canPop(loginContext)) {
                    Navigator.pop(loginContext);
                  }
                  ScaffoldMessenger.of(loginContext).showSnackBar(
                    SnackBar(
                      content: Text('Error: $e'),
                      backgroundColor: IntentPalette.of(UiIntent.danger).base,
                      duration: const Duration(seconds: 4),
                    ),
                  );
                }
              },
            ),
    );
  }
}
