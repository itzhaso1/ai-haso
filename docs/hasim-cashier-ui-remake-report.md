# تقرير إعادة بناء واجهة كاشير حاسم (مطابقة Frontend الموقع)

تاريخ: 2026-09-01  
الفرع: `cursor/hasim-cashier-app-757c`  
المرجع: Laravel POS Web (`resources/views/tenant/pos/*` + `resources/css/app.css`)

---

## 1. Web UI الذي تمت دراسته

| ملف / منطقة | ماذا يغطي |
|-------------|-----------|
| `tenant/pos/cashier.blade.php` | تخطيط الكاشير 3 أعمدة، أنواع الطلب، البحث، الشبكة، السلة، مودال النجاح |
| `tenant/pos/partials/cashier-nav.blade.php` | شريط تنقل علوي + pills + تبديل فرع |
| `tenant/pos/partials/cashier-channel-stats.blade.php` | إحصائيات القنوات |
| `tenant/pos/partials/categories-sidebar.blade.php` | تصنيفات جانبية عمودية |
| `tenant/pos/tables/index.blade.php` | لوحة طاولات + حالات ألوان |
| `tenant/pos/orders/index.blade.php` | قائمة الطلبات |
| `tenant/pos/menu-orders/index.blade.php` | تغذية طلبات المنيو |
| `resources/css/app.css` | Brand tokens `#06C2A4`، أزرار، بطاقات، RTL |

---

## 2. Screens الموجودة في Web

كاشير، طاولات، طلبات، طلبات المنيو، فواتير، مطبخ، عملاء، تقارير، إعدادات، Login (بوابة الويب).

---

## 3. Screens الموجودة في Flutter (بعد التعديل)

Login، Splash، Workspace، Shell (Header+Pills)، Cashier Home، Cart Panel، Tables، Orders، Menu Orders، Settings، PosBlocked، Offline banner.

---

## 4. ماذا تم تغيير تصميمه

| قبل | بعد |
|-----|-----|
| Theme عام أزرق/Indigo | Brand حاسم Teal `#06C2A4` + emerald للـ CTA |
| Sidebar أيقونات عمودي + BottomNav | Header علوي + pills أفقية مثل الويب |
| تصنيفات Chips أفقية | Sidebar تصنيفات عمودي (Desktop) / شريط أفقي (Mobile) |
| منتجات قائمة عمودية فقط | شبكة 2–4 أعمدة حسب العرض |
| سلة صفحة ثانوية فقط | لوحة سلة ثابتة على Desktop/Tablet |
| Login Material عام | Login بشعار حاسم + حقول بطاقات |
| بدون مؤشرات Offline واضحة | نقطة اتصال + شريط Offline + Pending Sync |

---

## 5. ماذا تم توحيده

`HasimColors` / `HasimTypography` / `HasimSpacing` / `HasimRadius` / `HasimShadows` / `HasimTheme`  
Widgets: `HasimPrimaryButton`, `HasimSecondaryButton`, `HasimTextField`, `HasimEmptyState`, `HasimErrorState`, `HasimLoading`, `HasimProductCard`, `HasimTableCard`, `HasimStatusPill`, `HasimOfflineBanner`

---

## 6. Design System

مستخرج من الموقع — انظر `docs/hasim-cashier-ui-audit.md` و`lib/core/theme/`.

---

## 7. Responsive behavior

| Breakpoint | السلوك |
|------------|--------|
| `< 800` Mobile | منتجات شبكة 2 + زر سلة عائم + bottom sheet |
| `800–1099` Tablet | منتجات + سلة جنبًا إلى جنب |
| `≥ 1100` Desktop | تصنيفات \| منتجات \| سلة (مطابقة `xl:grid-cols-12`) |

---

## 8. Offline UX

نقطة ● متصل / ○ غير متصل في الـ header  
شريط: «أنت تعمل دون اتصال — سيتم مزامنة الطلبات عند عودة الإنترنت.»  
عداد «N عمليات بانتظار المزامنة»  
حالات الطلب في المزامنة: Pending / Synced / Failed + Retry عبر SyncEngine

---

## 9. API changes

**لا تغييرات Laravel في هذه الجولة.**  
الواجهة تستهلك `/api/cashier/v1` الموجود.  
لا تعديل على `apps/hasim` أو حاسم شات.

---

## 10. Tests

`flutter analyze` — بدون أخطاء  
`flutter test` — 3 نجح (tokens + offline + widget smoke)

---

## 11. Screens التي ما زالت مختلفة (PARTIAL)

| شاشة | السبب |
|------|--------|
| فواتير / مطبخ / عملاء كاملة | لم تُبنَ كشاشات مستقلة بعد (روابط API موجودة جزئيًا) |
| حوارات نقل/دمج/تقسيم طاولة | الأزرار موجودة على الويب؛ Flutter يحتاج dialogs كاملة فوق الـ API الجاهز |
| صوت طلبات المنيو من ملف | تبديل الإعدادات موجود؛ يحتاج `audioplayers` + asset |
| فلاتر طلبات الويب المتقدمة | تبسيط للحالة الحالية |
| طباعة أصلية ESC/POS | خيار بعد النجاح موجود؛ الطباعة الأصلية لاحقًا |

---

## 12. ما لم تتم مطابقته ولماذا

1. **Livewire/Alpine realtime exact** — Flutter يستخدم polling بدل Echo/Pusher حاليًا.  
2. **طباعة متصفح window.print** — على الموبايل تحتاج مسار طباعة أصلي منفصل.  
3. **Pixel-perfect لكل shadow/spacing** — مطابقة هوية/هيكل؛ فروق محرك الرسم Flutter vs CSS متوقعة.  
4. **شاشات فواتير/مطبخ كاملة** — خارج نطاق إعادة بناء الكاشير الأساسي في هذه الجولة؛ موثّقة كـ PARTIAL.

---

## مصفوفة WEB → FLUTTER (مختصرة)

| Web | Flutter | الحالة |
|-----|---------|--------|
| Cashier Home 3-col | CashierHomeScreen | MATCHED (هيكل) |
| Categories sidebar | CategoriesPane | MATCHED Desktop / PARTIAL Mobile |
| Product grid | HasimProductCard grid | MATCHED |
| Cart panel | CartPanel | MATCHED |
| Success print modal | _showOrderSuccess | MATCHED |
| Table board | TablesScreen | MATCHED |
| Orders list | OrdersScreen | PARTIAL |
| Menu orders | MenuOrdersScreen | PARTIAL |
| Nav pills | ShellScreen | MATCHED |
| Login brand | LoginScreen | MATCHED |
| Offline | banner + sync | MATCHED (UX) |
