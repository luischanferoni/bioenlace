// lib/screens/main_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/push_notification_service.dart';
import '../services/chat_service.dart';
import 'chat_consulta_screen.dart';
import 'home_screen.dart';
import 'chat_screen.dart';
import 'configuracion_screen.dart';

class MainScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final String? idProfesionalEfectorServicio;

  const MainScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.idProfesionalEfectorServicio,
  }) : super(key: key);

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _selectedIndex = 0;
  int _homeRefreshKey = 0;

  @override
  void initState() {
    super.initState();
    _initPush();
  }

  Future<void> _initPush() async {
    await PushNotificationService.instance.init(
      onOpen: (data) {
        final guardiaId = PushNotificationService.guardiaIdDesdePush(data);
        if (guardiaId != null && mounted) {
          setState(() {
            _selectedIndex = 0;
            _homeRefreshKey++;
          });
          return;
        }
        final asyncId = PushNotificationService.asyncConsultaIdDesdePush(data);
        if (asyncId != null && mounted) {
          setState(() => _selectedIndex = 0);
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ChatConsultaScreen(
                consultaId: asyncId,
                authToken: widget.authToken,
                userId: widget.userId,
                userName: widget.userName,
                titulo: 'Consulta clínica por mensaje',
              ),
            ),
          );
        }
      },
    );
    await PushNotificationService.instance.registerTokenIfLoggedIn();
  }

  void _onEncounterChanged() {
    setState(() {
      _homeRefreshKey++;
    });
  }

  List<Widget> _buildScreens() {
    final chatService = ChatService(
      currentUserId: widget.userId,
      currentUserName: widget.userName,
      authToken: widget.authToken,
    );
    return [
      HomeScreen(
        key: ValueKey('home_$_homeRefreshKey'),
        userId: widget.userId,
        userName: widget.userName,
        authToken: widget.authToken,
        idProfesionalEfectorServicio: widget.idProfesionalEfectorServicio,
      ),
      ChatScreen(chatService: chatService),
      ConfiguracionScreen(
        userId: widget.userId,
        userName: widget.userName,
        authToken: widget.authToken,
        onEncounterChanged: _onEncounterChanged,
      ),
    ];
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: _buildScreens(),
      ),
      bottomNavigationBar: BioBottomNav(
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        items: const [
          BioBottomNavItem(icon: Icons.home_outlined, label: 'Inicio'),
          BioBottomNavItem(icon: Icons.chat_bubble_outline, label: 'Asistente'),
          BioBottomNavItem(icon: Icons.settings_outlined, label: 'Configuración'),
        ],
      ),
    );
  }
}

