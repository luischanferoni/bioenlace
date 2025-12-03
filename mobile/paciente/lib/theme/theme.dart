import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  const AppTheme();
  
  // Definición de colores centralizados
  static const Color primaryColor = Color(0xff00bac7);
  static const Color primaryColorDark = Color(0xffffffff);
  static const Color primaryColorLight = Color(0xff3754aa);

  static const Color secondaryColor = Color(0xFF6C757D);
  static const Color secondaryContainerColor = Color(0xff13165A);

  static const Color successColor = Color(0xFF28A745);
  static const Color dangerColor = Color(0xFFDC3545);
  static const Color warningColor = Color(0xFFFFC107);
  static const Color infoColor = Color(0xFF17A2B8);
  static const Color light = Color(0xFFF8F9FA);
  static const Color dark = Color(0xFF324356);

  static const Color backgroundColor = Color(0xffe1e1e9);
  static const Color cardColor = Color.fromRGBO(249, 249, 246, 1);
  static const Color titleTextColor = Color(0xff5a5d85);
  static const Color subTitleTextColor = Color(0xff797878);
  static const Color iconColor = Color(0xff3E404D);
  static const Color dividerColor = Color(0xffDFE7DD);  
  
  
  static ThemeData lightTheme = ThemeData.light().copyWith(
    scaffoldBackgroundColor: backgroundColor,
    primaryColor: primaryColor,
    primaryColorDark: primaryColorDark,
    primaryColorLight: primaryColorLight,
    cardTheme: CardThemeData(
      color: cardColor,
      elevation: 2,
    ),
    iconTheme: IconThemeData(color: iconColor),
    dividerColor: dividerColor,
    textTheme: GoogleFonts.openSansTextTheme(),
    colorScheme: ColorScheme(
        primary: primaryColor,
        primaryContainer: primaryColor,
        secondary: secondaryColor,
        secondaryContainer: secondaryContainerColor,
        surface: backgroundColor,
        background: backgroundColor,
        error: Colors.red,
        onPrimary: primaryColorDark,
        onSecondary: backgroundColor,
        onSurface: primaryColorDark,
        onBackground: titleTextColor,
        onError: titleTextColor,
        brightness: Brightness.light),
  );

  static TextStyle titleStyle = GoogleFonts.openSans(
    color: titleTextColor, 
    fontSize: 16,
    fontWeight: FontWeight.w600,
  );
  static TextStyle subTitleStyle = GoogleFonts.openSans(
    color: subTitleTextColor, 
    fontSize: 12,
    fontWeight: FontWeight.w400,
  );

  static TextStyle h1Style = GoogleFonts.openSans(
    fontSize: 24, 
    fontWeight: FontWeight.bold,
  );
  static TextStyle h2Style = GoogleFonts.openSans(
    fontSize: 22,
    fontWeight: FontWeight.w600,
  );
  static TextStyle h3Style = GoogleFonts.openSans(
    fontSize: 20,
    fontWeight: FontWeight.w600,
  );
  static TextStyle h4Style = GoogleFonts.openSans(
    fontSize: 18,
    fontWeight: FontWeight.w600,
  );
  static TextStyle h5Style = GoogleFonts.openSans(
    fontSize: 16,
    fontWeight: FontWeight.w500,
  );
  static TextStyle h6Style = GoogleFonts.openSans(
    fontSize: 14,
    fontWeight: FontWeight.w500,
  );
}
