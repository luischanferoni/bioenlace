// lib/screens/configuracion_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import 'main_screen.dart';
import 'signup_screen.dart';

/// Pantalla de configuración del paciente (perfil, preferencias, cerrar sesión).
class ConfiguracionScreen extends StatelessWidget {
  final String userId;
  final String userName;
  final String? authToken;

  const ConfiguracionScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Configuración'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: ListView(
          padding: const EdgeInsets.all(16.0),
          children: [
            // Información del usuario
            Card(
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Tu cuenta',
                      style: AppTheme.h4Style.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.person, color: AppTheme.primaryColor),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Usuario: $userName',
                            style: AppTheme.h5Style,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.badge, color: AppTheme.primaryColor),
                        const SizedBox(width: 8),
                        Text(
                          'ID: $userId',
                          style: AppTheme.subTitleStyle,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            // Opciones
            Card(
              elevation: 0,
              child: Column(
                children: [
                  ListTile(
                    leading: Icon(Icons.notifications_outlined, color: AppTheme.primaryColor),
                    title: Text('Notificaciones', style: AppTheme.h5Style),
                    subtitle: Text('Recordatorios de turnos', style: AppTheme.subTitleStyle),
                    trailing: Switch(
                      value: true,
                      onChanged: (value) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Próximamente')),
                        );
                      },
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: Icon(Icons.language, color: AppTheme.primaryColor),
                    title: Text('Idioma', style: AppTheme.h5Style),
                    subtitle: Text('Español', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Próximamente')),
                      );
                    },
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: Icon(Icons.dark_mode_outlined, color: AppTheme.primaryColor),
                    title: Text('Tema', style: AppTheme.h5Style),
                    subtitle: Text('Claro', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Próximamente')),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            // Cerrar sesión
            Card(
              elevation: 0,
              child: ListTile(
                leading: Icon(Icons.logout, color: AppTheme.dangerColor),
                title: Text(
                  'Cerrar sesión',
                  style: AppTheme.h5Style.copyWith(
                    color: AppTheme.dangerColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                onTap: () => _confirmarCerrarSesion(context),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmarCerrarSesion(BuildContext context) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Estás seguro de que deseas cerrar sesión?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: AppTheme.dangerColor),
            child: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );

    if (confirm != true || !context.mounted) return;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', false);
    await prefs.remove('user_id');
    await prefs.remove('user_name');
    await prefs.remove('auth_token');

    if (!context.mounted) return;

    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => _buildLoginScreen(context),
      ),
      (route) => false,
    );
  }

  /// Construye la pantalla de login con la misma configuración que en main.dart
  Widget _buildLoginScreen(BuildContext context) {
    return LoginScreen(
      appTitle: 'Bienvenido a BioEnlace',
      appSubtitle: 'Tu asistente de salud personal',
      welcomeMessage: '¡Bienvenido de vuelta, {userName}!',
      signupButtonText: '¿No tienes cuenta? Regístrate aquí',
      goToHomeButtonText: 'Ir al inicio de la app',
      onLoginSuccess: (userId, userName, loginContext) async {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setBool('is_logged_in', true);
        await prefs.setString('user_id', userId);
        await prefs.setString('user_name', userName);
        if (!loginContext.mounted) return;
        final newChatService = ChatService(
          currentUserId: userId,
          currentUserName: userName,
          authToken: prefs.getString('auth_token'),
        );
        Navigator.pushReplacement(
          loginContext,
          MaterialPageRoute(
            builder: (_) => MainScreen(
              chatService: newChatService,
              authToken: prefs.getString('auth_token'),
            ),
          ),
        );
      },
      onNavigateToSignup: (loginContext) {
        Navigator.push(
          loginContext,
          MaterialPageRoute(builder: (_) => SignupScreen()),
        );
      },
      onNavigateToHome: (loginContext) async {
        ScaffoldMessenger.of(loginContext).showSnackBar(
          const SnackBar(
            content: Text('Usá el botón "Ir al inicio" desde la pantalla de login.'),
          ),
        );
      },
    );
  }
}
