import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../services/turnos_service.dart';
import 'chat_motivos_screen.dart';

/// Pantalla de inicio del paciente: saludo, próximo turno y acciones rápidas.
class HomeScreen extends StatefulWidget {
  final String userId;
  final String userName;
  final String? authToken;
  final VoidCallback onIrAChat;
  final VoidCallback onIrAMisTurnos;

  const HomeScreen({
    Key? key,
    required this.userId,
    required this.userName,
    this.authToken,
    required this.onIrAChat,
    required this.onIrAMisTurnos,
  }) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late TurnosService _turnosService;
  List<dynamic> _turnos = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _loadTurnos();
  }

  Future<void> _loadTurnos() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _turnosService.getMisTurnos();
    setState(() {
      _loading = false;
      _turnos = result['turnos'] ?? [];
      if (result['success'] != true) _error = result['message'] as String?;
    });
  }

  /// Primer turno futuro (fecha/hora >= ahora) o el primero de la lista.
  Map<String, dynamic>? _getProximoTurno() {
    if (_turnos.isEmpty) return null;
    final now = DateTime.now();
    for (final t in _turnos) {
      final map = t as Map<String, dynamic>;
      final fecha = map['fecha']?.toString();
      final hora = map['hora']?.toString();
      if (fecha != null && hora != null) {
        try {
          final dt = DateTime.parse('$fecha $hora');
          if (dt.isAfter(now) || dt.isAtSameMomentAs(now)) return map;
        } catch (_) {}
      }
    }
    return _turnos.isNotEmpty ? _turnos.first as Map<String, dynamic> : null;
  }

  String _saludo() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Buenos días';
    if (hour < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  @override
  Widget build(BuildContext context) {
    final proximo = _getProximoTurno();
    final idConsulta = proximo != null ? proximo['id_consulta'] : null;
    final puedeCargarMotivos = idConsulta != null;

    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadTurnos,
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 16),
                            ElevatedButton(
                              onPressed: _loadTurnos,
                              child: const Text('Reintentar'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
                      children: [
                        Text(
                          '${_saludo()}, ${widget.userName.split(',').first.trim()}',
                          style: AppTheme.h2Style.copyWith(
                            color: AppTheme.dark,
                            fontSize: 22,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '¿En qué podemos ayudarte?',
                          style: AppTheme.subTitleStyle.copyWith(fontSize: 15),
                        ),
                        const SizedBox(height: 24),
                        if (proximo != null) ...[
                          _buildCardProximoTurno(proximo, puedeCargarMotivos),
                          const SizedBox(height: 24),
                        ] else
                          _buildCardSinTurnos(),
                        const SizedBox(height: 16),
                        _buildSeccionAcciones(puedeCargarMotivos, idConsulta),
                        const SizedBox(height: 24),
                        _buildCardHablarConBioEnlace(),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _buildCardProximoTurno(Map<String, dynamic> t, bool puedeCargarMotivos) {
    final idConsulta = t['id_consulta'];
    return Card(
      elevation: 0,
      color: AppTheme.primaryColor.withOpacity(0.1),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.calendar_today, color: AppTheme.primaryColor, size: 24),
                const SizedBox(width: 10),
                Text(
                  'Tu próximo turno',
                  style: AppTheme.h5Style.copyWith(
                    color: AppTheme.primaryColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '${t['fecha']} · ${t['hora']}',
              style: AppTheme.h4Style,
            ),
            if (t['servicio'] != null)
              Text(t['servicio'].toString(), style: AppTheme.subTitleStyle),
            if (t['profesional'] != null)
              Text('Con: ${t['profesional']}', style: AppTheme.subTitleStyle),
            if (puedeCargarMotivos) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                icon: const Icon(Icons.edit_note, size: 18),
                label: const Text('Cargar motivos de la consulta'),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatMotivosScreen(
                        consultaId: idConsulta as int,
                        authToken: widget.authToken,
                        userId: widget.userId,
                        userName: widget.userName,
                        titulo: 'Motivos · ${t['fecha']} ${t['hora']}',
                      ),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.primaryColor,
                  side: BorderSide(color: AppTheme.primaryColor),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCardSinTurnos() {
    return Card(
      elevation: 0,
      color: Colors.grey.shade100,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: const Padding(
        padding: EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.event_available, color: Colors.grey),
            SizedBox(width: 12),
            Expanded(
              child: Text(
                'No tenés turnos pendientes. Podés pedir uno desde el chat.',
                style: TextStyle(color: Colors.black54, fontSize: 14),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSeccionAcciones(bool puedeCargarMotivos, dynamic idConsulta) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Acciones rápidas',
          style: AppTheme.h5Style.copyWith(
            color: AppTheme.primaryColor,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _buildActionChip(
              icon: Icons.add_circle_outline,
              label: 'Pedir turno',
              onTap: widget.onIrAChat,
            ),
            _buildActionChip(
              icon: Icons.calendar_today,
              label: 'Ver mis turnos',
              onTap: widget.onIrAMisTurnos,
            ),
            if (puedeCargarMotivos && idConsulta != null)
              _buildActionChip(
                icon: Icons.edit_note,
                label: 'Cargar motivos',
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatMotivosScreen(
                        consultaId: idConsulta as int,
                        authToken: widget.authToken,
                        userId: widget.userId,
                        userName: widget.userName,
                        titulo: 'Motivos de la consulta',
                      ),
                    ),
                  );
                },
              ),
          ],
        ),
      ],
    );
  }

  Widget _buildActionChip({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return Material(
      color: AppTheme.primaryColor.withOpacity(0.08),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 20, color: AppTheme.primaryColor),
              const SizedBox(width: 8),
              Text(label, style: TextStyle(color: AppTheme.primaryColor, fontWeight: FontWeight.w500)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCardHablarConBioEnlace() {
    return Material(
      color: AppTheme.primaryColor.withOpacity(0.12),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: widget.onIrAChat,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              CircleAvatar(
                backgroundColor: AppTheme.primaryColor.withOpacity(0.2),
                child: Icon(Icons.chat_bubble_outline, color: AppTheme.primaryColor, size: 28),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Hablar con BioEnlace',
                      style: AppTheme.h5Style.copyWith(
                        color: AppTheme.primaryColor,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Pedí turnos, consultá tus citas o hacé preguntas.',
                      style: AppTheme.subTitleStyle.copyWith(fontSize: 13),
                    ),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_ios, size: 16, color: AppTheme.primaryColor),
            ],
          ),
        ),
      ),
    );
  }
}
