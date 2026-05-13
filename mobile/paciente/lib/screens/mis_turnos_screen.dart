import 'package:flutter/material.dart';

import '../services/turnos_service.dart';
import '../theme/paciente_theme_extensions.dart';
import 'chat_medico_screen.dart';
import 'chat_motivos_screen.dart';

/// Pantalla "Mis turnos": próximos e historial con paginación por sección.
class MisTurnosScreen extends StatefulWidget {
  final String? authToken;
  final String userId;
  final String? userName;

  const MisTurnosScreen({
    Key? key,
    required this.authToken,
    required this.userId,
    this.userName,
  }) : super(key: key);

  @override
  State<MisTurnosScreen> createState() => _MisTurnosScreenState();
}

class _MisTurnosScreenState extends State<MisTurnosScreen> {
  static const int _pageLimit = 15;

  late TurnosService _turnosService;
  final List<Map<String, dynamic>> _pendientes = [];
  final List<Map<String, dynamic>> _pasados = [];
  int _totalPendientes = 0;
  int _totalPasados = 0;
  bool _loading = true;
  bool _loadingMasP = false;
  bool _loadingMasPa = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _turnosService = TurnosService(authToken: widget.authToken);
    _cargarInicial();
  }

  bool get _hayMasP => _pendientes.length < _totalPendientes;
  bool get _hayMasPa => _pasados.length < _totalPasados;

  List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<void> _cargarInicial() async {
    setState(() {
      _loading = true;
      _error = null;
      _pendientes.clear();
      _pasados.clear();
    });
    final r1 = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: 0,
    );
    final r2 = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: 0,
    );
    if (!mounted) return;
    if (r1['success'] != true && r2['success'] != true) {
      setState(() {
        _loading = false;
        _error = (r1['message'] ?? r2['message']) as String? ?? 'Error al cargar turnos';
      });
      return;
    }
    setState(() {
      _loading = false;
      if (r1['success'] == true) {
        _pendientes.clear();
        _pendientes.addAll(_asMapList(r1['turnos']));
        _totalPendientes = r1['total'] as int? ?? _pendientes.length;
      }
      if (r2['success'] == true) {
        _pasados.clear();
        _pasados.addAll(_asMapList(r2['turnos']));
        _totalPasados = r2['total'] as int? ?? _pasados.length;
      }
      if (r1['success'] != true) {
        _error = r1['message'] as String?;
      } else if (r2['success'] != true) {
        _error = r2['message'] as String?;
      }
    });
  }

  Future<void> _cargarMasPendientes() async {
    if (_loadingMasP || !_hayMasP) return;
    setState(() => _loadingMasP = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pendientes',
      limit: _pageLimit,
      offset: _pendientes.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasP = false;
      if (r['success'] == true) {
        _pendientes.addAll(_asMapList(r['turnos']));
        _totalPendientes = r['total'] as int? ?? _pendientes.length;
      }
    });
  }

  Future<void> _cargarMasPasados() async {
    if (_loadingMasPa || !_hayMasPa) return;
    setState(() => _loadingMasPa = true);
    final r = await _turnosService.getMisTurnos(
      alcance: 'pasados',
      limit: _pageLimit,
      offset: _pasados.length,
    );
    if (!mounted) return;
    setState(() {
      _loadingMasPa = false;
      if (r['success'] == true) {
        _pasados.addAll(_asMapList(r['turnos']));
        _totalPasados = r['total'] as int? ?? _pasados.length;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis turnos'),
        backgroundColor: cs.primary,
        foregroundColor: cs.onPrimary,
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _pendientes.isEmpty && _pasados.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _cargarInicial,
                          child: const Text('Reintentar'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _cargarInicial,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(
                            _error!,
                            style: tt.bodySmall?.copyWith(color: cs.error),
                          ),
                        ),
                      Text(
                        'Próximos',
                        style: tt.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      if (_pendientes.isEmpty)
                        Text(
                          'No tenés turnos próximos.',
                          style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                        ),
                      ..._pendientes.map((t) => _cardTurno(context, t)),
                      if (_loadingMasP)
                        const Padding(
                          padding: EdgeInsets.all(8),
                          child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                        ),
                      if (_hayMasP && !_loadingMasP)
                        TextButton(
                          onPressed: _cargarMasPendientes,
                          child: const Text('Cargar más'),
                        ),
                      const SizedBox(height: 20),
                      Text(
                        'Anteriores',
                        style: tt.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      if (_pasados.isEmpty)
                        Text(
                          'No hay turnos anteriores.',
                          style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                        ),
                      ..._pasados.map((t) => _cardTurno(context, t)),
                      if (_loadingMasPa)
                        const Padding(
                          padding: EdgeInsets.all(8),
                          child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                        ),
                      if (_hayMasPa && !_loadingMasPa)
                        TextButton(
                          onPressed: _cargarMasPasados,
                          child: const Text('Cargar más'),
                        ),
                    ],
                  ),
                ),
    );
  }

  Widget _cardTurno(BuildContext context, Map<String, dynamic> t) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final tipoAtencion = t['tipo_atencion'] as String? ?? 'presencial';
    final idConsulta = t['id_consulta'];
    final puedeChat = tipoAtencion == 'teleconsulta' && idConsulta != null;
    final puedeMotivos = idConsulta != null;
    final tituloMotivos = 'Motivos · ${t['fecha']} ${t['hora']}';
    final estado = t['estado_label']?.toString() ?? '';

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${t['fecha']} · ${t['hora']}',
              style: tt.titleSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            if (estado.isNotEmpty)
              Text(
                estado,
                style: tt.labelSmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            if (t['servicio'] != null)
              Text(
                t['servicio'].toString(),
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            if (t['profesional'] != null)
              Text(
                'Con: ${t['profesional']}',
                style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
              ),
            Text(
              tipoAtencion == 'teleconsulta' ? 'Consulta por chat' : 'Presencial',
              style: tt.labelSmall?.copyWith(
                color: tipoAtencion == 'teleconsulta' ? cs.primary : cs.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                if (puedeMotivos)
                  OutlinedButton.icon(
                    icon: const Icon(Icons.edit_note, size: 18),
                    label: const Text('Cargar motivos'),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ChatMotivosScreen(
                            consultaId: idConsulta as int,
                            authToken: widget.authToken,
                            userId: widget.userId,
                            userName: widget.userName ?? 'Paciente',
                            titulo: tituloMotivos,
                          ),
                        ),
                      );
                    },
                  ),
                if (puedeChat)
                  ElevatedButton.icon(
                    icon: const Icon(Icons.chat, size: 18),
                    label: const Text('Abrir chat'),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ChatMedicoScreen(
                            consultaId: idConsulta as int,
                            authToken: widget.authToken,
                            userId: widget.userId,
                            userName: widget.userName ?? 'Paciente',
                            titulo: 'Chat con ${t['profesional'] ?? 'médico'}',
                          ),
                        ),
                      );
                    },
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
