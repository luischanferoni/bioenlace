import 'dart:io';
import 'package:http/http.dart' as http;
import '../models/user_registration.dart';

class RegistrationService {
  Future<bool> submitRegistration(UserRegistration user) async {
    final uri = Uri.parse('https://tuservidor.com/api/register');
    final request = http.MultipartRequest('POST', uri)
      ..fields['name'] = user.name
      ..fields['email'] = user.email
      ..files.add(await http.MultipartFile.fromPath('dni', user.dniImagePath))
      ..files.add(await http.MultipartFile.fromPath('selfie', user.selfieImagePath));

    final response = await request.send();
    return response.statusCode == 200;
  }
}
