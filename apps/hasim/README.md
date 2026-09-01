# حاسم — تطبيق Flutter (Mobile API v1)

عميل عربي RTL لمنصة حاسم، يعتمد حصراً على:

```
{API_BASE}/api/mobile/v1
```

لا يعدّل هذا المشروع أي كود Laravel. العقد الكامل في `docs/mobile-api.md` في المستودع الرئيسي، وملخص المعمارية في [`ARCHITECTURE.md`](ARCHITECTURE.md).

## المتطلبات

- Flutter 3.35+ / Dart 3.9+
- جهاز أو محاكي Android / iOS
- خادم Laravel يعمل مع مسارات `/api/mobile/v1`

## التشغيل

من مجلد التطبيق:

```bash
cd apps/hasim
flutter pub get

# غيّر عنوان الـ API حسب بيئتك (افتراضي http://10.0.2.2:8000 للمحاكي أندرويد)
flutter run --dart-define=API_BASE=http://10.0.2.2:8000
```

أمثلة عناوين:

| البيئة | `API_BASE` |
|--------|------------|
| Android Emulator → Laravel على الجهاز المضيف | `http://10.0.2.2:8000` |
| iOS Simulator → localhost | `http://127.0.0.1:8000` |
| جهاز حقيقي على نفس الشبكة | `http://192.168.x.x:8000` |
| إنتاج / Staging | `https://your-domain.com` |

تسجيل الدخول يستخدم حساب منصة حاسم (Sanctum token عبر `POST /auth/login`).

## البناء

### Android APK

```bash
flutter build apk --release --dart-define=API_BASE=https://your-domain.com
```

الملف: `build/app/outputs/flutter-apk/app-release.apk`

### Android App Bundle

```bash
flutter build appbundle --release --dart-define=API_BASE=https://your-domain.com
```

### iOS

```bash
flutter build ios --release --dart-define=API_BASE=https://your-domain.com
# ثم افتح ios/Runner.xcworkspace في Xcode للتوقيع والأرشفة
```

> ملاحظة: إعدادات Firebase/APNs للإشعارات غير مفعّلة في هذه النسخة (الخلفية تستخدم FCM placeholder). الواجهة تعرض حالة «غير مفعّل» بوضوح.

## الاختبارات والتحليل

```bash
flutter analyze
flutter test
```

## الوحدات المنفّذة

1. تسجيل الدخول / الخروج  
2. اختيار مساحة العمل والتبديل  
3. الصفحة الرئيسية (إحصاءات + اختصارات)  
4. قائمة المحادثات الموحدة + بحث  
5. شاشة المحادثة + إرسال نص (Optimistic UI + Idempotency-Key)  
6. عرض مرفقات الصور (رفع مرفق جديد يتطلب message id — موضّح في الواجهة)  
7. البريد: قائمة / تفاصيل / رد / أرشفة / غير مقروء (إنشاء رسالة جديدة يحتاج `email_account_id` من API غير موجود حالياً)  
8. الحجوزات: قائمة / تفاصيل / تأكيد / إلغاء / إكمال / عدم حضور  
9. الإشعارات + تعليم الكل كمقروء  
10. الإعدادات / الحساب / الجلسات / حالة الدفع والوقت الفعلي  

## هيكل المجلدات

انظر `ARCHITECTURE.md`.
