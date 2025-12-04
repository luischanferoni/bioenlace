// lib/main.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'screens/main_screen.dart';
import 'screens/config_wizard_screen.dart';

// Clave global para el Navigator
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

Future<Map<String, dynamic>> _getUserData() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Manejar rrhh_id que puede estar guardado como int o String
    String rrhhId = '7830';
    if (prefs.containsKey('rrhh_id')) {
      // Intentar leer como int primero (como se guarda en config_wizard_screen)
      final rrhhIdInt = prefs.getInt('rrhh_id');
      if (rrhhIdInt != null) {
        rrhhId = rrhhIdInt.toString();
      } else {
        // Si no es int, intentar leer como String
        final rrhhIdStr = prefs.getString('rrhh_id');
        if (rrhhIdStr != null) {
          rrhhId = rrhhIdStr;
        }
      }
    }
    
    final configCompleted = prefs.getBool('config_completed') ?? false;
    
    print('[DEBUG] _getUserData() - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
    print('[DEBUG] _getUserData() - rrhhId: $rrhhId');
    print('[DEBUG] _getUserData() - configCompleted: $configCompleted');
    
    return {
      'authToken': authToken,
      'rrhhId': rrhhId,
      'configCompleted': configCompleted,
    };
  } catch (e) {
    print('[ERROR] _getUserData() - Error: $e');
    // Si hay error, retornar valores por defecto
    return {
      'authToken': null,
      'rrhhId': '7830',
      'configCompleted': false,
    };
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Inicializar formato de fechas localizado para español
  await initializeDateFormatting('es', null);
  
  bool isLoggedIn = false;
  String userId = '5748';
  String userName = 'Usuario Médico';
  
  try {
    final prefs = await SharedPreferences.getInstance();
    
    // Simulación de usuario: id_rrhh=7830, id_user=5748
    // Guardar estos valores si no existen
    if (!prefs.containsKey('user_id')) {
      await prefs.setString('user_id', '5748');
      await prefs.setString('rrhh_id', '7830');
      await prefs.setString('user_name', 'Usuario Médico');
      await prefs.setBool('is_logged_in', false); // No auto-login, requiere autenticación
      print('[DEBUG] main() - Valores iniciales guardados');
    }
    
    isLoggedIn = prefs.getBool('is_logged_in') ?? false;
    userId = prefs.getString('user_id') ?? '5748';
    userName = prefs.getString('user_name') ?? 'Usuario Médico';
    
    print('[DEBUG] main() - isLoggedIn: $isLoggedIn');
    print('[DEBUG] main() - userId: $userId');
    print('[DEBUG] main() - userName: $userName');
    print('[DEBUG] main() - auth_token existe: ${prefs.containsKey('auth_token')}');
    print('[DEBUG] main() - config_completed: ${prefs.getBool('config_completed') ?? false}');
  } catch (e) {
    // Si hay error al inicializar SharedPreferences, usar valores por defecto
    print('[ERROR] main() - Error al inicializar SharedPreferences: $e');
    // Continuar con valores por defecto
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
      home: isLoggedIn && userId.isNotEmpty
          ? FutureBuilder<Map<String, dynamic>>(
              future: _getUserData(),
              builder: (context, snapshot) {
                print('[DEBUG] FutureBuilder - connectionState: ${snapshot.connectionState}');
                print('[DEBUG] FutureBuilder - hasData: ${snapshot.hasData}');
                print('[DEBUG] FutureBuilder - hasError: ${snapshot.hasError}');
                
                // Manejar estado de conexión
                if (snapshot.connectionState == ConnectionState.waiting) {
                  print('[DEBUG] FutureBuilder - Mostrando loading...');
                  return const Scaffold(
                    body: Center(child: CircularProgressIndicator()),
                  );
                }
                
                // Manejar errores
                if (snapshot.hasError) {
                  print('[ERROR] FutureBuilder - Error: ${snapshot.error}');
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
                
                // Si no hay datos, mostrar login
                if (!snapshot.hasData) {
                  print('[DEBUG] FutureBuilder - No hay datos, mostrando LoginScreen');
                  return LoginScreen(
                    appTitle: 'Bienvenido a BioEnlace Médico',
                    appSubtitle: 'Tu plataforma de gestión médica',
                    onLoginSuccess: (_, __) {},
                  );
                }
                
                final data = snapshot.data!;
                final authToken = data['authToken'] as String?;
                final configCompleted = data['configCompleted'] as bool? ?? false;
                
                print('[DEBUG] FutureBuilder - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
                print('[DEBUG] FutureBuilder - configCompleted: $configCompleted');
                
                // Si no hay token, siempre mostrar login (incluso si config está completa)
                if (authToken == null || authToken.isEmpty) {
                  print('[DEBUG] FutureBuilder - No hay authToken, mostrando LoginScreen');
                  return LoginScreen(
                    appTitle: 'Bienvenido a BioEnlace Médico',
                    appSubtitle: 'Tu plataforma de gestión médica',
                    onLoginSuccess: (userId, userName) async {
                      print('[DEBUG] onLoginSuccess (dentro FutureBuilder) - userId: $userId, userName: $userName');
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('user_id', userId);
                      await prefs.setString('user_name', userName);
                      
                      print('[DEBUG] onLoginSuccess (dentro FutureBuilder) - Datos guardados, esperando delay...');
                      // Pequeño delay para asegurar que SharedPreferences se haya guardado completamente
                      await Future.delayed(const Duration(milliseconds: 100));
                      
                      // Reconstruir la app completamente para que lea los nuevos valores
                      final newPrefs = await SharedPreferences.getInstance();
                      final newIsLoggedIn = newPrefs.getBool('is_logged_in') ?? false;
                      final newUserId = newPrefs.getString('user_id') ?? userId;
                      final newUserName = newPrefs.getString('user_name') ?? userName;
                      
                      print('[DEBUG] onLoginSuccess (dentro FutureBuilder) - Reconstruyendo app con isLoggedIn: $newIsLoggedIn');
                      // Reconstruir la app con los nuevos valores
                      runApp(MyApp(
                        isLoggedIn: newIsLoggedIn,
                        userId: newUserId,
                        userName: newUserName,
                      ));
                    },
                  );
                }
                
                // Si la configuración no está completa, mostrar wizard
                if (!configCompleted) {
                  print('[DEBUG] FutureBuilder - Config no completa, mostrando ConfigWizardScreen');
                  return ConfigWizardScreen(
                    userId: userId,
                    userName: userName,
                    authToken: authToken,
                  );
                }
                
                // Todo configurado y con token válido, mostrar MainScreen
                print('[DEBUG] FutureBuilder - Todo configurado, mostrando MainScreen');
                return MainScreen(
                  userId: userId,
                  userName: userName,
                  authToken: authToken,
                  rrhhId: data['rrhhId'] as String? ?? '7830',
                );
              },
            )
          : LoginScreen(
              appTitle: 'Bienvenido a BioEnlace Médico',
              appSubtitle: 'Tu plataforma de gestión médica',
              onLoginSuccess: (userId, userName) async {
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - userId: $userId, userName: $userName');
                // Guardar estado de login
                final prefs = await SharedPreferences.getInstance();
                await prefs.setBool('is_logged_in', true);
                await prefs.setString('user_id', userId);
                await prefs.setString('user_name', userName);
                
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - Datos guardados');
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - auth_token existe: ${prefs.containsKey('auth_token')}');
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - config_completed: ${prefs.getBool('config_completed') ?? false}');
                
                // Pequeño delay para asegurar que SharedPreferences se haya guardado completamente
                await Future.delayed(const Duration(milliseconds: 100));
                
                // Reconstruir la app completamente para que lea los nuevos valores
                // Esto asegura que el MaterialApp se reconstruya con el nuevo estado
                final newPrefs = await SharedPreferences.getInstance();
                final newIsLoggedIn = newPrefs.getBool('is_logged_in') ?? false;
                final newUserId = newPrefs.getString('user_id') ?? userId;
                final newUserName = newPrefs.getString('user_name') ?? userName;
                
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - Reconstruyendo app con isLoggedIn: $newIsLoggedIn');
                print('[DEBUG] onLoginSuccess (LoginScreen inicial) - newUserId: $newUserId, newUserName: $newUserName');
                
                // Reconstruir la app con los nuevos valores
                runApp(MyApp(
                  isLoggedIn: newIsLoggedIn,
                  userId: newUserId,
                  userName: newUserName,
                ));
              },
            ),
    );
  }
}
