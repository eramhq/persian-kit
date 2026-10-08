---
title: "Action و filter"
description: "نام hookها، آرگومان‌ها، زمان اجرا و مقدار برگشتی."
---
# Action و filter

filter را در افزونه، `functions.php` قالب یا `persian_kit_loaded` و پیش از شروع بخش‌ها اضافه کنید. کنترل‌های زمان شروع، hook ثبت‌شده را بعدا در همان درخواست عوض نمی‌کنند. مقدار را برگردانید و برای خواندن چند آرگومان، تعداد accepted arguments را بدهید. این hookها دسترسی یا امنیت endpoint سفارشی شما را تامین نمی‌کنند.

## persian_kit_loaded

`action` — `—`

سرویس‌ها در plugins_loaded ثبت شده‌اند؛ بخش‌ها در after_setup_theme با اولویت ۲۰ راه می‌افتند.

## persian_kit_date_display

`filter` — `string $date, string $format, int $timestamp, DateTimeZone $timezone`

خروجی قالب‌بندی شمسی؛ رشته تاریخ را برگردانید.

## persian_kit_gregorian_date

`filter` — `bool $show, string $format, DateTimeInterface $date`

برای نیاوردن تاریخ میلادی کنار این تاریخ سایت، false برگردانید.

## persian_kit_gregorian_date_display

`filter` — `string $date, string $format, int $timestamp, DateTimeZone $timezone`

بخش میلادی پیش از اتصال؛ رشته خالی آن را حذف می‌کند.

## persian_kit_digit_conversion

`filter` — `bool $enabled, string $hook`

اجازه هر hook اعداد، از جمله woocommerce_emails و woocommerce_email_order_number.

## persian_kit_char_normalization

`filter` — `bool $enabled, string $hook`

اجازه wp_insert_post_data، preprocess_comment، pre_term_name، pre_term_description یا posts_search.

## persian_kit_should_normalize

`filter` — `bool $normalize, object $postContext, array $data, array $postarr`

اصلاح هنگام ذخیره هر نوشته بعد از موارد حذف قطعی؛ کنترل کار دسته‌ای نیست.

## persian_kit_utilities

`filter` — `bool $enabled, string $feature`

کنترل sanitize_title یا encode_links.

## persian_kit_jalali_archives

`filter` — `bool $enabled`

false نمایش شمسی فهرست و تقویم را خاموش می‌کند، نه شناسایی آدرس بایگانی.

## persian_kit_jalali_permalinks

`filter` — `bool $enabled`

false لینک ساخته‌شده نوشته را میلادی نگه می‌دارد؛ لینک قبلی همچنان رسیدگی می‌شود.

## persian_kit_multilingual

`filter` — `bool $multilingual`

ورود یا خروج از رفتار وابسته به زبان؛ با WPML و Polylang تنظیم‌شده خودکار است.

## persian_kit_is_persian_locale

`filter` — `bool $persian, string $locale`

پیش‌فرض fa و fa_*؛ تعیین فارسی بودن برای نگارش.

## persian_kit_reads_jalali

`filter` — `bool $reads, string $locale`

زبان نمایش شمسی و اعداد در سایت چندزبانه؛ جدا از زبان نگارش.

## persian_kit_calendar_names

`filter` — `string $set, string $locale`

یکی از iranian، dari، pashto یا kurdish؛ برای هر locale در درخواست نگه داشته می‌شود.

## persian_kit_current_locale

`filter` — `string $locale`

زبان خواندن صفحه، ایمیل یا پیشخوان در درخواست فعلی.

## persian_kit_content_locale

`filter` — `?string $locale, string $objectType, int $objectId`

زبان محتوای post یا term؛ شناسه صفر یعنی مورد در حال ذخیره؛ null نامشخص و برای نگارش فارسی حساب می‌شود.

## persian_kit_woocommerce_validate

`filter` — `bool $validate, string $rule, string $group, string $value`

