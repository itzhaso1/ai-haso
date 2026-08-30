# WhatsApp Webhook & Embedded Signup Setup

هذا الملف يشرح الحالة الفعلية الحالية داخل المشروع بعد التنفيذ، بدون افتراضات.

## 1) المسار النهائي للـ WhatsApp Webhook

- تم تعريف Webhook الآن داخل `routes/web.php` وليس `routes/api.php`.
- المسار النهائي:
  - **GET** `/whatsapp-webhook` (للتحقق Verification)
  - **POST** `/whatsapp-webhook` (لاستقبال الأحداث Events)
- أسماء المسارات:
  - `webhooks.whatsapp.verify`
  - `webhooks.whatsapp.handle`

### لماذا `web.php` وليس `api.php`؟

- لتقليل مشاكل التحقق الخارجي من Meta عندما تكون بعض الاستضافات أو طبقات الحماية (WAF) أكثر تشددًا مع مسارات API.
- هذا مفيد خصوصًا عند وجود Query Parameters من Meta في طلبات التحقق.
- المسار النهائي العام الذي يجب وضعه في Meta:
  - `https://your-domain.com/whatsapp-webhook`

---

## 2) إعداد CSRF الفعلي

في `bootstrap/app.php` تم استثناء المسار التالي من CSRF:

- `whatsapp-webhook`

الغرض من الاستثناء:

- طلبات Meta الخارجية لا تحمل CSRF token من Laravel session.
- بدون الاستثناء سيتم رفض الطلبات (419 / CSRF mismatch).

---

## 3) آلية Webhook Verification (مطابقة للكود الحالي)

المنفذ: `App\Http\Controllers\Webhook\WhatsAppWebhookController::verify`

### HTTP Method

- `GET /whatsapp-webhook`

### Query Parameters المستخدمة

- `hub.mode`
- `hub.verify_token`
- `hub.challenge`

### المنطق

1. يقرأ التطبيق `hub.mode`, `hub.verify_token`, `hub.challenge`.
2. يقارن:
   - `hub.mode === subscribe`
   - `hub.verify_token === config('whatsapp.verify_token')`
3. عند النجاح:
   - يرجع قيمة `hub.challenge` كنص
   - HTTP `200`
4. عند الفشل:
   - يرجع `Forbidden`
   - HTTP `403`

---

## 4) آلية استقبال Webhook Events (مطابقة للكود الحالي)

المنفذ: `App\Http\Controllers\Webhook\WhatsAppWebhookController::handle`

### HTTP Method

- `POST /whatsapp-webhook`

### التحقق الأمني قبل المعالجة

- يتم التحقق من الهيدر `X-Hub-Signature-256` عبر HMAC SHA-256 باستخدام `config('whatsapp.app_secret')`.
- عند فشل التوقيع:
  - JSON: `{"message":"Invalid signature"}`
  - HTTP `403`

### ماذا يحدث بعد نجاح التوقيع؟

1. يتم تمرير كامل الـ payload + headers إلى:
   - `App\Services\WhatsApp\WhatsAppService::processWebhook`
2. الخدمة تستخرج حاليًا:
   - `phone_number_id` من metadata
   - أول رسالة inbound من `messages[0]`
3. إذا كانت البيانات غير مكتملة، يتم تجاهل الحدث بدون crash.
4. عند وجود رقم واتساب معروف داخل `whats_app_phone_numbers`:
   - يسجّل الحدث في جدول `webhook_events` (idempotent عبر `external_event_id`)
   - يمنع إعادة المعالجة لنفس event.
   - يرسل Job:
     - `ProcessIncomingWhatsAppMessage`
5. الـ Job يقوم بـ:
   - إنشاء/تحديث `Customer`
   - إنشاء/استخدام `Conversation`
   - إنشاء `Message` inbound
   - تحديث `last_message_at` و`metadata.unread_count`
   - تحديث حالة الحدث في `webhook_events` إلى `processed`
6. استجابة الـ webhook:
   - JSON: `{"received": true}`
   - HTTP `202`

### أنواع الأحداث المدعومة فعليًا الآن

- رسائل WhatsApp inbound ضمن المسار:
  - `entry[0].changes[0].value.messages[0]`
- لا يوجد حاليًا معالج مستقل لحالات delivery/read/status events في نفس الخدمة.

---

## 5) التكامل الفعلي مع Meta WhatsApp Embedded Signup

### الواجهة (Frontend)

- صفحة القنوات: `resources/views/workspace/channels/index.blade.php`
- زر: **Connect WhatsApp / Reconnect WhatsApp**
- يتم تحميل SDK الرسمي:
  - `https://connect.facebook.net/en_US/sdk.js`
- يتم استدعاء `FB.login` باستخدام:
  - `config_id`
  - `response_type=code`
  - `override_default_response_type=true`
- يتم التقاط session data من `postMessage` عند توفرها (`WA_EMBEDDED_SIGNUP`).

### الخلفية (Backend)

- Controller:
  - `App\Http\Controllers\Workspace\ChannelController::connectWhatsApp`
- Service:
  - `App\Services\WhatsApp\WhatsAppEmbeddedSignupService`

### الخطوات الفعلية في السيرفر

1. استلام `code` من Meta popup.
2. Exchange للكود إلى access token عبر Graph API:
   - `/{api_version}/oauth/access_token`
3. جلب WhatsApp Business Account + phone numbers من Graph.
4. تحديث/إنشاء:
   - `whats_app_accounts`
   - `whats_app_phone_numbers`
5. تعيين حالة القناة إلى `connected` فعليًا في قاعدة البيانات.

> ملاحظة: لا يتم وضع App Secret أو Access Token في الـ frontend.

---

