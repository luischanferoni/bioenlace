import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';

import '../services/chat_service.dart';
import '../services/acciones_service.dart';
import '../components/dynamic_form.dart';
import 'mis_turnos_screen.dart';

class ChatScreen extends StatefulWidget {
  final ChatService chatService;

  const ChatScreen({Key? key, required this.chatService}) : super(key: key);

  @override
  _ChatScreenState createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  late AccionesService _accionesService;
  List<Map<String, dynamic>> _chatHistory = [];
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _initializeService();
    // Inicializar con mensaje de bienvenida
    _chatHistory = [
      {
        'type': 'bot',
        'content': '¡Hola! Soy tu asistente de BioEnlace. ¿En qué puedo ayudarte?',
        'timestamp': DateTime.now(),
      }
    ];
  }

  Future<void> _initializeService() async {
    // Cargar token desde SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    final authToken = prefs.getString('auth_token');
    
    // Inicializar servicio con el userId y token
    _accionesService = AccionesService(
      userId: widget.chatService.currentUserId,
      authToken: authToken,
    );
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    if (text.isEmpty) return;

    setState(() {
      _isSending = true;

      // Agregar mensaje del usuario al historial
      _chatHistory.add({
        'type': 'user',
        'content': text,
        'timestamp': DateTime.now(),
      });
      _messageController.clear();
    });

    _scrollToBottom();

    try {
      // Procesar consulta con el servicio de acciones
      final result = await _accionesService.processQuery(text);

      if (result['success'] == true) {
        final data = result['data'];
        final explanation = data['explanation'] ?? 'Consulta procesada';
        final actions = data['actions'] ?? (data['action'] != null ? [data['action']] : null);
        final suggestedQuery = data['suggested_query'];
        final queryType = data['query_type'];
        final matchedBy = data['matched_by']; // 'action_id', 'semantic', o null (LLM)
        final needsUserInput = data['needs_user_input'] ?? false;
        final actionAnalysisRaw = data['action_analysis'];
        final actionAnalysis = (actionAnalysisRaw is Map) 
            ? Map<String, dynamic>.from(actionAnalysisRaw) 
            : null;

        setState(() {
          _isSending = false;

          // Agregar respuesta del bot al historial (parameters a nivel raíz para execute-action URL)
          _chatHistory.add({
            'type': 'bot',
            'content': explanation,
            'actions': actions != null && actions.isNotEmpty ? List<Map<String, dynamic>>.from(actions) : null,
            'suggested_query': suggestedQuery,
            'query_type': queryType,
            'matched_by': matchedBy,
            'needs_user_input': needsUserInput,
            'action_analysis': actionAnalysis,
            'parameters': data['parameters'],
            'timestamp': DateTime.now(),
          });
        });

        // Si es una acción directa (action_id o semantic) y solo hay una acción, ejecutarla automáticamente
        // PERO solo si no necesita input del usuario
        if ((matchedBy == 'action_id' || matchedBy == 'semantic') && 
            actions != null && 
            actions.length == 1 && 
            queryType == 'direct_action' &&
            !needsUserInput) {
          // Ejecutar la acción automáticamente después de un breve delay
          Future.delayed(Duration(milliseconds: 500), () {
            _executeAction(actions[0]);
          });
        }
      } else {
        // Si hay explanation en los datos, usarla aunque success sea false (compatibilidad)
        final data = result['data'];
        final explanation = data?['explanation'];
        
        setState(() {
          _isSending = false;
          _chatHistory.add({
            'type': 'bot',
            'content': explanation ?? result['message'] ?? 'Lo siento, no pude procesar tu consulta. Intenta nuevamente.',
            'suggested_query': data?['suggested_query'],
            'timestamp': DateTime.now(),
          });
        });
      }
      _scrollToBottom();
    } catch (e) {
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': 'Error al procesar tu consulta. Por favor, intenta nuevamente.',
          'timestamp': DateTime.now(),
        });
      });
      _showErrorSnackbar('Error: ${e.toString()}');
      _scrollToBottom();
    }
  }

  Future<void> _executeAction(Map<String, dynamic> action) async {
    final actionId = action['action_id'];

    if (actionId == null || actionId.isEmpty) {
      _showErrorSnackbar('Error: No se pudo identificar la acción');
      return;
    }

    // Parámetros ya obtenidos por process-query (ej. id_servicio_asignado) para incluir en la URL execute-action
    Map<String, dynamic> params = {};
    final actionParams = action['parameters'];
    if (actionParams != null && actionParams is Map) {
      final providedRaw = (actionParams as Map<String, dynamic>)['provided'];
      if (providedRaw != null && providedRaw is Map) {
        final provided = providedRaw as Map<String, dynamic>;
        provided.forEach((key, value) {
          if (value != null) params[key] = value;
        });
      }
    }

    // Completar con action_analysis del mensaje en el historial si existe
    for (int i = _chatHistory.length - 1; i >= 0; i--) {
      final message = _chatHistory[i];
      if (message['type'] == 'bot') {
        // Buscar en action_analysis
        final actionAnalysisRaw = message['action_analysis'];
        if (actionAnalysisRaw != null && actionAnalysisRaw is Map) {
          final analysisMap = actionAnalysisRaw as Map<String, dynamic>;
          if (analysisMap['action_id'] == actionId) {
            // Extraer parámetros proporcionados del action_analysis
            final parameters = analysisMap['parameters'];
            if (parameters != null && parameters is Map) {
              final parametersMap = parameters as Map<String, dynamic>;
              final providedParamsRaw = parametersMap['provided'];
              
              // Manejar tanto Map como List (por si acaso)
              if (providedParamsRaw != null) {
                if (providedParamsRaw is Map) {
                  final providedMap = providedParamsRaw as Map<String, dynamic>;
                  providedMap.forEach((key, value) {
                    if (params.containsKey(key)) return; // Prioridad a params de la acción
                    // El valor puede venir como {'value': X, 'source': 'extracted'}
                    if (value is Map) {
                      final valueMap = value as Map<String, dynamic>;
                      // Extraer el valor real del objeto
                      if (valueMap.containsKey('value')) {
                        params[key] = valueMap['value'];
                      } else {
                        params[key] = value;
                      }
                    } else {
                      params[key] = value;
                    }
                  });
                } else if (providedParamsRaw is List) {
                  // Si es una lista, convertir a mapa si es posible
                  for (var item in providedParamsRaw) {
                    if (item is Map) {
                      final itemMap = item as Map<String, dynamic>;
                      final key = itemMap['name'] ?? itemMap['key'];
                      if (key != null && !params.containsKey(key)) {
                        params[key.toString()] = itemMap['value'];
                      }
                    }
                  }
                }
              }
            }
            
            // También buscar en extracted_data si está disponible directamente
            final extractedData = analysisMap['extracted_data'];
            if (extractedData != null && extractedData is Map) {
              final extractedMap = extractedData as Map<String, dynamic>;
              // Agregar id_servicio y servicio_actual si están presentes
              if (extractedMap.containsKey('id_servicio') && !params.containsKey('id_servicio')) {
                params['id_servicio'] = extractedMap['id_servicio'];
              }
              if (extractedMap.containsKey('servicio_actual') && !params.containsKey('servicio_actual')) {
                params['servicio_actual'] = extractedMap['servicio_actual'];
              }
            }
            
            break;
          }
        }
        
        // También buscar en parameters del mensaje (puede venir de form_config previo)
        final messageParameters = message['parameters'];
        if (messageParameters != null && messageParameters is Map) {
          final parametersMap = messageParameters as Map<String, dynamic>;
          final providedParamsRaw = parametersMap['provided'];
          
          if (providedParamsRaw != null && providedParamsRaw is Map) {
            final providedMap = providedParamsRaw as Map<String, dynamic>;
            providedMap.forEach((key, value) {
              // El valor puede venir como {'value': X, 'source': 'extracted'}
              if (value is Map) {
                final valueMap = value as Map<String, dynamic>;
                if (valueMap.containsKey('value') && !params.containsKey(key)) {
                  params[key] = valueMap['value'];
                }
              } else if (!params.containsKey(key)) {
                params[key] = value;
              }
            });
          }
        }
      }
    }
    
    // Debug: imprimir parámetros extraídos (solo en desarrollo)
    print('Parámetros extraídos para $actionId: $params');

    // Obtener configuración del formulario/wizard usando GET
    setState(() {
      _isSending = true;
    });

    try {
      // Llamar a GET para obtener el form_config (wizard)
      final result = await _accionesService.getActionFormConfig(actionId, params: params);

      if (result['success'] == true) {
        final data = result['data'];
        final formConfig = data['form_config'];
        final wizardSteps = data['wizard_steps'];
        final initialStep = data['initial_step'] ?? 0;
        final actionName = data['action_name'];

        setState(() {
          _isSending = false;
        });

        // Si hay formulario, abrirlo directamente en modal (sin crear mensaje en el chat)
        if (formConfig != null && formConfig is Map) {
          _showFormModal(
            context: context,
            formConfig: Map<String, dynamic>.from(formConfig),
            wizardSteps: wizardSteps != null ? List<Map<String, dynamic>>.from(wizardSteps.map((step) => Map<String, dynamic>.from(step))) : null,
            initialStep: initialStep,
            title: actionName ?? 'Completa la información',
            authToken: _accionesService.authToken,
            onSubmit: (formValues) async {
              await _executeActionWithParams(actionId, formValues);
            },
            onCancel: () {},
          );
        }
      } else {
        setState(() {
          _isSending = false;
        });
        // Mostrar alerta flotante en lugar de crear mensaje de chat
        _showErrorSnackbar(result['message'] ?? 'No se pudo obtener la configuración del formulario. Por favor, intente nuevamente más tarde.');
      }
      _scrollToBottom();
    } catch (e) {
      setState(() {
        _isSending = false;
        _chatHistory.add({
          'type': 'bot',
          'content': 'Error al obtener el formulario. Por favor, intenta nuevamente.',
          'timestamp': DateTime.now(),
        });
      });
      _showErrorSnackbar('Error: ${e.toString()}');
      _scrollToBottom();
    }
  }

  Future<void> _executeActionWithParams(String actionId, Map<String, dynamic> params) async {
    setState(() {
      _isSending = true;
    });

    // Log para debug: ver qué parámetros se están enviando
    print('Executing action: $actionId');
    print('Params being sent: $params');
    
    // Filtrar valores nulos o vacíos si es necesario, pero mantener los que tienen valor
    final filteredParams = <String, dynamic>{};
    params.forEach((key, value) {
      if (value != null && value != '') {
        filteredParams[key] = value;
      }
    });
    
    if (filteredParams.isNotEmpty) {
      print('Filtered params: $filteredParams');
    } else {
      print('WARNING: No params to send (all were null or empty)');
    }

    try {
      final result = await _accionesService.executeAction(actionId, params: filteredParams);

      setState(() {
        _isSending = false;
      });

      if (result['success'] == true) {
        final data = result['data'];
        final explanation = data['explanation'] ?? 'Acción ejecutada exitosamente';

        setState(() {
          _chatHistory.add({
            'type': 'bot',
            'content': explanation,
            'data': data,
            'timestamp': DateTime.now(),
          });
        });
      } else {
        _showErrorSnackbar(result['message'] ?? 'Error al ejecutar la acción');
      }
      _scrollToBottom();
    } catch (e) {
      setState(() {
        _isSending = false;
      });
      _showErrorSnackbar('Error: ${e.toString()}');
    }
  }

  /// Despliega el formulario dinámico en un modal (bottom sheet) para móvil.
  void _showFormModal({
    required BuildContext context,
    required Map<String, dynamic> formConfig,
    List<Map<String, dynamic>>? wizardSteps,
    int initialStep = 0,
    required String title,
    String? authToken,
    required Future<void> Function(Map<String, dynamic>) onSubmit,
    required VoidCallback onCancel,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (modalContext) => Container(
        height: MediaQuery.of(modalContext).size.height,
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
        ),
        child: Column(
          children: [
            // Título y cerrar
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      Navigator.of(modalContext).pop();
                      onCancel();
                    },
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            // Formulario con scroll
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: DynamicForm(
                  formConfig: formConfig,
                  wizardSteps: wizardSteps,
                  initialStep: initialStep,
                  authToken: authToken,
                  onSubmit: (formValues) async {
                    Navigator.of(modalContext).pop();
                    await onSubmit(formValues);
                  },
                  onCancel: () {
                    Navigator.of(modalContext).pop();
                    onCancel();
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showErrorSnackbar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        duration: const Duration(seconds: 3),
      )
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'BioEnlace',
          style: AppTheme.h2Style.copyWith(color: Colors.white),
        ),
        backgroundColor: Theme.of(context).primaryColor,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.calendar_today, color: Colors.white),
            tooltip: 'Mis turnos',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => MisTurnosScreen(
                    authToken: _accionesService.authToken,
                    userId: widget.chatService.currentUserId,
                    userName: widget.chatService.currentUserName,
                  ),
                ),
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.symmetric(vertical: 8.0),
              itemCount: _chatHistory.length,
              itemBuilder: (context, index) {
                final message = _chatHistory[index];
                final isUser = message['type'] == 'user';
                final content = message['content'] as String;
                final actions = message['actions'] as List<Map<String, dynamic>>?;
                final suggestedQuery = message['suggested_query'] as String?;
                final timestamp = message['timestamp'] as DateTime;
                final needsUserInput = message['needs_user_input'] as bool? ?? false;
                final actionAnalysisRaw = message['action_analysis'];
                final actionAnalysis = (actionAnalysisRaw is Map) 
                    ? Map<String, dynamic>.from(actionAnalysisRaw) 
                    : null;

                // Verificar si hay form_config para ocultar el mensaje de chat
                final hasFormConfig = message['form_config'] != null;
                
                return Column(
                  children: [
                    // Mensaje (solo mostrar si no hay form_config)
                    if (!hasFormConfig)
                      Align(
                        alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
                        child: Container(
                          margin: const EdgeInsets.symmetric(
                            vertical: 4.0,
                            horizontal: 16.0,
                          ),
                          padding: const EdgeInsets.all(16.0),
                          decoration: BoxDecoration(
                            color: isUser
                                ? Theme.of(context).primaryColor
                                : Colors.grey[200],
                            borderRadius: BorderRadius.circular(16.0),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.1),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (!isUser)
                                Row(
                                  children: [
                                    Icon(
                                      Icons.smart_toy,
                                      size: 16,
                                      color: Theme.of(context).primaryColor,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      'BioEnlace',
                                      style: AppTheme.h6Style.copyWith(
                                        fontWeight: FontWeight.bold,
                                        color: Theme.of(context).primaryColor,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ),
                              if (!isUser) const SizedBox(height: 4),
                              if (content.isNotEmpty)
                                Text(
                                  content,
                                  style: AppTheme.subTitleStyle.copyWith(
                                    color: isUser ? Colors.white : Colors.black87,
                                    fontSize: 14,
                                  ),
                                ),
                              const SizedBox(height: 4),
                              Text(
                                '${timestamp.hour}:${timestamp.minute.toString().padLeft(2, '0')}',
                                style: TextStyle(
                                  fontSize: 10,
                                  color: isUser ? Colors.white70 : Colors.grey[600],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    // Acciones si existen
                    if (!isUser && actions != null && actions.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16.0),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: actions.map((action) {
                            return ActionChip(
                              label: Text(
                                action['display_name'] ?? action['title'] ?? action['label'] ?? 'Acción',
                                style: TextStyle(fontSize: 12),
                              ),
                              avatar: Icon(
                                Icons.touch_app,
                                size: 16,
                              ),
                              onPressed: () => _executeAction(action),
                              backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                              labelStyle: TextStyle(
                                color: Theme.of(context).primaryColor,
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                    // Formulario dinámico si hay form_config (viene de getActionFormConfig)
                    if (!isUser) ...[
                      Builder(
                        builder: (context) {
                          final formConfig = message['form_config'];
                          final wizardSteps = message['wizard_steps'];
                          final actionIdFromMessage = message['action_id'];
                          final actionName = message['action_name'];
                          final parametersFromMessage = message['parameters'];
                          
                          if (formConfig != null && formConfig is Map) {
                            // Card tappable: un solo tap abre directamente el formulario en modal
                            return Column(
                              children: [
                                const SizedBox(height: 16),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                                  child: Material(
                                    color: Colors.blue[50],
                                    borderRadius: BorderRadius.circular(12),
                                    child: InkWell(
                                      onTap: () {
                                        _showFormModal(
                                          context: context,
                                          formConfig: Map<String, dynamic>.from(formConfig),
                                          wizardSteps: wizardSteps != null ? List<Map<String, dynamic>>.from(wizardSteps.map((step) => Map<String, dynamic>.from(step))) : null,
                                          initialStep: message['initial_step'] as int? ?? 0,
                                          title: actionName ?? 'Completa la información',
                                          authToken: _accionesService.authToken,
                                          onSubmit: (formValues) async {
                                            if (actionIdFromMessage != null) {
                                              await _executeActionWithParams(actionIdFromMessage, formValues);
                                            }
                                          },
                                          onCancel: () {
                                            setState(() {
                                              _chatHistory.removeAt(index);
                                            });
                                          },
                                        );
                                      },
                                      borderRadius: BorderRadius.circular(12),
                                      child: Container(
                                        padding: const EdgeInsets.all(16.0),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(
                                            color: Theme.of(context).primaryColor.withOpacity(0.3),
                                          ),
                                        ),
                                        child: Row(
                                          children: [
                                            Icon(
                                              Icons.input,
                                              size: 20,
                                              color: Theme.of(context).primaryColor,
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                actionName ?? 'Completa la información',
                                                style: TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  color: Theme.of(context).primaryColor,
                                                ),
                                              ),
                                            ),
                                            Icon(
                                              Icons.arrow_forward_ios,
                                              size: 14,
                                              color: Theme.of(context).primaryColor,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            );
                          }
                          
                          // Si no hay form_config, verificar si hay actionAnalysis (comportamiento anterior)
                          final actionAnalysis = message['action_analysis'];
                          final needsUserInput = message['needs_user_input'] ?? false;
                          
                          if (needsUserInput && actionAnalysis != null && actionAnalysis is Map) {
                            final actionId = actionAnalysis['action_id'] as String?;
                            return Column(
                              children: [
                                const SizedBox(height: 16),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 16.0),
                                  child: Material(
                                    color: Colors.blue[50],
                                    borderRadius: BorderRadius.circular(12),
                                    child: InkWell(
                                      onTap: () {
                                        _showFormModal(
                                          context: context,
                                          formConfig: Map<String, dynamic>.from(actionAnalysis['form_config'] ?? {}),
                                          title: 'Completa la información',
                                          authToken: _accionesService.authToken,
                                          onSubmit: (formValues) async {
                                            if (actionId != null) {
                                              await _executeActionWithParams(actionId, formValues);
                                            }
                                          },
                                          onCancel: () {
                                            setState(() {
                                              _chatHistory.removeAt(index);
                                            });
                                          },
                                        );
                                      },
                                      borderRadius: BorderRadius.circular(12),
                                      child: Container(
                                        padding: const EdgeInsets.all(16.0),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(
                                            color: Theme.of(context).primaryColor.withOpacity(0.3),
                                          ),
                                        ),
                                        child: Row(
                                          children: [
                                            Icon(
                                              Icons.input,
                                              size: 20,
                                              color: Theme.of(context).primaryColor,
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                'Completa la información',
                                                style: TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  color: Theme.of(context).primaryColor,
                                                ),
                                              ),
                                            ),
                                            Icon(
                                              Icons.arrow_forward_ios,
                                              size: 14,
                                              color: Theme.of(context).primaryColor,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            );
                          }
                          
                          return const SizedBox.shrink();
                        },
                      ),
                    ],
                    // Consulta sugerida si existe
                    if (!isUser && suggestedQuery != null && suggestedQuery.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16.0),
                        child: OutlinedButton.icon(
                          onPressed: () {
                            // Enviar la consulta sugerida automáticamente
                            _messageController.text = suggestedQuery;
                            _sendMessage();
                          },
                          icon: Icon(
                            Icons.lightbulb_outline,
                            size: 16,
                            color: Theme.of(context).primaryColor,
                          ),
                          label: Text(
                            suggestedQuery,
                            style: TextStyle(
                              fontSize: 13,
                              color: Theme.of(context).primaryColor,
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            side: BorderSide(
                              color: Theme.of(context).primaryColor.withOpacity(0.5),
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(20),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                );
              },
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16.0),
            decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.1),
                  blurRadius: 4,
                  offset: const Offset(0, -2),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: InputDecoration(
                      hintText: 'Escribe tu consulta aquí... Ejemplo: "Necesito ver mis consultas" o "Quiero agendar un turno"',
                      hintStyle: AppTheme.subTitleStyle,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide(
                          color: Theme.of(context).primaryColor.withOpacity(0.3),
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide(
                          color: Theme.of(context).primaryColor.withOpacity(0.3),
                        ),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide(
                          color: Theme.of(context).primaryColor,
                          width: 2,
                        ),
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 12,
                      ),
                      suffixIcon: _isSending
                          ? Padding(
                              padding: const EdgeInsets.all(12.0),
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Theme.of(context).primaryColor,
                              ),
                            )
                          : null,
                    ),
                    onSubmitted: (_) => _sendMessage(),
                    enabled: !_isSending,
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).primaryColor,
                    shape: BoxShape.circle,
                  ),
                  child: IconButton(
                    icon: const Icon(Icons.send, color: Colors.white),
                    onPressed: _isSending ? null : _sendMessage,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}