<div dir="rtl">

# یکسان‌ساز متن فارسی برای وردپرس و ووکامرس

[![CI](https://github.com/kamranhajhossein/persian-text-normalizer/actions/workflows/ci.yml/badge.svg)](https://github.com/kamranhajhossein/persian-text-normalizer/actions/workflows/ci.yml)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![License GPL-2.0+](https://img.shields.io/badge/license-GPL--2.0%2B-green)

کاربر در جستجوی سایت می‌نویسد «كتاب» (با ک عربی)، محصول شما «کتاب» است، و نتیجه‌ای پیدا نمی‌شود. مشتری شماره را با ارقام فارسی «۰۹۱۲…» وارد می‌کند و تسویه‌حساب ووکامرس، پنل پیامک یا درگاه پرداخت خطا می‌دهد. یک نویسنده با کیبورد عربی «ي» تایپ می‌کند و حالا دو نشانی برای یک موضوع دارید.

این پلاگین همهٔ این‌ها را **بی‌سروصدا و بدون خراب کردن چیزی** درست می‌کند.

## چه کار می‌کند؟

| کجا | چه اتفاقی می‌افتد |
|---|---|
| **ذخیرهٔ نوشته، برگه و محصول** | «ي، ى، ك» ← «ی، ک»، ارقام عربی «٠١٢» ← فارسی «۰۱۲»، نیم‌فاصله‌های تکراری یا بی‌جا حذف می‌شوند. فقط متن تغییر می‌کند؛ تگ‌ها، کلاس‌ها، لینک‌ها، بلوک‌های گوتنبرگ، `<code>` و `<pre>` دست نمی‌خورند. |
| **جستجو** (سایت و محصولات ووکامرس) | حروف عربی، اِعراب و کشیده یکسان می‌شوند و نیم‌فاصله مثل فاصله حساب می‌شود؛ پس «مي‌خواهم» هم «می‌خواهم» را پیدا می‌کند و «خانهٔ» هم «خانه» را. |
| **فرم‌ها و تسویه‌حساب** | ارقام فارسی و عربی در فیلدهای تلفن، موبایل، کد پستی، کد ملی، شبا و کد تأیید به انگلیسی تبدیل می‌شوند. ووکامرس کلاسیک، **تسویه‌حساب بلوکی (Store API)**، Contact Form 7، Gravity Forms و هر فرمی که POST می‌کند. |
| **نامک (Slug)** | نامک‌های جدید یکسان ساخته می‌شوند تا نشانی تکراری نسازید. نامک‌های موجود **هرگز** تغییر نمی‌کنند، پس رتبهٔ سئو و لینک‌ها امن‌اند. |
| **دیدگاه‌ها، دسته‌ها، برچسب‌ها و ویژگی‌های محصول** | همان یکسان‌سازی. |
| **محتوای قدیمی** | ابزار «اصلاح محتوای موجود» با حالت **بررسی بدون تغییر** و پردازش دسته‌ای. تاریخ ویرایش، رونوشت‌ها و نامک‌ها دست نمی‌خورند. |

## نصب

۱. فایل `persian-text-normalizer.zip` را از [آخرین نسخه](https://github.com/kamranhajhossein/persian-text-normalizer/releases/latest) دانلود کنید.
۲. پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه.
۳. فعال کنید. همین؛ تنظیمات پیش‌فرض برای بیشتر سایت‌ها مناسب است.

تنظیمات و ابزار اصلاح در **ابزارها ← یکسان‌ساز فارسی** است. همان‌جا یک جعبهٔ «امتحان کنید» هست که نشان می‌دهد هر متن چطور ذخیره و جستجو می‌شود (نیم‌فاصله‌ها با رنگ زرد نمایش داده می‌شوند).

## اصلاح محتوای قبلی

قبل از هر کاری **پشتیبان بگیرید**. بعد:

۱. «بررسی (بدون تغییر)» را بزنید تا ببینید چند نوشته و دسته نیاز به اصلاح دارند و نمونه‌ها را چک کنید.
۲. «اصلاح همه» را بزنید.

یا با WP-CLI:

</div>

```bash
wp ptn fix --dry-run                  # فقط گزارش
wp ptn fix                            # اصلاح همه
wp ptn fix --post_type=product,page   # فقط این نوع‌ها
wp ptn test "كتاب‌‌هاي ١٤٠٥"            # ببینید یک متن چطور یکسان می‌شود
```

<div dir="rtl">

## مستثنا کردن یک بخش

برای آیات قرآن یا متن عربی که باید دقیقاً همان‌طور بماند:

</div>

```html
[ptn_ignore]بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ[/ptn_ignore]

<!-- ptn:ignore -->
<p>متن عربی که نباید تغییر کند</p>
<!-- /ptn:ignore -->
```

<div dir="rtl">

شورت‌کد `[ptn_ignore]` در خروجی سایت هیچ اثری ندارد و فقط محتوایش را محافظت می‌کند.

## فیلترها برای توسعه‌دهندگان

| فیلتر | کاربرد |
|---|---|
| `ptn_normalizer_options` | تغییر گزینه‌های یکسان‌سازی محتوای ذخیره‌شده |
| `ptn_digit_fields_pattern` | الگوی regex نام فیلدهایی که ارقامشان انگلیسی شود |
| `ptn_digit_rest_routes` | مسیرهای REST که ارقامشان انگلیسی شود (پیش‌فرض `/wc/store`) |
| `ptn_skip_post_types` | نوع‌هایی که هنگام ذخیره نادیده گرفته شوند |
| `ptn_bulk_post_types` | نوع‌هایی که ابزار اصلاح دسته‌ای پردازش می‌کند |
| `ptn_normalize_admin_search` | خاموش کردن یکسان‌سازی جستجو در پیشخوان |

استفادهٔ مستقیم در کد خودتان:

</div>

```php
$n = new PTN_Normalizer();
$n->text( 'كتاب يك' );                 // «کتاب یک»
$n->html( '<p class="ي">ي</p>' );      // فقط متن تغییر می‌کند: <p class="ي">ی</p>
PTN_Normalizer::search( 'مُحَمَّـد' );     // «محمد»
PTN_Normalizer::latin_digits( '۰۹۱۲' ); // «0912»
```

<div dir="rtl">

## چه چیزی را عمداً تغییر نمی‌دهد

- ارقام انگلیسی داخل متن (فقط در فیلدهای عددی فرم‌ها تبدیل انجام می‌شود).
- «ة» به «ه» (پیش‌فرض خاموش؛ از تنظیمات روشن می‌شود).
- اِعراب و کشیده در متن ذخیره‌شده (پیش‌فرض خاموش؛ جستجو همیشه آن‌ها را نادیده می‌گیرد).
- نامک‌ها و نشانی‌های موجود، تاریخ ویرایش و رونوشت‌ها.
- تگ‌ها و ویژگی‌های HTML، کامنت‌های بلوک گوتنبرگ، `<script>`، `<style>`، `<code>`، `<pre>` و `<textarea>`.

## آزمون‌ها

</div>

```bash
php tests/run-tests.php
```

<div dir="rtl">

آزمون‌های واحد هیچ وابستگی‌ای ندارند و در GitHub Actions روی PHP 7.4، 8.1 و 8.3 اجرا می‌شوند.

---

ساخته‌شده توسط [کامران حاج حسین](https://kamranh.com)، کارشناس SEO و برنامه‌نویس و توسعه‌دهندهٔ وب. مجوز GPL-2.0-or-later.

</div>

---

## English

**Persian Text Normalizer** fixes Arabic «ي/ك» letter variants, Arabic-Indic digits and messy ZWNJs on Persian WordPress & WooCommerce sites:

- **On save** — posts, pages, products, comments and terms are normalized; only text nodes change (tags, attributes, block comments, `<code>`/`<pre>` are preserved byte-for-byte).
- **Search** — queries ignore Arabic/Persian letter differences, diacritics and kashida; ZWNJ is treated as a space.
- **Forms** — Persian/Arabic digits become Latin in phone, postcode and ID fields (classic checkout, block checkout via Store API, CF7, Gravity Forms…).
- **Slugs** — new slugs are normalized; existing URLs are never changed.
- **Bulk fixer** — dry-run scan and batched fix of existing content from *Tools → Persian Normalizer* or `wp ptn fix`.

Requires WordPress 6.0+ and PHP 7.4+. Persian (fa_IR) translation included.
