# Hasim Cashier — Production Readiness Audit

تاريخ: 2026-09-02  
النطاق: `apps/hasim_cashier/lib` + `apps/hasim_cashier/test` بعد Schema v5/CheckoutService/Standalone.  
القاعدة: لا Features جديدة، لا Licensing، لا إعادة كتابة SyncEngine.

```
Standalone POS = SQLite/Drift + Local Business Logic
              + Local Auth + Local Reports + Local Inventory
              + Local Payments + Local Invoices + Local Returns
              + Local Shifts + Local Printing + Local Backup
```

لا اعتماد في Core POS على Laravel / API / Internet / InitialSync / SyncEngine / Dio.

---

## الحكم النهائي

**ليس Production Ready بالكامل.**  
المسار اليومي (إعداد مستقل → PIN → بيع نقدي → فاتورة → مرتجع → وردية → تقرير → Backup) أصبح معزولاً عن الشبكة في طبقة الخدمات، وله اختبار يفشل إذا لمس `NetworkGuard`.  
ما زالت مخاطر متبقية تمنع إعلان المنتج جاهزاً للبيع التجاري بدون تحفظ: لا Foreign Keys في Drift، تخزين المال ما زال REAL مشتقاً من السنتات، PIN بـ SHA-256 وليس KDF، مسارات الطاولات/QR ما زالت Connected-oriented، ولم يُنفَّذ stress على 100 ألف بند.

---

## Before → After

| المنطقة | قبل التدقيق | بعد الإصلاح |
|---|---|---|
| هوية Standalone | `workspaceId = 1` يتصادم مع Laravel WS 1 | نطاق محجوز `900001` + هوية المتجر UUID. ترحيل v6 يحوّل `1 → 900001` فقط لمتجر standalone غير متصل وغير مُزامَن |
| المال | `double` + تقريب بعد كل عملية | سياسة واحدة: **سنتات صحيحة** داخل `Money`/`PricingService`. REAL في SQLite يُشتق من السنتات |
| Checkout | وردية اختيارية في الخدمة؛ مسودة تُمسح خارج المعاملة | وردية مفتوحة إلزامية. مسودة تُمسح داخل نفس المعاملة. حقن فشل بعد كل نقطة يُراجع البيع بالكامل |
| الصلاحيات | إخفاء أزرار فقط | `PosPermissions` في Checkout/Return/Shift/Catalog/Backup/Stock adjust |
| جلسة Standalone | استعادة `standalone:` بدون PIN | Cold start يطلب PIN. Logout لا يستدعي Laravel |
| مرتجع UI | `order['id'] as num` يُجهض UUID | `local_id` أولاً. لا يغيّر إجمالي الفاتورة الأصلية |
| Isolation | شاشات كانت تستدعي Dio ثم تُرفض | Standalone يتخطى HTTP. `NetworkGuard` + اختبار عزل |
| Backup | بدون checksum؛ مسودات غير مُصدَّرة | format v2 + SHA-256 + مسودات + sequences حسب المتجر |
| التقارير | تحميل كل الجداول ثم فلترة في Dart | نطاق تاريخ في SQL + تجميع بنود دفعة واحدة + `net_sales = invoices − returns` |
| التسلسل | read-modify-write | `INSERT … ON CONFLICT UPDATE` ذري |
| الفهارس | ناقصة للباركود/التواريخ | فهارس v6 + unique `client_reference` + وردية مفتوحة واحدة |

---

## Tests

- `apps/hasim_cashier/test/pos_local_core_test.dart` يغطي: تسعير السنتات، بيع ذري، double-tap، مخزون، مرتجع، مسودة، تسلسل، PIN، تقارير، backup، وردية، باركود، هوية 900001، عزل الشبكة، فشل جزئي للبيع، صلاحيات الكاشير، عدم تغيير الفاتورة، sale→return→sale، مخزون سالب، remap.
- التحقق في هذا الفرع: `flutter analyze` = 0 errors (28 info سابقة)، `flutter test` = **141 passed, 0 failed**.

---

## BLOCKER / CRITICAL (أُصلحت في هذا الفرع)

### B1 — Standalone identity = Laravel workspace 1

