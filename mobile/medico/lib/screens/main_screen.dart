// lib/screens/main_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import 'home_screen.dart';
import 'acciones_screen.dart';
import 'configuracion_screen.dart';

class MainScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final String? rrhhId;

  const MainScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    this.rrhhId,
  }) : super(key: key);

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _selectedIndex = 0;

  final List<Widget> _screens = [];

  @override
  void initState() {
    super.initState();
    // Inicializar las pantallas
    _screens.addAll([
      HomeScreen(
        userId: widget.userId,
        userName: widget.userName,
        authToken: widget.authToken,
        rrhhId: widget.rrhhId,
      ),
      AccionesScreen(
        userId: widget.userId,
        userName: widget.userName,
      ),
      ConfiguracionScreen(
        userId: widget.userId,
        userName: widget.userName,
      ),
    ]);
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
        children: _screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _selectedIndex,
        onTap: _onItemTapped,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppTheme.primaryColor,
        unselectedItemColor: AppTheme.secondaryColor,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home),
            label: 'Inicio',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.bolt),
            label: 'Acciones',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.settings),
            label: 'Configuración',
          ),
        ],
      ),
    );
  }
}

