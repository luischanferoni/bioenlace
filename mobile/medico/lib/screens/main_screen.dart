// lib/screens/main_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/push_notification_service.dart';
import 'home_screen.dart';
import 'acciones_screen.dart';
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
    return [
      HomeScreen(
        key: ValueKey('home_$_homeRefreshKey'),
        userId: widget.userId,
        userName: widget.userName,
        authToken: widget.authToken,
        idProfesionalEfectorServicio: widget.idProfesionalEfectorServicio,
      ),
      AccionesScreen(
        userId: widget.userId,
        userName: widget.userName,
        authToken: widget.authToken,
      ),
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
          BioBottomNavItem(icon: Icons.bolt_outlined, label: 'Acciones'),
          BioBottomNavItem(icon: Icons.settings_outlined, label: 'Configuración'),
        ],
      ),
    );
  }
}