- **Problem:** `PosMode.standaloneWorkspaceId = 1` يجعل صفوف المتجر المستقل تبدو كأنها مساحة Laravel رقم 1.
- **Location:** كان `lib/core/pos/pos_mode.dart`؛ يُكتب في `LocalStores`, `LocalUsers`, `Orders`, `Products`, `Customers`, `Invoices`, `SyncMetadata`, `WorkspaceScope`.
- **Why it matters:** Connect لاحقاً إلى Workspace 1 يصطدم بالمفاتيح والترحيل.
- **Reproduction:** إعداد Standalone ثم فحص `local_stores.workspace_id`.
- **Recommended fix (applied):** `900001` نطاق محجوز. الهوية الحقيقية `LocalStores.localId` (UUID). v6: remap `1 → 900001` فقط إذا وُجد متجر و`connected_mode=0` ولا `initial_sync_completed`. Connect لاحقاً يجب أن يعيد تعيين `workspace_id` من 900001 إلى معرّف Laravel مع الإبقاء على UUID.
- **Risk:** إن وُجد Laravel workspace حقيقي رقمه 900001 (غير محتمل) سيحتاج Connect خطة خاصة.

### C1 — Money as floating point

- **Problem:** `0.1 + 0.2 != 0.3` على `double`.
- **Location:** `pricing_service.dart`, أعمدة `RealColumn`, التقارير، الصندوق.
- **Why it matters:** فروقات هللة في الضريبة/الباقي/الوردية.
- **Reproduction:** جمع أسعار 0.1 و 0.2.
- **Recommended fix (applied):** سنتات صحيحة في المحرك؛ `Money.round` = `fromCents(toCents)`.
- **Risk:** الأعمدة ما زالت REAL — ترحيل INTEGER cents مؤجّل (MEDIUM متبقي).

### C2 — Permissions UI-only

- **Problem:** الكاشير كان يملك `orders.manage` فيدور المرتجع. الخدمات لا تفحص الصلاحية.
- **Location:** `local_auth_service.permissionsFor`, Checkout/Return/Catalog/Shift/Backup.
- **Why it matters:** إخفاء الزر لا يمنع الاستدعاء.
- **Reproduction:** استدعاء `ReturnService` بصلاحيات كاشير.
- **Recommended fix (applied):** `PosPermissions.require` في طبقة التطبيق. كاشير: بيع + فتح وردية + تقارير فقط.
- **Risk:** أدوار Laravel القديمة ما زالت واسعة عبر `orders.manage` في الوضع المتصل.

### C3 — Standalone session restores without PIN

- **Problem:** `_bootstrap` كان يعيد جلسة `standalone:` مباشرة.
- **Location:** `auth_controller.dart`
- **Why it matters:** أي شخص يفتح الجهاز يدخل كآخر مستخدم.
- **Reproduction:** قتل التطبيق بعد تسجيل PIN ثم إعادة الفتح.
- **Recommended fix (applied):** لا استعادة جلسة Standalone؛ الإبقاء على المتجر وطلب PIN.
- **Risk:** تجربة إضافية في كل فتح — مقصود.

### C4 — Returns UI aborted on UUID

- **Problem:** `_returnOrder` يحوّل `id` إلى `num` قبل مسار `local_id`.
- **Location:** `features/orders/orders_list.dart`
- **Why it matters:** المرتجع المحلي لا يعمل في Standalone.
- **Reproduction:** بيع محلي ثم مرتجع من قائمة الطلبات.
- **Recommended fix (applied):** `local_id` أولاً؛ `allowNegativeStock` من إعداد المتجر؛ صلاحية في الخدمة.
- **Risk:** لا.

### C5 — Sale without shift / multiple open shifts

- **Problem:** `shiftLocalId` اختياري؛ لا unique partial index.
- **Location:** `checkout_service.dart`, `local_shifts`
- **Why it matters:** مبيعات بلا صندوق؛ ورديتان مفتوحتان تفسدان النقد المتوقع.
- **Reproduction:** Checkout بدون وردية؛ `open()` مرتين من جهازين نظرياً.
- **Recommended fix (applied):** الخدمة ترفض بلا وردية مفتوحة. `UNIQUE (workspace_id) WHERE status='open'`.
- **Risk:** SQLite يفرض القيد عند الكتابة المتزامنة.

### C6 — Half-complete sale

- **Problem:** معاملة واحدة موجودة لكن بلا اختبار فشل بعد كل كتابة. مسودة خارج المعاملة.
- **Location:** `checkout_service.dart`, `shell_screen.dart`
- **Why it matters:** بيع مكرر بعد crash أو فاتورة بلا مخزون.
- **Reproduction:** فشل بعد insert الطلب؛ قتل التطبيق بعد الدفع قبل `cart.clear`.
- **Recommended fix (applied):** `CheckoutFaultPoint` + rollback؛ مسودة داخل نفس المعاملة.
- **Risk:** حقن الفشل للاختبار فقط.

