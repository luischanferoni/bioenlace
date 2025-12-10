// lib/main.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
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
              onNavigateToHome: (loginContext) {
                // Crear servicio de chat en modo visitante/demo
                final demoChatService = ChatService(
                  currentUserId: 'visitor_${DateTime.now().millisecondsSinceEpoch}',
                  currentUserName: 'Visitante',
                );
                // Navegar directamente al ChatScreen (inicio de la app) usando el contexto del LoginScreen
                Navigator.pushReplacement(
                  loginContext,
                  MaterialPageRoute(
                    builder: (_) => ChatScreen(chatService: demoChatService),
                  ),
                );
              },
            ),
    );
  }
}