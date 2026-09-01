/// حاسم — إعدادات التطبيق
class AppConfig {
  AppConfig._();

  static const String apiBase = String.fromEnvironment(
    'API_BASE',
    defaultValue: 'http://127.0.0.1:8000',
  );

  static String get mobileApiBase => '$apiBase/api/mobile/v1';

  static const String appName = 'حاسم';
  static const ColorBrand brand = ColorBrand();
}

class ColorBrand {
  const ColorBrand();
  int get primary => 0xFF06C2A4;
  int get primaryDark => 0xFF067E6B;
  int get surface => 0xFFF3FCFA;
}
