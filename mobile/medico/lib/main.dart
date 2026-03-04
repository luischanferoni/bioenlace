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
      print('[DEBUG] main() - Valores iniciales guardados');
    }
    // Para la app móvil del médico simulamos login biométrico correcto
    await prefs.setBool('is_logged_in', true);
    
    isLoggedIn = true;
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
      home: FutureBuilder<Map<String, dynamic>>(
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
          
          if (!snapshot.hasData) {
            print('[DEBUG] FutureBuilder - No hay datos, usando valores por defecto');
            return const Scaffold(
              body: Center(child: Text('No se pudieron cargar datos de usuario')),
            );
          }
          
          final data = snapshot.data!;
          final authToken = data['authToken'] as String?;
          final configCompleted = data['configCompleted'] as bool? ?? false;
          
          print('[DEBUG] FutureBuilder - authToken: ${authToken != null ? "${authToken.substring(0, authToken.length > 20 ? 20 : authToken.length)}..." : "null"}');
          print('[DEBUG] FutureBuilder - configCompleted: $configCompleted');
          
          // Si la configuración no está completa, mostrar wizard (aunque no haya token, se usa user_id para desarrollo)
          if (!configCompleted) {
            print('[DEBUG] FutureBuilder - Config no completa, mostrando ConfigWizardScreen');
            return ConfigWizardScreen(
              userId: userId,
              userName: userName,
              authToken: authToken,
            );
          }
          
          // Todo configurado, mostrar MainScreen (puede operar en modo desarrollo con user_id)
          print('[DEBUG] FutureBuilder - Todo configurado, mostrando MainScreen');
          return MainScreen(
            userId: userId,
            userName: userName,
            authToken: authToken,
            rrhhId: data['rrhhId'] as String? ?? '7830',
          );
        },
      ),
    );
  }
}
