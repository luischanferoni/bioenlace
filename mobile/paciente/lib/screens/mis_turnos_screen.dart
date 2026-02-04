import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import '../services/turnos_service.dart';
import 'chat_medico_screen.dart';

/// Pantalla "Mis turnos" del paciente. Muestra turnos con opción de abrir chat si es teleconsulta.
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Mis turnos', style: AppTheme.h2Style.copyWith(color: Colors.white)),
        backgroundColor: Theme.of(context).primaryColor,
        elevation: 0,
      ),
      body: _loading
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
              : _turnos.isEmpty
                  ? Center(
                      child: Text(
                        'No tenés turnos pendientes.',
                        style: AppTheme.subTitleStyle,
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _loadTurnos,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _turnos.length,
                        itemBuilder: (context, index) {
                          final t = _turnos[index] as Map<String, dynamic>;
                          final tipoAtencion = t['tipo_atencion'] as String? ?? 'presencial';
                          final idConsulta = t['id_consulta'];
                          final puedeChat = tipoAtencion == 'teleconsulta' && idConsulta != null;

                          return Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            child: ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              title: Text(
                                '${t['fecha']} · ${t['hora']}',
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (t['servicio'] != null) Text(t['servicio'].toString()),
                                  if (t['profesional'] != null) Text('Con: ${t['profesional']}'),
                                  Text(
                                    tipoAtencion == 'teleconsulta' ? 'Consulta por chat' : 'Presencial',
                                    style: TextStyle(
                                      color: tipoAtencion == 'teleconsulta'
                                          ? AppTheme.primaryColor
                                          : Colors.grey[600],
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                              trailing: puedeChat
                                  ? ElevatedButton.icon(
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
                                    )
                                  : null,
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
