---
title: "راهنمای تنظیمات"
description: "نام دقیق بخش‌ها و گزینه‌ها، پیش‌فرض‌ها و وابستگی‌های نصب تازه."
---
# راهنمای تنظیمات

منوی **Persian Kit** به دسترسی `manage_options` نیاز دارد. کلید کارت هر بخش را روشن یا خاموش کنید، گزینه‌ها را تغییر دهید و **ذخیره تغییرات** را بزنید. یک فرم، تنظیمات تب‌های نمایش‌داده‌شده را با هم ذخیره می‌کند؛ بخش غایب یا در دسترس نبودن افزونه، تنظیمات قبلی آن را نگه می‌دارد. پیش‌فرض‌های زیر برای نصب تازه‌اند، نه به‌روزرسانی. `true` یعنی روشن، `false` خاموش، `[]` فهرست خالی و `""` متن خالی. کلیدها برای توسعه‌دهنده‌اند؛ برچسب‌ها متن واقعی رابط هستند.

## اعداد فارسی

مسیر: نمایش / اعداد فارسی. `digit_conversion`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | اعداد فارسی (Persian digits) | `false` |
| `dates` | تاریخ شمسی (Jalali dates) | `true` |
| `numbers` | تعداد نوشته‌ها و دیدگاه‌ها (Post and comment counts) | `true` |
| `prices` | قیمت‌های فروشگاه (Shop prices) | `true` |
| `emails` | ایمیل‌های ووکامرس (WooCommerce emails) | `false` |
## تاریخ شمسی

مسیر: نمایش / تاریخ شمسی. `date_conversion`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | تاریخ شمسی (Jalali dates) | `true` |
| `global_conversion` | تبدیل همه تاریخ‌ها (پیشرفته) (Convert every date (advanced)) | `false` |
| `jalali_archives` | بایگانی و تقویم شمسی (Jalali archives and calendar) | `true` |
| `jalali_permalinks` | تاریخ شمسی در پیوند نوشته‌ها (Jalali dates in post links) | `false` |
| `gregorian_date` | نمایش تاریخ میلادی در کنار شمسی (Show the Gregorian date too) | `false` |
| `gregorian_style` | تاریخ میلادی (Gregorian date) | `"numeric"` |
| `gregorian_order` | ترتیب (Order) | `"jalali_first"` |
| `gregorian_separator` | بین دو تاریخ (Between the dates) | `"parentheses"` |
| `month_names` | نام ماه‌ها (Month names) | `"auto"` |
| `month_names_by_locale` | نام ماه‌ها در %s (Month names in %s) | `[]` |
## ی و ک فارسی

مسیر: نگارش / ی و ک فارسی. `char_normalization`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | ی و ک فارسی (Persian ی and ک) | `true` |
| `normalize_on_save` | اصلاح حروف هنگام ذخیره (Fix letters on save) | `false` |
| `teh_marbuta` | ة هم به ه تبدیل شود (Also replace ة with ه) | `false` |
| `half_space_fix` | افزودن نیم‌فاصله هنگام ذخیره (Add half-spaces on save) | `false` |
## فونت پیشخوان

مسیر: نمایش / فونت پیشخوان. `admin_font`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | فونت پیشخوان (Admin font) | `true` |
| `font` | فونت (Font) | `"vazirmatn"` |
## کلید نیم‌فاصله

مسیر: نگارش / کلید نیم‌فاصله. `zwnj_editor`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | کلید نیم‌فاصله (Half-space key) | `true` |
## ووکامرس

مسیر: ووکامرس / ووکامرس. `woocommerce`

افزونه لازم و فعال: WooCommerce 9.9+.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | ووکامرس (WooCommerce) | `true` |
| `checkout_normalize` | اصلاح آنچه مشتری وارد می‌کند (Fix what customers type) | `true` |
| `checkout_validate` | بررسی تلفن و کد پستی (Check phone numbers and postcodes) | `true` |
| `national_id` | کد ملی (National ID) | `"off"` |
| `city_select` | فهرست شهرهای ایران (City list for Iran) | `false` |
| `allowed_states` | استان‌هایی که به آنها ارسال می‌کنید (Provinces you deliver to) | `[]` |
| `short_checkout` | پرداخت کوتاه‌تر وقتی چیزی ارسال نمی‌شود (Shorter checkout when nothing needs shipping) | `false` |
| `dates_admin` | تقویم شمسی در مدیریت فروشگاه (Jalali date picker in the shop admin) | `true` |
| `dates_analytics` | تاریخ شمسی در تجزیه و تحلیل ووکامرس (Jalali dates in WooCommerce Analytics) | `true` |
| `call_for_price` | نمایش متن به جای قیمت خالی (Show a text instead of an empty price) | `false` |
| `call_for_price_text` | متن در صفحه محصول (Text on the product page) | `""` |
| `call_for_price_list_text` | متن در فروشگاه و فهرست‌های دیگر (Text in the shop and other lists) | `""` |
| `call_for_price_link` | پیوند (Link) | `""` |
| `email_font` | فونت فارسی در ایمیل‌ها (Persian font in emails) | `true` |
## Contact Form 7