### C7 — Network still attempted from UI

- **Problem:** تقارير/فواتير/عملاء/إعدادات/قائمة طعام/حذف صنف/logout كانت تستدعي Dio. المعترض يرفض لكن المحاولة موجودة.
- **Location:** `daily_reports_panel`, `invoices_list`, `customers_panel`, `settings_panel`, `menu_orders_feed`, `admin_placeholders`, `auth_controller`
- **Why it matters:** عزل Standalone غير مثبت؛ أخطاء اتصال مربكة.
- **Reproduction:** فتح التقارير بدون إنترنت في Standalone.
- **Recommended fix (applied):** تخطي HTTP عندما `PosMode.isStandaloneRuntime`. `NetworkGuard` في المعترض. اختبار عزل للمسار الأساسي.
- **Risk:** شاشات الطاولات/QR ما زالت Connected (انظر المتبقي).

---

## HIGH (أُصلح ما يمس البيع/المال/المخزون/البيانات)

### H1 — Incomplete backup / restore

- **Problem:** sequences عامة؛ مسودات تُحذف ولا تُصدَّر؛ لا checksum.
- **Location:** `backup_service.dart`
- **Fix applied:** format 2 + checksum + drafts + sequences حسب `store_id`. v1 ما زال يُستعاد.
- **Risk:** UX ما زال يختار أحدث ملف بالاسم (MEDIUM).

### H2 — Reports load entire tables / ignore returns in net

- **Problem:** N+1 على البنود؛ `grand_total` = إجمالي الفواتير دون طرح المرتجع كصافي.
- **Location:** `reports_service.dart`
- **Fix applied:** فلترة تاريخ SQL؛ بنود دفعة واحدة؛ `gross_sales` vs `net_sales`. الفاتورة الأصلية لا تُعدَّل.
- **Risk:** تقرير المخزون الافتتاحي/المشتريات غير مبني بالكامل (MEDIUM).

### H3 — client_reference not UNIQUE; sequences racy

- **Fix applied:** unique index + upsert ذري للتسلسل.
- **Risk:** أرقام مكررة من backup مستعاد فوق بيانات حيّة إذا تغيّر `store_id`.

### H4 — Hive migration on standalone home

- **Fix applied:** لا تشغيل `HiveLegacyMigration` على نطاق Standalone.
- **Risk:** Hive لم يُحذف (مقصود).

### H5 — Catalog delete API-only

- **Fix applied:** `CatalogAdminService.deleteProduct` (soft-delete) من الواجهة المحلية.

### H6 — Return hardcoded `allowNegativeStock: true`

- **Fix applied:** يحترم إعداد المتجر.

---

## MEDIUM (موثّقة — لم تُوسَّع النطاق)

### M1 — لا Foreign Keys في Drift

- **Problem:** كل العلاقات منطقية فقط. orphan `order_items` / `payments` ممكن عند حذف يدوي.
- **Location:** `lib/core/local_db/tables.dart`
- **Why it matters:** سلامة مرجعية غير مضمونة من المحرك.
- **Reproduction:** `DELETE FROM local_orders` مباشرة من sqlite3.
- **Recommended fix:** إعادة بناء الجداول مع FK في إصدار مخطط لاحق (SQLite لا يضيف FK بسهولة).
- **Risk:** عالي إن حدثت صيانة يدوية للملف. `PRAGMA foreign_keys=ON` مفعّل لكن بلا قيود معرّفة.

### M2 — REAL money columns

- ترحيل INTEGER cents يحتاج نسخ جداول. السياسة الحالية تخفف الخطأ ولا تلغيه من التخزين.

### M3 — PIN = SHA-256 + salt، الحد الأدنى 4 أرقام

- ليس PBKDF2/Argon2. كافٍ لكاشير محلي مغلق؛ ضعيف إن سُرق ملف DB.
- **Recommended fix:** KDF + PIN من 6 قبل البيع التجاري الواسع.

### M4 — طباعة غير متوفرة لا تفشل البيع (تحقق جزئي)

- `shell_screen` يطبع بعد COMMIT. إعادة الطباعة من قائمة الفواتير موجودة.
- Bluetooth/USB غير مكتمل. لا قفل صلاحية على reprint.

### M5 — Restore يختار `backups.first` (الأحدث حسب اسم الملف)

- يعمل إن التسمية صحيحة؛ لا منتقي ملفات عام.

### M6 — `local-offline` ما زال مقبولاً كرمز تراثي

- يجب إيقافه بعد هجرة كل الأجهزة.

