// lib/main.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

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
              onLoginSuccess: (userId, userName) {
                // Crear servicio de chat
                final newChatService = ChatService(
                  currentUserId: userId,
                  currentUserName: userName,
                );
                // Navegar a la pantalla de chat
                Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(builder: (_) => ChatScreen(chatService: newChatService)),
                );
              },
              onNavigateToSignup: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => SignupScreen()),
                );
              },
            ), // Redirige si no está logueado
    );
  }
}