import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  const AppTheme();
  
  // Definición de colores centralizados - Nueva paleta BioEnlace
  // #0081A7 -> azul (primary)
  static const Color primaryColor = Color(0xFF0081A7);
  static const Color primaryColorDark = Color(0xffffffff);
  // #00AFB9 -> celeste (secondary/info)
  static const Color primaryColorLight = Color(0xFF00AFB9);

  static const Color secondaryColor = Color(0xFF00AFB9);
  static const Color secondaryContainerColor = Color(0xFF0081A7);

  static const Color successColor = Color(0xFF28A745);
  // #F07167 -> rojo (danger)
  static const Color dangerColor = Color(0xFFF07167);
  // #FED9B7 -> naranja (warning)
  static const Color warningColor = Color(0xFFFED9B7);
  static const Color infoColor = Color(0xFF00AFB9);
  // #fafafa -> gris claro (light/background)
  static const Color light = Color(0xFFFAFAFA);
  static const Color dark = Color(0xFF324356);

  // #fafafa -> gris claro (background)
  static const Color backgroundColor = Color(0xFFFAFAFA);
  static const Color cardColor = Color.fromRGBO(255, 255, 255, 1);
  static const Color titleTextColor = Color(0xFF0081A7);
  static const Color subTitleTextColor = Color(0xff797878);
  static const Color iconColor = Color(0xFF0081A7);
  static const Color dividerColor = Color(0xFFE0E0E0);  
  
  
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
        error: dangerColor,
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