### M7 — تبويب المزامنة ظاهر في Standalone

- إرباك تشغيلي فقط.

### M8 — `LocalPermissions` جدول مكتوب ولا يُقرأ

- الصلاحيات من دور `LocalUsers` / خريطة الجلسة.

### M9 — تقرير المخزون = لقطة حالية وليس دفتر (افتتاح + مشتريات + مرتجع − مبيعات ± تسوية)

- الحركات موجودة في `local_stock_movements` لكن تقرير الدفتر غير مبني.

---

## LOW

- طابعات Bluetooth/USB غير منفّذة.
- لا اختبار ضغط 10k منتج / 100k بند في CI — أُضيفت فهارس فقط.
- `table_detail_screen` / `table_add_order_sheet` ما زالا يستدعيان API لمسار الطاولات المتصل.
- SyncEngineV2 لم يُمس (مقصود، P3).
- لا Licensing (مقصود).

---

## Standalone isolation (مسار مثبت بالتحليل الثابت + اختبار)

```
Standalone setup → PIN → Home → Catalog → Cart → Checkout
→ Invoice → Payment → Stock → Return → Shift → Reports → Backup
```

الخدمات: `CheckoutService`, `ReturnService`, `StockEngine`, `ShiftService`, `LocalReportsService`, `BackupService`, `CatalogAdminService`, `DocumentNumberService`, `LocalAuthService` لا تستورد Dio / CashierApiClient / InitialSync / SyncEngine.

الاختبار `standalone core path never increments NetworkGuard` يفشل إن سُجِّلت محاولة HTTP أثناء هذا المسار.

الحد Connected الصحيح:

```
POS Core → (اختياري) Outbox فقط إذا connected=true → SyncEngineV2 → Laravel
```

وليس `POS Core → API` داخل معاملة البيع.

---

## Database integrity

| جدول | PK | ملاحظات |
|---|---|---|
| معظم الكيانات | `localId` نص UUID | `serverId` nullable |
| LocalSettings / SyncMetadata | `(workspaceId, key)` | |
| LocalSequences | `(storeId, kind)` | ليس max(id)+1 |
| SyncQueueItems | autoIncrement | Connected فقط |

قيود v6 المضافة:

- UNIQUE `(workspace_id, client_reference)` على الطلبات
- UNIQUE جزئي لرقم الفاتورة/الطلب
- UNIQUE وردية مفتوحة واحدة لكل نطاق
- فهارس باركود/SKU/`created_at`

لا FK. سلوك الحذف: soft-delete للأصناف. الفاتورة المدفوعة لا تُحدَّث مالياً عند المرتجع.

---

## Migration strategy (آمنة)

1. **تثبيت جديد:** schema 6 + `workspace_id=900001` + `store.localId=UUID`.
2. **تثبيت v5 standalone (workspace=1، لا sync):** عند الفتح يُرحَّل كل صف `workspace_id=1` → `900001` إن لم يوجد متجر على 900001 ولم تكتمل `initial_sync_completed`.
3. **تثبيت متصل سبق وزامَن Laravel WS 1:** لا remap. تلك الصفوف تبقى لـ Laravel.
4. **Connect لاحقاً:** لا تُعاد كتابة UUID. تُنقل صفوف `900001` إلى `laravelWorkspaceId` داخل معاملة واحدة بعد تأكيد أن الهدف فارغ أو أن الدمج مُخطَّط. لا تُستخدم القيمة `1` كهوية Standalone أبداً.
5. **هجرة متقطعة:** `CREATE INDEX IF NOT EXISTS` و`addColumn` محميان. remap شرطى وidempotent.
6. **Hive:** لا يُحذف. Daily POS لا يقرأه في Standalone.

---

## Remaining Risks

1. لا FK — orphans ممكنة خارج مسارات الخدمات.
2. REAL للتخزين المالي.
3. PIN ضعيف نسبياً.
4. مسارات الطاولات/QR/قائمة الويب ما زالت API-first.
5. لم يُقاس الأداء على 10k فاتورة / 100k بند.
6. Backup JSON غير مشفّر على القرص.
7. Connect إلى Laravel غير منفّذ — الخطة موثّقة فقط.
8. تقرير المخزون الدفتري غير مكتمل.
9. صلاحيات Connected ما زالت واسعة إن أعطى Laravel `orders.manage`.

لا تبدأ Licensing أو إعادة كتابة Sync أو Realtime أو كاميرا باركود قبل إغلاق M1–M3 على الأقل إن كان البيع التجاري يعني أجهزة غير موثوقة أو ربط سحابي.
