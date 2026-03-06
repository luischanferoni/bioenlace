import 'package:flutter/material.dart';

import '../services/chat_service.dart';
import 'home_screen.dart';
import 'mis_turnos_screen.dart';
import 'chat_screen.dart';

/// Pantalla principal del paciente con bottom nav: Inicio, Mis turnos, Chat.
class MainScreen extends StatefulWidget {
  final ChatService chatService;
  final String? authToken;

  const MainScreen({
    Key? key,
    required this.chatService,
    this.authToken,
  }) : super(key: key);

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          HomeScreen(
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
            authToken: widget.authToken,
            onIrAChat: () => setState(() => _selectedIndex = 2),
            onIrAMisTurnos: () => setState(() => _selectedIndex = 1),
          ),
          MisTurnosScreen(
            authToken: widget.authToken,
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
          ),
          ChatScreen(chatService: widget.chatService),
        ],
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _selectedIndex,
        onTap: (index) => setState(() => _selectedIndex = index),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home),
            label: 'Inicio',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.calendar_today),
            label: 'Mis turnos',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.chat_bubble_outline),
            label: 'Chat',
          ),
        ],
      ),
    );
  }
}
