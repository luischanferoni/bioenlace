// lib/main.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart'; // Usar LoginScreen del paquete compartido

import 'services/chat_service.dart';
import 'screens/chat_screen.dart';
import 'screens/signup_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  final prefs = await SharedPreferences.getInstance();
  final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
  final userId = prefs.getString('user_id') ?? '';
  final userName = prefs.getString('user_name') ?? '';

  ChatService? chatService;

  if (isLoggedIn) {
    chatService = ChatService(
      currentUserId: userId,
      currentUserName: userName,
    );
  }
  /*    
  // Simulamos un usuario logeado
  final chatService = ChatService(
    currentUserId: 'user123',
    currentUserName: 'Usuario Demo',
  );
  */

  runApp(MyApp(
    isLoggedIn: isLoggedIn,
    chatService: chatService,
  ));
}

class MyApp extends StatelessWidget {
  final bool isLoggedIn;
  final ChatService? chatService;

  const MyApp({Key? key, required this.isLoggedIn, this.chatService}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Chat App',
      theme: AppTheme.lightTheme,
      home: isLoggedIn
          ? ChatScreen(chatService: chatService!)
          : LoginScreen(
              appTitle: 'Bienvenido a BioEnlace',
              appSubtitle: 'Tu asistente de salud personal',
              // Textos personalizados para la app del paciente
              welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
              signupButtonText: '¿No tienes cuenta? Regístrate aquí',
              goToHomeButtonText: 'Ir al inicio de la app',
              onLoginSuccess: (userId, userName, loginContext) {
                // Crear servicio de chat
                final newChatService = ChatService(
                  currentUserId: userId,
                  currentUserName: userName,
                );
                // Navegar a la pantalla de chat usando el contexto del LoginScreen
                Navigator.pushReplacement(
                  loginContext,
                  MaterialPageRoute(builder: (_) => ChatScreen(chatService: newChatService)),
                );
              },
              onNavigateToSignup: (loginContext) {
                Navigator.push(
                  loginContext,
                  MaterialPageRoute(builder: (_) => SignupScreen()),
                );
              },
              onNavigateToHome: (loginContext) async {
                // Mostrar indicador de carga
                showDialog(
                  context: loginContext,
                  barrierDismissible: false,
                  builder: (context) => Center(
                    child: CircularProgressIndicator(),
                  ),
                );

                try {
                  // Obtener token del paciente con DNI 29486884
                  final tokenResponse = await http.get(
                    Uri.parse('${AppConfig.apiUrl}/auth/generate-test-token?dni=29486884'),
                  ).timeout(Duration(seconds: 10));

                  if (tokenResponse.statusCode == 200) {
                    final responseData = json.decode(tokenResponse.body);
                    
                    if (responseData['success'] == true) {
                      final data = responseData['data'];
                      final token = data['token'] as String;
                      final user = data['user'] as Map<String, dynamic>;
                      final persona = data['persona'] as Map<String, dynamic>;
                      
                      // Guardar datos en SharedPreferences
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('is_logged_in', true);
                      await prefs.setString('auth_token', token);
                      await prefs.setString('user_id', user['id'].toString());
                      await prefs.setString('user_name', '${persona['apellido']}, ${persona['nombre']}');
                      await prefs.setString('dni_detected', persona['documento']);
                      
                      // Cerrar el diálogo de carga
                      Navigator.pop(loginContext);
                      
                      // Crear servicio de chat con los datos del paciente real
                      final chatService = ChatService(
                        currentUserId: user['id'].toString(),
                        currentUserName: '${persona['apellido']}, ${persona['nombre']}',
                      );
                      
                      // Mostrar mensaje de éxito
                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text('Sesión iniciada como ${persona['apellido']}, ${persona['nombre']}'),
                          backgroundColor: AppTheme.successColor,
                          duration: Duration(seconds: 2),
                        ),
                      );
                      
                      // Navegar al ChatScreen con el servicio configurado
                      Navigator.pushReplacement(
                        loginContext,
                        MaterialPageRoute(
                          builder: (_) => ChatScreen(chatService: chatService),
                        ),
                      );
                    } else {
                      // Cerrar el diálogo de carga
                      Navigator.pop(loginContext);
                      
                      // Mostrar error y usar modo visitante como fallback
                      ScaffoldMessenger.of(loginContext).showSnackBar(
                        SnackBar(
                          content: Text('No se pudo obtener token. Usando modo visitante.'),
                          backgroundColor: AppTheme.warningColor,
                          duration: Duration(seconds: 3),
                        ),
                      );
                      
                      final demoChatService = ChatService(
                        currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                        currentUserName: 'Visitante',
                      );
                      
                      Navigator.pushReplacement(
                        loginContext,
                        MaterialPageRoute(
                          builder: (_) => ChatScreen(chatService: demoChatService),
                        ),
                      );
                    }
                  } else {
                    // Cerrar el diálogo de carga
                    Navigator.pop(loginContext);
                    
                    // Mostrar error y usar modo visitante como fallback
                    ScaffoldMessenger.of(loginContext).showSnackBar(
                      SnackBar(
                        content: Text('Error al conectar con el servidor. Usando modo visitante.'),
                        backgroundColor: AppTheme.warningColor,
                        duration: Duration(seconds: 3),
                      ),
                    );
                    
                    final demoChatService = ChatService(
                      currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                      currentUserName: 'Visitante',
                    );
                    
                    Navigator.pushReplacement(
                      loginContext,
                      MaterialPageRoute(
                        builder: (_) => ChatScreen(chatService: demoChatService),
                      ),
                    );
                  }
                } catch (e) {
                  // Cerrar el diálogo de carga si aún está abierto
                  if (Navigator.canPop(loginContext)) {
                    Navigator.pop(loginContext);
                  }
                  
                  // Mostrar error y usar modo visitante como fallback
                  ScaffoldMessenger.of(loginContext).showSnackBar(
                    SnackBar(
                      content: Text('Error: ${e.toString()}. Usando modo visitante.'),
                      backgroundColor: AppTheme.warningColor,
                      duration: Duration(seconds: 3),
                    ),
                  );
                  
                  final demoChatService = ChatService(
                    currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                    currentUserName: 'Visitante',
                  );
                  
                  Navigator.pushReplacement(
                    loginContext,
                    MaterialPageRoute(
                      builder: (_) => ChatScreen(chatService: demoChatService),
                    ),
                  );
                }
              },
            ),
    );
  }
}