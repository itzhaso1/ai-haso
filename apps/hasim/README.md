# حاسم — تطبيق Flutter (Mobile API v1 · UX V2)

عميل عربي RTL لمنصة حاسم، يعتمد حصراً على:

```
{API_BASE}/api/mobile/v1
```

لا يعدّل هذا المشروع أي كود Laravel. العقد في `docs/mobile-api.md`، والمعمارية في [`ARCHITECTURE.md`](ARCHITECTURE.md). إعداد Google: [`GOOGLE_SIGNIN.md`](GOOGLE_SIGNIN.md).

## المتطلبات

- Flutter 3.35+ / Dart 3.9+
- جهاز أو محاكي Android / iOS
- خادم Laravel مع مسارات `/api/mobile/v1`

## التشغيل

```bash
cd apps/hasim
flutter pub get
flutter gen-l10n

flutter run --dart-define=API_BASE=http://10.0.2.2:8000
```

| البيئة | `API_BASE` |
|--------|------------|
| Android Emulator | `http://10.0.2.2:8000` |
| iOS Simulator | `http://127.0.0.1:8000` |
| جهاز حقيقي | `http://192.168.x.x:8000` |
| إنتاج | `https://your-domain.com` |

## V2 — ما تغيّر

- Splash بعلامة حاسم وانتقال حسب الجلسة
- تسجيل دخول محسّن + نسيت كلمة المرور / إعادة التعيين + Google (يتطلب إعداد عميل)
- سمة فاتح/داكن/تلقائي محفوظة
- رئيسية بتحية زمنية، إحصاءات، إجراءات سريعة، محادثات وحجوزات اليوم
- محادثة: تحميل أقدم، إعادة محاولة، مرفقات send-then-attach، AI اقتراح/تلخيص، كاش Hive
- بريد: حسابات الإرسال، رد، markRead عند الفتح
- إعدادات: ملف شخصي، باقات/استخدام، قنوات، تفضيلات إشعارات، مظهر
- ملف عميل `/customers/:id`
- شارات غير مقروء في الشريط السفلي
- gen-l10n عربي أساسي + إنجليزي stubs

## الاختبارات

```bash
flutter analyze
flutter test
```

## الوحدات

1. Splash / Login / Forgot / Reset / Social  
2. Workspace picker (بحث + أفاتار)  
3. Home V2  
4. Conversations + Chat V2  
5. Email compose/detail V2  
6. Appointments / Notifications  
7. Profile / Plans / Channels / Theme / Notification prefs  
8. Customer profile  
