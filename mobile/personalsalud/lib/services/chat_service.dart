import 'package:shared/shared.dart';

class ChatService extends AssistantChatClient {
  ChatService({
    required super.currentUserId,
    required super.currentUserName,
    super.authToken,
    super.client,
  }) : super(appClient: 'personalsalud-flutter');
}