## 6) Security المطبق فعليًا

1. **عدم وضع الأسرار في الواجهة**  
   الأسرار تبقى في `.env` وتُقرأ عبر config فقط.

2. **Webhook Signature Verification**  
   كل POST على `/whatsapp-webhook` يجب أن يجتاز `X-Hub-Signature-256`.

3. **CSRF Exception محدود**  
   تم استثناء `whatsapp-webhook` فقط للسماح بطلبات Meta الخارجية.

4. **Idempotency / Duplicate protection**  
   جدول `webhook_events` يحتوي unique قيود على:
   - `provider + idempotency_key`
   - `provider + external_event_id`

5. **التعامل مع بيانات غير صحيحة**  
   verification الفاشل أو signature الفاشل يرجع 403، بدون كشف أسرار.

6. **عدم تسجيل الأسرار في Logs**  
   الكود الحالي لا يطبع app secret أو access tokens في logs.

---

## 7) Environment Variables المستخدمة فعليًا

> استخدم قيم Placeholder فقط:

```env
WHATSAPP_VERIFY_TOKEN=your_verify_token
WHATSAPP_APP_SECRET=your_meta_webhook_app_secret
WHATSAPP_API_VERSION=v20.0
WHATSAPP_PERMANENT_TOKEN=your_whatsapp_permanent_token

META_APP_ID=your_meta_app_id
META_APP_SECRET=your_meta_app_secret
WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID=your_embedded_signup_config_id
WHATSAPP_EMBEDDED_SIGNUP_REDIRECT_URI=https://your-domain.com/workspace/channels
```

### شرح مختصر

- `WHATSAPP_VERIFY_TOKEN`: توكن المقارنة في webhook verification (GET).
- `WHATSAPP_APP_SECRET`: مفتاح التحقق من توقيع Webhook POST.
- `WHATSAPP_API_VERSION`: نسخة Graph API المستخدمة.
- `WHATSAPP_PERMANENT_TOKEN`: توكن الإرسال outbound لرسائل واتساب.
- `META_APP_ID`: App ID المستخدم لتهيئة Meta JS SDK.
- `META_APP_SECRET`: App Secret المستخدم في code exchange لـ Embedded Signup.
- `WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID`: Configuration ID من Meta Embedded Signup.
- `WHATSAPP_EMBEDDED_SIGNUP_REDIRECT_URI`: Redirect URI مطابق لإعدادات Meta عند الحاجة.

---

## 8) خطوات Meta Developer Dashboard

1. أنشئ/استخدم Meta App مناسب لـ WhatsApp Business.
2. فعّل WhatsApp product داخل التطبيق.
3. في Webhooks:
   - Callback URL:
     - `https://your-domain.com/whatsapp-webhook`
   - Verify Token:
     - نفس قيمة `WHATSAPP_VERIFY_TOKEN`
4. اشترك في حقول WhatsApp المطلوبة (مثال أساسي):
   - `messages`
5. فعّل Embedded Signup configuration واحصل على:
   - `WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID`
6. تأكد من إضافة النطاق والـ redirect URI المسموحين في إعدادات التطبيق.
7. تأكد من الصلاحيات المرتبطة بـ WhatsApp Business management/messaging حسب سياسة Meta الحالية.

---

## 9) Troubleshooting

### A) 403 Forbidden

- تأكد أن المسار المستخدم في Meta هو:
  - `https://your-domain.com/whatsapp-webhook`
- تأكد من إعدادات WAF/Hosting التي قد تمنع query params أو requests من Meta.
- تأكد أن الطلبات تصل لنفس التطبيق/البيئة الصحيحة.
- في POST تأكد من وجود `X-Hub-Signature-256` وتطابق `WHATSAPP_APP_SECRET`.

### B) Webhook Verification Failed

- تحقق من:
  - `hub.mode=subscribe`
  - `hub.verify_token` مطابق لـ `WHATSAPP_VERIFY_TOKEN`
  - Callback URL صحيح
  - method هو GET

### C) CSRF Error

- تأكد أن `whatsapp-webhook` موجود ضمن `validateCsrfTokens(except: [...])` في `bootstrap/app.php`.
- بعد أي تعديل config/middleware نفّذ:
  - `php artisan optimize:clear`

### D) POST Requests لا تصل

- تأكد من HTTPS صالح.
- راجع Firewall/WAF وقواعد الحظر.
- تحقق من إعدادات Meta Webhook subscription.
- تحقق أن المسار في `routes/web.php` وليس route مختلف.

### E) الرسائل لا تظهر في Inbox

- تحقق من payload: هل يحتوي `messages[0]` و`phone_number_id`.
- تأكد أن رقم الواتساب موجود في `whats_app_phone_numbers`.
- راجع جدول `webhook_events` (status / duplicate / failed).
- تأكد من عمل queue worker لمعالجة `ProcessIncomingWhatsAppMessage`.
- راجع mapping القناة داخل `conversations.metadata.channel_source`.

### F) Environment Variables

- تحقق من وجود كل متغيرات واتساب/ميتا.
- تحقق من صحة القيم.
- بعد التعديل:
  - `php artisan config:clear`
  - `php artisan cache:clear`

---

## 10) ملاحظات تنفيذية مهمة

- صفحة Channels مرتبطة فعليًا داخل لوحة العمل عبر route:
  - `workspace.channels.index`
- ربط WhatsApp عبر Embedded Signup يتم فعليًا عبر endpoint:
  - `workspace.channels.whatsapp.connect`
- Omnichannel Inbox يعتمد على `display_channel` من `conversation.channel` و/أو `metadata.channel_source`، ويعرض badge واضح للقناة داخل القائمة وداخل المحادثة المفتوحة.
