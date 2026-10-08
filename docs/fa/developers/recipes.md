---
title: "نمونه‌های کاربردی PHP"
description: "توابع را در قالب و handler سفارشی وردپرس به کار ببرید."
---
# نمونه‌های کاربردی PHP

این قطعه‌ها به محیط وردپرس، متغیرهای مشخص و مجوز لازم نیاز دارند و اسکریپت مستقل نیستند. مثال بدون وابستگی به سایت در [API](api.md) است؛ نتیجه اعتبارسنجی و exceptionها هم همان‌جا توضیح داده شده‌اند.

## وقتی ممکن است افزونه غیرفعال باشد

در قالب، اگر افزونه نبود از وردپرس استفاده کنید. تابع نمونه را دو بار ثبت نکنید.

```php
function mytheme_post_date(): string
{
    $timestamp = get_post_timestamp();
    if ($timestamp !== false && function_exists('persian_kit_date')) {
        return persian_kit_date('j F Y', $timestamp);
    }
    return get_the_date('j F Y');
}
```

## بررسی ورودی پیش از ذخیره

داخل handler دارای مجوز وردپرس: احراز هویت، nonce و دسترسی را جدا بررسی کنید. اعتبارسنج تلفن ثابت را هم می‌پذیرد و فقط موبایل نیست.

```php
$raw = $_POST['phone'] ?? '';
$input = is_string($raw) ? sanitize_text_field(wp_unslash($raw)) : '';
$result = persian_kit_validate_phone($input);
if (!$result->isValid()) {
    return new WP_Error('invalid_phone', implode(' ', $result->errors()), ['status' => 422]);
}
$phone = $result->detail()->normalizedE164;
```

## ساخت مقدار یکسان برای جستجو

این مثال یک meta سفارشی می‌نویسد و همان filter جستجوی داخلی نیست. شناسه نوشته مجاز و متن خام را تامین کنید.

```php
$searchable = persian_kit_to_english_digits(persian_kit_normalize_persian($rawText));
update_post_meta($postId, '_searchable_value', $searchable);
```

## قالب‌بندی جدا از ذخیره

ورودی عددی نامعتبر را مدیریت و خروجی را escape کنید. برای مبلغ 1500000 تابع پیش‌فرض پول، ۱،۵۰۰،۰۰۰ تومان می‌دهد.

```php
try {
    $formatted = persian_kit_number_format($userValue, '٬');
} catch (\RuntimeException $e) {
    $formatted = '';
}
echo esc_html($formatted);
echo esc_html(persian_kit_currency_format(1500000));
```

## مرتب‌سازی term و نمایش زمان نسبی

به term و نوشته وردپرس نیاز دارد؛ intl ترتیب درست فارسی می‌دهد. متن زمان نسبی به اکنون بستگی دارد.

```php
$sorted = persian_kit_persian_sort($terms, static fn (WP_Term $term): string => $term->name);
echo esc_html(persian_kit_time_ago(get_post_timestamp($post)));
```

## ساخت صریح نامک و تاریخ فارسی

این‌ها رشته می‌دهند؛ ذخیره نامک روی نوشته کار جداست. تبدیل اعداد را می‌توانید روی تاریخ اجرا کنید.

```php
$slug = persian_kit_slug('می‌خواهم بنویسم'); // می-خواهم-بنویسم
echo esc_html(persian_kit_to_persian_digits(persian_kit_date('Y/m/d')));
```

مرتبط: [hookها](hooks.md)، [API](api.md) و [توسعه](../../DEVELOPMENT.md).