مسیر: یکپارچه‌سازی / Contact Form 7. `cf7`

افزونه لازم و فعال: Contact Form 7.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Contact Form 7 (Contact Form 7) | `true` |
## ACF

مسیر: یکپارچه‌سازی / ACF. `acf`

افزونه لازم و فعال: ACF.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | ACF (ACF) | `true` |
## Forminator

مسیر: یکپارچه‌سازی / Forminator. `forminator`

افزونه لازم و فعال: Forminator 1.50+.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Forminator (Forminator) | `true` |
## Gravity Forms

مسیر: یکپارچه‌سازی / Gravity Forms. `gravityforms`

افزونه لازم و فعال: Gravity Forms 2.9+.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Gravity Forms (Gravity Forms) | `true` |
## WPForms

مسیر: یکپارچه‌سازی / WPForms. `wpforms`

افزونه لازم و فعال: WPForms 1.9.1+.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | WPForms (WPForms) | `true` |
## Yoast SEO

مسیر: یکپارچه‌سازی / Yoast SEO. `yoast`

افزونه لازم و فعال: Yoast SEO.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Yoast SEO (Yoast SEO) | `true` |
## Rank Math SEO

مسیر: یکپارچه‌سازی / Rank Math SEO. `rank_math`

افزونه لازم و فعال: Rank Math SEO.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Rank Math SEO (Rank Math SEO) | `true` |
## WPML

مسیر: یکپارچه‌سازی / WPML. `wpml`

افزونه لازم و فعال: WPML.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | WPML (WPML) | `true` |
## Polylang

مسیر: یکپارچه‌سازی / Polylang. `polylang`

افزونه لازم و فعال: Polylang.

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | Polylang (Polylang) | `true` |
## نامک فارسی

مسیر: نگارش / نامک فارسی. `utilities`

| کلید | برچسب رابط | پیش‌فرض |
| --- | --- | --- |
| `enabled` | نامک فارسی (Persian slugs) | `true` |
| `persian_slugs` | استفاده از نامک فارسی (Use Persian slugs) | `true` |

## مقدارهای قابل انتخاب و وابستگی‌ها

- نام ماه: `auto`، `iranian`، `dari`، `pashto` و `kurdish`. کلید نگاشت هر زبان locale آن است. حالت میلادی `numeric` یا `named`، ترتیب `jalali_first` یا `gregorian_first` و جداکننده `parentheses`، `slash` یا `dash` است.
- فونت‌ها `vazirmatn`، `noto-sans-arabic` و `ibm-plex-sans-arabic` هستند. کد ملی `off`، `optional` یا `required` است. استان خالی یعنی همه. متن خالی قیمت محصول از پیش‌فرض زبان استفاده می‌کند؛ متن خالی فهرست از متن محصول و لینک خالی بدون لینک است.
- گزینه فرعی فقط در محدوده پشتیبانی‌شده بخش اثر دارد. گزینه‌های اعداد به کلید اصلی آن نیاز دارند. انتخابگر فرم از نمایش اصلی تاریخ جداست و تاریخ WooCommerce کنترل خودش را دارد. ثبت واحد پول، محافظت schema و حالت جایگزین فیلد سفارشی ممکن است با خاموش بودن اتصال هم باقی بمانند. پیش از غیرفعال‌سازی کل افزونه راهنما را بخوانید.
- ابزارها تنظیم ماژول جدا ندارند. رابط اصلاح حروف به مسیرهای REST بخش روشن ی و ک نیاز دارد؛ CLI با خاموش بودن آن هم کار می‌کند. تنظیمات در `persian_kit_settings` ذخیره و با Settings API پاک‌سازی می‌شوند.

مثال و محدودیت‌ها: [متن](text.md)، [تاریخ](dates.md)، [لینک](links.md)، [WooCommerce](woocommerce.md)، [فرم](forms.md)، [اصلاح نوشته‌ها](maintenance.md) و [سازگاری](compatibility.md).
