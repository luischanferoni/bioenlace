// lib/screens/acciones_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

class AccionesScreen extends StatefulWidget {
  final String userId;
  final String userName;

  const AccionesScreen({
    Key? key,
    required this.userId,
    required this.userName,
  }) : super(key: key);

  @override
  State<AccionesScreen> createState() => _AccionesScreenState();
}

class _AccionesScreenState extends State<AccionesScreen> {
  final TextEditingController _queryController = TextEditingController();
  bool _isLoading = false;
  String? _responseText;
  List<Map<String, dynamic>>? _actions;

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  Future<void> _sendQuery() async {
    if (_queryController.text.trim().isEmpty) {
      return;
    }

    setState(() {
      _isLoading = true;
      _responseText = null;
      _actions = null;
    });

    // TODO: Implementar llamada a API de acciones/IA
    // Por ahora, simulación
    await Future.delayed(const Duration(seconds: 1));

    setState(() {
      _isLoading = false;
      _responseText = 'Consulta recibida. Funcionalidad en desarrollo.';
      _actions = [];
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Acciones',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: AppTheme.primaryColor,
        elevation: 0,
      ),
      body: Container(
        color: AppTheme.backgroundColor,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Header
              Text(
                'VitaMind',
                style: AppTheme.h1Style.copyWith(
                  color: AppTheme.dark,
                  fontSize: 32,
                  fontWeight: FontWeight.w300,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '¿En qué puedo ayudarte?',
                style: AppTheme.subTitleStyle.copyWith(fontSize: 18),
              ),
              const SizedBox(height: 32),
              
              // Textarea estilo ChatGPT
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: TextField(
                  controller: _queryController,
                  maxLines: 4,
                  decoration: InputDecoration(
                    hintText: 'Escribe tu consulta aquí... Ejemplo: "Necesito buscar una persona" o "Quiero ver los reportes disponibles"',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                    filled: true,
                    fillColor: Colors.white,
                    contentPadding: const EdgeInsets.all(16),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              
              // Botón enviar
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _sendQuery,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        )
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.send),
                            const SizedBox(width: 8),
                            Text(
                              'Enviar',
                              style: AppTheme.h5Style.copyWith(color: Colors.white),
                            ),
                          ],
                        ),
                ),
              ),
              
              // Área de respuesta
              if (_responseText != null || _actions != null) ...[
                const SizedBox(height: 24),
                Card(
                  elevation: 2,
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (_responseText != null)
                          Text(
                            _responseText!,
                            style: AppTheme.subTitleStyle,
                          ),
                        if (_actions != null && _actions!.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text(
                            'Acciones disponibles:',
                            style: AppTheme.h5Style.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          ..._actions!.map((action) => Padding(
                                padding: const EdgeInsets.only(bottom: 8.0),
                                child: ListTile(
                                  leading: const Icon(Icons.arrow_forward),
                                  title: Text(action['title'] ?? 'Acción'),
                                  onTap: () {
                                    // TODO: Implementar acción
                                  },
                                ),
                              )),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
              
              const SizedBox(height: 32),
              
              // Acciones comunes
              Text(
                'Acciones Comunes',
                style: AppTheme.h3Style.copyWith(
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              
              // Grid de acciones comunes (se llenará dinámicamente)
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 2,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                childAspectRatio: 1.2,
                children: [
                  _buildCommonActionCard(
                    context,
                    icon: Icons.search,
                    title: 'Buscar Persona',
                    color: AppTheme.primaryColor,
                    onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Funcionalidad en desarrollo'),
                        ),
                      );
                    },
                  ),
                  _buildCommonActionCard(
                    context,
                    icon: Icons.assessment,
                    title: 'Ver Reportes',
                    color: AppTheme.infoColor,
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
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCommonActionCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 2,
      color: AppTheme.cardColor,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 48,
                color: color,
              ),
              const SizedBox(height: 12),
              Text(
                title,
                style: AppTheme.h6Style.copyWith(
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

