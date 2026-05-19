import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/notificaciones_service.dart';
import '../services/push_notification_service.dart';
import '../utils/turno_resolucion_utils.dart';
import 'alertas_screen.dart';
import 'home_screen.dart';
import 'chat_screen.dart';
import 'configuracion_screen.dart';

/// Pantalla principal del paciente con bottom nav: Inicio, Asistente, Configuración.
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
  PendingTurnoResolver? _pendingResolver;
  int _alertasNoLeidas = 0;
  final GlobalKey<HomeScreenState> _homeKey = GlobalKey<HomeScreenState>();

  @override
  void initState() {
    super.initState();
    _initPush();
    _refreshAlertasCount();
  }

  Future<void> _initPush() async {
    await PushNotificationService.instance.init(
      onOpen: (data) {
        final stub = PushNotificationService.turnoStubDesdePush(data);
        if (stub != null) {
          _abrirResolverTurno(stub);
        } else {
          _openAlertas();
        }
      },
    );
    await PushNotificationService.instance.registerTokenIfLoggedIn();
  }

  Future<void> _refreshAlertasCount() async {
    final svc = NotificacionesService(authToken: widget.authToken);
    final r = await svc.listar(soloNoLeidas: true, limit: 1);
    if (!mounted) return;
    if (r['success'] == true) {
      setState(() => _alertasNoLeidas = r['no_leidas'] as int? ?? 0);
    }
  }

  void _abrirResolverTurno(Map<String, dynamic> turno) {
    setState(() {
      _selectedIndex = 1;
      _pendingResolver = PendingTurnoResolver(turno);
    });
  }

  void _onPendingResolverHandled() {
    setState(() => _pendingResolver = null);
    _refreshAlertasCount();
    _homeKey.currentState?.refrescarProximos();
  }

  void _openAlertas() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AlertasScreen(
          authToken: widget.authToken,
          onAbrirResolver: _abrirResolverTurno,
        ),
      ),
    ).then((_) => _refreshAlertasCount());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          HomeScreen(
            key: _homeKey,
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
            authToken: widget.authToken,
            alertasNoLeidas: _alertasNoLeidas,
            onOpenAlertas: _openAlertas,
            onResolverTurno: _abrirResolverTurno,
          ),
          ChatScreen(
            chatService: widget.chatService,
            pendingResolver: _pendingResolver,
            onPendingResolverHandled: _onPendingResolverHandled,
          ),
          ConfiguracionScreen(
            userId: widget.chatService.currentUserId,
            userName: widget.chatService.currentUserName,
            authToken: widget.authToken,
            onOpenAlertas: _openAlertas,
            alertasNoLeidas: _alertasNoLeidas,
          ),
        ],
      ),
      bottomNavigationBar: BioBottomNav(
        currentIndex: _selectedIndex,
        onTap: (index) {
          setState(() => _selectedIndex = index);
          if (index == 0) {
            _refreshAlertasCount();
          }
        },
        items: const [
          BioBottomNavItem(icon: Icons.home_outlined, label: 'Inicio'),
          BioBottomNavItem(icon: Icons.chat_bubble_outline, label: 'Asistente'),
          BioBottomNavItem(icon: Icons.settings_outlined, label: 'Configuración'),
        ],
      ),
    );
  }
}
