// lib/screens/configuracion_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../main.dart';
import 'main_screen.dart';

class ConfiguracionScreen extends StatelessWidget {
  final String userId;
  final String userName;

  const ConfiguracionScreen({
    Key? key,
    required this.userId,
    required this.userName,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Configuración',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: AppTheme.primaryColor,
        elevation: 0,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: ListView(
          padding: const EdgeInsets.all(16.0),
          children: [
            // Información del usuario
            Card(
              elevation: 2,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Información del Usuario',
                      style: AppTheme.h4Style.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.person, color: AppTheme.primaryColor),
                        const SizedBox(width: 8),
                        Text(
                          'Usuario: $userName',
                          style: AppTheme.h5Style,
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
            
            // Opciones de configuración
            Card(
              elevation: 2,
              child: Column(
                children: [
                  ListTile(
                    leading: Icon(Icons.notifications, color: AppTheme.primaryColor),
                    title: Text('Notificaciones', style: AppTheme.h5Style),
                    subtitle: Text('Gestionar notificaciones', style: AppTheme.subTitleStyle),
                    trailing: Switch(
                      value: true,
                      onChanged: (value) {
                        // TODO: Implementar gestión de notificaciones
                      },
                    ),
                  ),
                  const Divider(),
                  ListTile(
                    leading: Icon(Icons.language, color: AppTheme.primaryColor),
                    title: Text('Idioma', style: AppTheme.h5Style),
                    subtitle: Text('Español', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Funcionalidad en desarrollo'),
                        ),
                      );
                    },
                  ),
                  const Divider(),
                  ListTile(
                    leading: Icon(Icons.dark_mode, color: AppTheme.primaryColor),
                    title: Text('Tema', style: AppTheme.h5Style),
                    subtitle: Text('Claro', style: AppTheme.subTitleStyle),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Funcionalidad en desarrollo'),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Cerrar sesión
            Card(
              elevation: 2,
              child: ListTile(
                leading: Icon(Icons.logout, color: AppTheme.dangerColor),
                title: Text(
                  'Cerrar Sesión',
                  style: AppTheme.h5Style.copyWith(
                    color: AppTheme.dangerColor,
                  ),
                ),
                onTap: () async {
                  // Confirmar cierre de sesión
                  final confirm = await showDialog<bool>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Cerrar Sesión'),
                      content: const Text('¿Estás seguro de que deseas cerrar sesión?'),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Cancelar'),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(context, true),
                          style: TextButton.styleFrom(
                            foregroundColor: AppTheme.dangerColor,
                          ),
                          child: const Text('Cerrar Sesión'),
                        ),
                      ],
                    ),
                  );
                  
                  if (confirm == true) {
                    final prefs = await SharedPreferences.getInstance();
                    await prefs.setBool('is_logged_in', false);
                    await prefs.remove('user_id');
                    await prefs.remove('user_name');
                    
                    // Navegar al login usando navigatorKey
                    navigatorKey.currentState?.pushReplacement(
                      MaterialPageRoute(
                        builder: (_) => LoginScreen(
                          appTitle: 'Bienvenido a BioEnlace Médico',
                          appSubtitle: 'Tu plataforma de gestión médica',
                          onLoginSuccess: (userId, userName, loginContext) async {
                            final prefs = await SharedPreferences.getInstance();
                            await prefs.setBool('is_logged_in', true);
                            await prefs.setString('user_id', userId);
                            await prefs.setString('user_name', userName);
                            
                            navigatorKey.currentState?.pushReplacement(
                              MaterialPageRoute(
                                builder: (_) => MainScreen(
                                  userId: userId,
                                  userName: userName,
                                  authToken: prefs.getString('auth_token'),
                                  rrhhId: prefs.getString('rrhh_id'),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    );
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

