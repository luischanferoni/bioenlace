import 'package:shared/shared.dart';

/// Staff-facing actions for asynchronous encounter requests.
class EncounterAsyncApi extends EncounterAsyncClient {
  final String? userId;

  EncounterAsyncApi({super.authToken, this.userId, super.client})
    : super(appClient: 'personalsalud-flutter');
}
