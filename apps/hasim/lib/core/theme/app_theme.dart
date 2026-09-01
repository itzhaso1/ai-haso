
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:hasim/core/config/app_config.dart';

class AppTheme {
  static ThemeData light() {
    final base = Color(AppConfig.brand.primary);
    final scheme = ColorScheme.fromSeed(
      seedColor: base,
      primary: base,
      surface: const Color(0xFFF7FBFA),
    );
    final textTheme = GoogleFonts.cairoTextTheme();
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      textTheme: textTheme,
      scaffoldBackgroundColor: const Color(0xFFF7FBFA),
      appBarTheme: AppBarTheme(
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        elevation: 0,
        titleTextStyle: GoogleFonts.cairo(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: const Color(0xFF0F172A),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        indicatorColor: base.withValues(alpha: 0.15),
        labelTextStyle: WidgetStatePropertyAll(
          GoogleFonts.cairo(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}