قاعده phone، postcode یا national_id؛ گروه billing یا shipping و کد ملی همیشه billing.

## persian_kit_woocommerce_cities

`filter` — `array $cities`

کد استان به فهرست نام‌ها مثل THR؛ یک بار در درخواست، پیشنهادها و نام ذخیره‌شده را تغییر می‌دهد.

## persian_kit_analytics_jalali_intervals

`filter` — `bool $enabled, string $route, WP_REST_Request $request`

خارج کردن مسیر stats درخواست‌کننده wc-analytics از بازه شمسی.

## persian_kit_schema_rial_prices

`filter` — `bool $enabled, string $currency`

false واحد گره را نگه می‌دارد و به IRR تبدیل نمی‌کند.

## persian_kit_email_font_family

`filter` — `string $stack`

فهرست CSS فونت نصب‌شده؛ مقدار دارای ; { } < > رد می‌شود.

## persian_kit_acf_jalali_value

`filter` — `bool $jalali, array $field`

false مقدار قالب‌بندی‌شده این فیلد ACF را میلادی نگه می‌دارد.

## persian_kit_conflict_policies

`filter` — `array $policies`

راهنمای سازگاری با کلید فایل افزونه؛ name/type/summary/handles/recommendations/note و import و active_when اختیاری.

## persian_kit_import_sources

`filter` — `array $sources`

شیءهای پیاده‌کننده Service\Import\Source؛ کلاس پایه AbstractSource و کلید منبع با حروف کوچک.

## persian_kit_import_time_budget

`filter` — `int|float $seconds`

پیش‌فرض ۸ ثانیه، حداقل ۱؛ برای هر درخواست یا دسته import.

## مثال

عددهای عنوان را همان شکل تایپ‌شده نگه دارید و بقیه filterهای فعال را تغییر ندهید:

```php
add_filter('persian_kit_digit_conversion', function (bool $enabled, string $hook): bool {
    return $hook === 'the_title' ? false : $enabled;
}, 10, 2);
```

بخش مربوط باید در تنظیمات روشن باشد؛ true برگرداندن، بخش خاموش را راه نمی‌اندازد. [تنظیمات](../settings.md)، [API](api.md)، [نمونه‌ها](recipes.md) و [قرارداد کد بخش‌ها](../../../src/Contracts/ModuleInterface.php) را ببینید. کلاس‌های داخلی سرویس تضمین سازگاری جدا ندارند.

## قرارداد بخش‌ها و منبع انتقال

برای توسعه داخل مخزن، [ModuleInterface](../../../src/Contracts/ModuleInterface.php) متدهای `category()` با مقدار `forms`، `commerce`، `compat` یا `null`، متد `requiredPlugins()` با name/check و version/minVersion/slug/url اختیاری، `isAvailable()` و `unavailableReason()` را تعریف می‌کند. وقتی افزونه لازم در دسترس و بخش روشن باشد `boot()` اجرا می‌شود؛ اگر بخش خاموش باشد `bootDisabled()` حالت جایگزین را ثبت می‌کند. `formsUsingFields()` فهرست فرم‌های متاثر را می‌دهد. بخش‌های داخلی در [ModuleRegistry](../../../src/Core/ModuleRegistry.php) ثبت‌اند و filter عمومی ثبت ماژول وجود ندارد. [IranianFieldTypes](../../../src/Modules/Forms/IranianFieldTypes.php) بررسی فیلد و مقدار یکسان‌شده را در یک جا تعریف می‌کند.

برای افزودن منبع انتقال، [Source](../../../src/Service/Import/Source.php) را پیاده کنید، شیءهای [Task](../../../src/Service/Import/Task.php) بدهید و از `persian_kit_import_sources` ثبت کنید. در دسترس بودن، مجوز، log و برگشت مشروط را بررسی کنید؛ SQL دلخواه به‌تنهایی یک کار انتقال کامل نیست. این interfaceها رفتار فعلی را توضیح می‌دهند و تضمین پایداری جدا ندارند.
