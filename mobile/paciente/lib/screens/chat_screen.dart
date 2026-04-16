import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shared/shared.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/chat_service.dart';
import '../services/acciones_service.dart';
import '../components/dynamic_form.dart';
import 'mis_turnos_screen.dart';
import 'chat_motivos_screen.dart';
import 'package:shared/shared.dart' show UiJsonWizardScreen, applyProvidedParamsToRoute;

class ChatScreen extends StatefulWidget {
  final ChatService chatService;
  /// Si se provee, el botón "Mis turnos" del AppBar cambia a la pestaña Mis turnos (ej. en MainScreen).
  final VoidCallback? onIrAMisTurnos;

  const ChatScreen({
    Key? key,
    required this.chatService,
    this.onIrAMisTurnos,
  }) : super(key: key);

  @override
  _ChatScreenState createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  late AsistenteService _asistenteService;
  List<Map<String, dynamic>> _chatHistory = [];
  bool _isSending = false;
  Map<String, dynamic> _draft = {};
  String? _intentId;
  String? _subintentId;

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
    _asistenteService = AsistenteService(
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
      // Procesar interacción del usuario con el servicio de acciones
      final result = await _asistenteService.procesarInteraccion(text);

      if (result['success'] == true) {
        final data = result['data'];
        final kind = (data is Map) ? data['kind']?.toString() : null;
        final actions = (data is Map) ? (data['actions'] ?? (data['action'] != null ? [data['action']] : null)) : null;
        String explanation = (data is Map ? (data['explanation']?.toString()) : null) ?? 'Consulta procesada';

        // Modo intent (SubIntentEngine): text + open_ui + draft_delta
        if (data is Map && data['text'] != null) {
          explanation = data['text']?.toString() ?? explanation;
          final iid = data['intent_id']?.toString();
          final sid = data['subintent_id']?.toString();
          if (iid != null && iid.isNotEmpty) _intentId = iid;
          if (sid != null && sid.isNotEmpty) _subintentId = sid;
          // Sincronizar en el service para que el próximo envío use snapshot intent.
          if (_intentId != null && _intentId!.isNotEmpty) {
            _asistenteService.currentIntentId = _intentId;
          }
          if (_subintentId != null && _subintentId!.isNotEmpty) {
            _asistenteService.currentSubintentId = _subintentId;
          }
          final dd = data['draft_delta'];
          if (dd is Map) {
            _draft = {..._draft, ...Map<String, dynamic>.from(dd)};
            _asistenteService.draft = _draft;
          }

          // Si viene open_ui, abrir UI JSON inline/fullscreen usando el mismo mecanismo de actions.
          final openUi = data['open_ui'];
          if (openUi is Map) {
            final co = openUi['client_open'];
            final actionId = openUi['action_id']?.toString();
            if (co is Map && actionId != null && actionId.isNotEmpty) {
              final pseudoAction = <String, dynamic>{
                'action_id': actionId,
                'display_name': actionId,
                'client_open': Map<String, dynamic>.from(co),
                'parameters': {'provided': _draft},
              };
              // Agregar el bot message primero; luego abrir inline/fullscreen.
              setState(() {
                _isSending = false;
                _chatHistory.add({
                  'type': 'bot',
                  'content': explanation,
                  'actions': null,
                  'timestamp': DateTime.now(),
                });
              });
              _scrollToBottom();
              await _tryOpenClientNative(pseudoAction, messageIndex: _chatHistory.length - 1);
              return;
            }
          }
        }
        if (kind == 'ui_intent_match' && actions is List && actions.isNotEmpty) {
          final a0 = actions[0];
          if (a0 is Map) {
            final dn = a0['display_name']?.toString();
            if (dn != null && dn.isNotEmpty) {
              explanation = 'Puedo ayudarte con “$dn”.';
            }
          }
        }
        final suggestedQuery = data['interaccion_sugerida']?['texto'];
        final queryType = data['query_type'];
        final matchedBy = data['matched_by']; // 'action_id', 'semantic', o null (LLM)
        // final needsUserInput = data['needs_user_input'] ?? false;
        final actionAnalysisRaw = data['action_analysis'];
        // final actionAnalysis = (actionAnalysisRaw is Map)
        //     ? Map<String, dynamic>.from(actionAnalysisRaw)
        //     : null;

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
            'needs_user_input': data['needs_user_input'] ?? false,
            'action_analysis': (actionAnalysisRaw is Map)
                ? Map<String, dynamic>.from(actionAnalysisRaw)
                : null,
            'parameters': data['parameters'],
            'timestamp': DateTime.now(),
          });
        });

        // Si es una acción directa (action_id o semantic) y solo hay una acción, ejecutarla automáticamente
        // PERO solo si no necesita input del usuario
        final needsUserInput = data['needs_user_input'] ?? false;
        if ((matchedBy == 'action_id' || matchedBy == 'semantic') &&
            actions != null &&
            actions.length == 1 &&
            queryType == 'direct_action' &&
            !needsUserInput) {
          // Ejecutar la acción automáticamente después de un breve delay
          Future.delayed(Duration(milliseconds: 500), () {
            _executeAction(actions[0], messageIndex: _chatHistory.length - 1);
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
            'suggested_query': data?['interaccion_sugerida']?['texto'],
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

  /// Origen del sitio web (sin `/api/v1`) a partir de {@link AppConfig.apiUrl}.
  static Uri _webBaseUriFromApiUrl(String apiUrl) {
    var s = apiUrl.trim();
    s = s.replaceAll(RegExp(r'/api/v\d+/?$', caseSensitive: false), '');
    while (s.endsWith('/')) {
      s = s.substring(0, s.length - 1);
    }
    return Uri.parse(s);
  }

  /// Si la acción trae `client_open` (pantalla Yii / web nativa), abre el navegador y no pasa por CRUD/wizard.
  Future<bool> _tryOpenClientNative(Map<String, dynamic> action, {int? messageIndex}) async {
    final co = action['client_open'];
    if (co is! Map) {
      return false;
    }
    final kind = co['kind']?.toString();
    final mobile = co['mobile'];
    final web = co['web'];

    // UI JSON (descriptor + submit): abrir con el motor UI JSON compartido.
    if (kind == 'ui_json') {
      final api = co['api'];
      final route = api is Map ? api['route']?.toString() : null;
      if (route == null || route.isEmpty) {
        _showErrorSnackbar('Acción UI JSON sin api.route.');
        return true;
      }
      final provided = (action['parameters'] is Map) ? (action['parameters'] as Map)['provided'] : null;
      final presentation = co['presentation']?.toString() ?? 'fullscreen';
      final widget = UiJsonWizardScreen(
        apiAbsoluteUrl: applyProvidedParamsToRoute(route, provided is Map ? Map<String, dynamic>.from(provided) : null),
        authToken: _asistenteService.authToken,
        appClient: 'bioenlace-paciente',
        title: action['display_name']?.toString() ?? action['action_id']?.toString(),
      );

      if (presentation == 'inline') {
        // Inline: embebido dentro del chat (en la misma burbuja si tenemos índice).
        final title = action['display_name']?.toString() ?? action['action_id']?.toString() ?? 'Formulario';
        setState(() {
          if (messageIndex != null && messageIndex >= 0 && messageIndex < _chatHistory.length) {
            final m = _chatHistory[messageIndex];
            m['inline_ui'] = {
              'title': title,
              'route': route,
              'provided': provided,
            };
            if ((m['content']?.toString() ?? '').trim().isEmpty) {
              m['content'] = title;
            }
          } else {
            _chatHistory.add({
              'type': 'bot',
              'content': title,
              'inline_ui': {
                'title': title,
                'route': route,
                'provided': provided,
              },
              'timestamp': DateTime.now(),
            });
          }
        });
        _scrollToBottom();
      } else {
        Navigator.of(context).push(
          MaterialPageRoute<void>(builder: (_) => widget),
        );
      }
      return true;
    }

    // UIs nativas (web+flutter) por screen_id: el móvil construye su propia pantalla.
    if (kind == 'native') {
      final screenId = (mobile is Map ? mobile['screen_id'] : null) ??
          co['screen_id'];
      if (screenId is! String || screenId.isEmpty) {
        _showErrorSnackbar('Acción nativa sin screen_id para móvil.');
        return true;
      }
      // TODO: mapear screen_id -> pantalla Flutter.
      // Por ahora, avisar claramente en UI para no abrir navegador/webview.
      _showErrorSnackbar('Pantalla nativa móvil pendiente: $screenId');
      return true;
    }

    final pathRaw = (web is Map ? web['path'] : null);
    if (pathRaw is! String || pathRaw.isEmpty) {
      return false;
    }
    final base = _webBaseUriFromApiUrl(AppConfig.apiUrl);
    var target = base.replace(path: pathRaw);
    final queryMap = (web is Map ? web['query'] : null);
    if (queryMap is Map && queryMap.isNotEmpty) {
      final q = <String, String>{...target.queryParameters};
      queryMap.forEach((k, v) {
        if (v != null && v.toString().isNotEmpty) {
          q[k.toString()] = v.toString();
        }
      });
      target = target.replace(queryParameters: q);
    }
    try {
      // Fallback legacy: abrir en navegador externo.
      if (await canLaunchUrl(target)) {
        await launchUrl(target, mode: LaunchMode.externalApplication);
        return true;
      }
      _showErrorSnackbar('No se pudo abrir la pantalla.');
      return true;
    } catch (e) {
      _showErrorSnackbar('No se pudo abrir: ${e.toString()}');
      return true;
    }
  }

  Future<void> _executeAction(Map<String, dynamic> action, {int? messageIndex}) async {
    if (await _tryOpenClientNative(action, messageIndex: messageIndex)) {
      return;
    }

    // Desde la migración a UI JSON, el backend debe enviar `client_open` para abrir la pantalla.
    // Si no vino, evitamos llamar endpoints legacy (/crud/ejecutar-accion) que ya no existen.
    _showErrorSnackbar('Acción sin client_open: no se puede abrir en esta versión.');
  }

  Future<void> _executeActionWithParams(String actionId, Map<String, dynamic> params) async {
    // Legacy: antes se posteaba a /crud/ejecutar-accion. Ese endpoint ya no existe.
    // La ejecución/submit ahora ocurre dentro de la pantalla UI JSON (UiJsonWizardScreen).
    _showErrorSnackbar('Submit legacy no soportado. Abrí la pantalla desde client_open.');
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
              if (widget.onIrAMisTurnos != null) {
                widget.onIrAMisTurnos!();
              } else {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => MisTurnosScreen(
                      authToken: _asistenteService.authToken,
                      userId: widget.chatService.currentUserId,
                      userName: widget.chatService.currentUserName,
                    ),
                  ),
                );
              }
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
                final inlineUi = message['inline_ui'];
                // final needsUserInput = message['needs_user_input'] as bool? ?? false;
                // final actionAnalysisRaw = message['action_analysis'];
                // final actionAnalysis = (actionAnalysisRaw is Map)
                //     ? Map<String, dynamic>.from(actionAnalysisRaw)
                //     : null;

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
                              onPressed: () => _executeAction(action, messageIndex: index),
                              backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                              labelStyle: TextStyle(
                                color: Theme.of(context).primaryColor,
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                    // Inline UI JSON embebida en chat
                    if (!isUser && inlineUi is Map) ...[
                      const SizedBox(height: 12),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16.0),
                        child: ConstrainedBox(
                          constraints: BoxConstraints(
                            maxHeight: MediaQuery.of(context).size.height * 0.75,
                          ),
                          child: AnimatedSize(
                            duration: const Duration(milliseconds: 180),
                            curve: Curves.easeOut,
                            child: UiJsonWizardScreen(
                              apiAbsoluteUrl: applyProvidedParamsToRoute(
                                inlineUi['route']?.toString() ?? '',
                                inlineUi['provided'] is Map ? Map<String, dynamic>.from(inlineUi['provided'] as Map) : null,
                              ),
                              authToken: _asistenteService.authToken,
                              appClient: 'bioenlace-paciente',
                              title: inlineUi['title']?.toString(),
                              embedded: true,
                            ),
                          ),
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
      // final parametersFromMessage = message['parameters'];
                          
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
                                          authToken: _asistenteService.authToken,
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
                                          authToken: _asistenteService.authToken,
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
                    // CTA Cargar motivos cuando la respuesta incluye id_consulta (ej. tras crear turno)
                    if (!isUser) ...[
                      Builder(
                        builder: (context) {
                          final data = message['data'];
                          final idConsulta = data is Map ? data['id_consulta'] : null;
                          final idConsultaInt = idConsulta is int ? idConsulta : (idConsulta is num ? idConsulta.toInt() : null);
                          if (idConsultaInt == null) return const SizedBox.shrink();
                          return Padding(
                            padding: const EdgeInsets.only(left: 16.0, right: 16.0, top: 8.0),
                            child: ActionChip(
                              avatar: Icon(Icons.edit_note, size: 18, color: Theme.of(context).primaryColor),
                              label: const Text('Cargar motivos de la consulta'),
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ChatMotivosScreen(
                                      consultaId: idConsultaInt,
                                      authToken: _asistenteService.authToken,
                                      userId: widget.chatService.currentUserId,
                                      userName: widget.chatService.currentUserName,
                                      titulo: 'Motivos de la consulta',
                                    ),
                                  ),
                                );
                              },
                              backgroundColor: Theme.of(context).primaryColor.withOpacity(0.1),
                              labelStyle: TextStyle(color: Theme.of(context).primaryColor, fontSize: 13),
                            ),
                          );
                        },
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