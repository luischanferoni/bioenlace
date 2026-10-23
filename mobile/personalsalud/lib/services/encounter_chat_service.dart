import 'package:shared/shared.dart';

class EncounterChatService extends EncounterChatClient {
  EncounterChatService({
    required super.userId,
    super.authToken,
    super.userName,
    super.client,
  }) : super(defaultUserRole: 'medico');
}
