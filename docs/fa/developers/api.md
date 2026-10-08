---
title: "API در PHP"
description: "امضای توابع، نتیجه اعتبارسنجی، قالب‌بندی و API فیلد تاریخ."
---
# API در PHP

این توابع در [src/functions.php](../../../src/functions.php) تعریف شده‌اند و با بارگذاری افزونه در دسترس‌اند، حتی اگر بخش نامک خاموش باشد. اگر افزونه ممکن است غیرفعال شود، با `function_exists()` بررسی کنید. بیشتر توابع از Abzar و Daynum همراه، زیر namespace `PersianKit\Dependencies\` استفاده می‌کنند. تنظیمات بخش‌ها لزوما روی فراخوانی صریح تابع اثر ندارد.

## نتیجه اعتبارسنجی و خطا

اعتبارسنج‌ها برای ورودی نامعتبر `ValidationResult` برمی‌گردانند و خطای ورودی را exception نمی‌کنند. کلاس کامل `PersianKit\Dependencies\Eram\Abzar\Validation\ValidationResult` است:

- `isValid()` اعتبار ساختار و قواعد بررسی را می‌دهد؛ این استعلام هویت نیست.
- `isStrictlyValid()` علاوه بر اعتبار، نبود هشدار را می‌خواهد.
- `errors()` و `warnings()` پیام فارسی؛ `errorCodes()` و `warningCodes()` موردهای enum برای کد هستند.
- `detail()` شیء با ویژگی‌های فقط‌خواندنی یا `null` است، نه آرایه. `jsonSerialize()` خروجی آرایه با کلیدهای snake_case می‌دهد.
- شهر، بانک یا کد منطقه ناشناخته می‌تواند هشدار بدهد و مقدار lookup را `null` کند، بدون اینکه ورودی از نظر ساختار نامعتبر شود.

همه اعتبارسنج‌ها اعداد فارسی و عربی و فاصله اطراف ورودی را می‌پذیرند و نشانه‌های رایج کپی‌کردن مثل ZWNJ، bidi mark و dash یونیکد را پاک می‌کنند.

| تابع | بررسی و ویژگی‌های detail |
| --- | --- |
| `persian_kit_validate_national_id` | کد ملی؛ ورودی ۸ یا ۹ رقمی با صفر به ۱۰ رقم می‌رسد؛ تکرار یک رقم و checksum غلط رد می‌شود. `value`, `cityCode`, `city`, `province` |
| `persian_kit_validate_legal_id` | شناسه ملی ۱۱ رقمی و checksum؛ `value` |
| `persian_kit_validate_phone` | همراه یا ثابت ایران؛ `type`, `normalizedLocal`, `normalizedE164`, `operator`, `areaCode`, `city`, `province`. نوع از `PhoneNumberType::MOBILE` یا `LANDLINE` است |
| `persian_kit_validate_card_number` | کارت ۱۶ رقمی و Luhn، بدون الگوی تکراری؛ `value`, `bin`, `bank` |
| `persian_kit_validate_iban` | شبای ایران و mod-97؛ ۲۴ رقم بدون پیشوند، `IR` می‌گیرد؛ `value`, `bankCode`, `bank` |
| `persian_kit_validate_postal_code` | الگوی کد پستی ۱۰ رقمی؛ `postalCode`, `zoneCode` |
| `persian_kit_validate_plate_number` | قالب `NN[letter]NNN-NN` مثل `12ب345-67`؛ `twoDigit`, `letter`, `threeDigit`, `cityCode`, `type`, `province`, `provinces` |
| `persian_kit_validate_bill_id` | شناسه قبض و در صورت دادن آرگومان دوم، شناسه پرداخت و ارتباط checksum آن‌ها؛ `billId`, `paymentId`, `type` |

قالب‌بندی عدد، عدد به حروف، زمان نسبی و ترتیب برای ورودی نامناسب `FormatException` از نوع `RuntimeException` می‌دهند. واحد پول ناشناخته `InvalidArgumentException` می‌دهد؛ قالب‌بندی مبلغ نامعتبر هم می‌تواند خطای قالب‌بندی بدهد. تبدیل حروف به عدد برای متن نامفهوم یا سرریز `null` می‌دهد.

## تاریخ و ورودی تاریخ

`persian_kit_date()` با tokenهای PHP مثل `Y/m/d` تاریخ شمسی می‌دهد؛ خروجی اولیه اعداد انگلیسی دارد ولی از filter به نام `persian_kit_date_display` می‌گذرد و ممکن است اعداد تغییر کنند. تاریخ میلادی کنار آن اضافه نمی‌شود. `persian_kit_gregorian_date()` میلادی می‌دهد و از تبدیل عمومی عبور نمی‌کند. timestamp یا رشته قابل فهم برای `strtotime()` پذیرفته می‌شود. رشته بدون timezone از پیش‌فرض PHP پیروی می‌کند که معمولا در وردپرس UTC است. `0` و `''` یعنی اکنون؛ رشته نامعتبر پس از شکست `strtotime()` به timestamp صفر cast می‌شود. بهتر است timestamp معتبر و timezone مشخص بدهید.

`persian_kit_jalali_to_gregorian()` تاریخ شمسی یا میلادی را به قالب درخواستی می‌برد. اعداد فارسی، عربی و انگلیسی، جداکننده `-`، `/` یا `.` و ساعت بعد از تاریخ پذیرفته می‌شوند؛ ساعت در timezone سایت است. سال ۱۲۰۰ تا ۱۶۰۰ شمسی و از ۱۷۰۰ میلادی است. تاریخ نامعتبر `null` می‌دهد. مثلا `1403/05/12` برابر `2024-08-02` است و `1403/12/31` معتبر نیست.

`persian_kit_date_field_attributes()` attributeهای escapeشده ورودی را می‌دهد و فایل انتخابگر را بارگذاری می‌کند:

- `format`: پیش‌فرض `Y-m-d`؛ همچنین `Ymd` و `Y-m-d H:i:s` برای ساعت.
- `type`: پیش‌فرض `date`؛ همچنین `range`، `multiple`، `month` و `year`. حالت‌های غیرتکی قالب خروجی خود picker را دارند، مثلا بازه `2026-10-02/2026-10-05`.
- `min` و `max`: تاریخ شمسی یا میلادی؛ `disable_past` و `disable_future`: boolean؛ `locale`: تگ BCP 47، پیش‌فرض زبان صفحه.

گزینه ناشناخته نادیده گرفته می‌شود. بدون JavaScript ورودی به شکل قبلی می‌ماند. انتخاب تاریخ مقدار میلادی را به ورودی می‌دهد و eventهای `input` و `change` را اجرا می‌کند. ویژگی‌های `required`، `disabled`، `readonly`، `placeholder`، `aria-label`، `min` و `max` منتقل می‌شوند. `data-persian-kit-date-hint="off"` راهنمای تایپ را فقط برای screen reader نگه می‌دارد.

```php
<input type="text" name="birthday" <?php echo persian_kit_date_field_attributes(['max' => '2010-12-31']); ?>>
```

این قطعه در قالب وردپرس اجرا می‌شود. استایل با متغیرهای CSS به نام `--idp-*` روی `intl-datepicker.persian-kit-date-picker` تنظیم می‌شود. در JavaScript، `window.PersianKitDateField` متدهای `upgrade(input)`، `upgradeAll(root)`، `refresh(input)` و `picker(input)` دارد. بعد از تغییر خاموش مقدار با `.val()` از `refresh` استفاده کنید؛ event نمی‌فرستد. فیلدهای تازه خودکار آماده می‌شوند. `window.PersianKitCalendar.jalaliToIso(year, month, day)` و `isoToJalali('2026-10-02')` تاریخ نامعتبر را `null` می‌کنند.

## متن و عدد

توابع `to_persian_digits`، `to_english_digits` و `to_arabic_digits` با پیشوند `persian_kit_` سه نوع عدد را تبدیل می‌کنند. `normalize_persian` ی و ک عربی و اعداد عربی را فارسی می‌کند؛ تنظیم `teh_marbuta` بخش نگارش را نمی‌خواند و پردازشگر HTML نیست. `slug` حروف را اصلاح، اعداد را انگلیسی و فاصله، زیرخط و نیم‌فاصله را خط تیره می‌کند و نشانه‌های نامناسب URL را حذف می‌کند.

`half_space_fix` بر اساس الگوی پیشوند و پسوند کار می‌کند و بی‌خطا نیست. `keyboard_fix` چیدمان اشتباه را بین فارسی و لاتین تبدیل می‌کند؛ `sghl` به `سلام` و برعکس می‌رود. Shift هم پشتیبانی می‌شود. `persian_sort` آرایه جدید می‌دهد و برای ترتیب درست به `intl` نیاز دارد؛ بدون آن به sort بایتی برمی‌گردد. می‌توانید callback استخراج نام بدهید.

`number_format` جداکننده هزارگان را اضافه و علامت و اعشار را حفظ می‌کند؛ رشته عددی فارسی هم پذیرفته می‌شود. `number_to_words` منفی و اعشار را می‌نویسد؛ `words_to_number` برعکس آن است. `ordinal_word` و `ordinal_short` عدد مثبت می‌خواهند؛ خروجی کوتاه پیش‌فرض فارسی است و رشته‌های قدیمی `persian` و `english` را هم می‌پذیرد. `time_ago` گذشته و آینده را نسبت به `$now` یا اکنون توصیف می‌کند.

`currency_format` پیش‌فرض تومان با اعداد فارسی و نام واحد می‌دهد. `currency_convert` تومان و ریال را با ضریب ۱۰ تبدیل می‌کند و نتیجه صحیح را `int` می‌دهد. واحدها `toman` و `rial` هستند و بزرگی حروف مهم نیست.

`is_persian` همه متن و `has_persian` وجود یک حرف فارسی را بررسی می‌کند؛ حالت `complex` حروف مشترک عربی و نشانه‌های بیشتر را هم می‌پذیرد. `is_arabic` و `has_arabic` به حروف مخصوص عربی حساس‌اند؛ تشخیص عمومی زبان از هر کاراکتر یونیکد نیستند.

## امضای توابع

امضاها عینا از کد آمده‌اند؛ نام‌ها و مقدارهای پیش‌فرض را ترجمه نکنید.

```text
persian_kit_to_persian_digits(string $text): string;
```

```text
persian_kit_to_english_digits(string $text): string;
```

```text
persian_kit_to_arabic_digits(string $text): string;
```

```text
persian_kit_normalize_persian(string $text): string;
```

```text
persian_kit_slug(string $text): string;
```

```text
persian_kit_is_persian(string $text, bool $complex = false): bool;
```

```text
persian_kit_has_persian(string $text, bool $complex = false): bool;
```

```text
persian_kit_is_arabic(string $text): bool;
```

```text
persian_kit_has_arabic(string $text): bool;
```

```text
persian_kit_half_space_fix(string $text): string;
```

```text
persian_kit_keyboard_fix(string $text): string;
```

```text
persian_kit_persian_sort(array $items, ?callable $key = null): array;
```

```text
persian_kit_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string;
```

```text
persian_kit_gregorian_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string;
```

```text
persian_kit_jalali_to_gregorian(string $date, string $format = 'Y-m-d'): ?string;
```

```text
persian_kit_date_field_attributes(array $options = []): string;
```

```text
persian_kit_number_format(int|float|string $number, string $separator = ','): string;
```

```text
persian_kit_number_to_words(int|float $number): string;
```

```text
persian_kit_words_to_number(string $words): int|float|null;
```

```text
persian_kit_ordinal_word(int $n): string;
```

```text
persian_kit_ordinal_short(int $n, bool|string $digits = true): string;
```

```text
persian_kit_time_ago(int|string|\DateTimeInterface $timestamp, ?int $now = null, bool $persianDigits = true): string;
```

```text
persian_kit_currency_format( int|float|string $amount, string $unit = 'toman', bool $persianDigits = true, bool $withUnit = true, ): string;
```

```text
persian_kit_currency_convert(int|float $amount, string $from, string $to): int|float;
```

```text
persian_kit_validate_national_id(string $id): ValidationResult;
```

```text
persian_kit_validate_phone(string $phone): ValidationResult;
```

```text
persian_kit_validate_card_number(string $card): ValidationResult;
```

```text
persian_kit_validate_iban(string $iban): ValidationResult;
```

```text
persian_kit_validate_legal_id(string $id): ValidationResult;
```

```text
persian_kit_validate_postal_code(string $code): ValidationResult;
```

```text
persian_kit_validate_plate_number(string $plate): ValidationResult;
```

```text
persian_kit_validate_bill_id(string $billId, ?string $paymentId = null): ValidationResult;
```

## مثال قابل تکرار

اجراکننده مستندات توابع همراه را با UTC و filter بدون تغییر بارگذاری می‌کند؛ دیتابیس وردپرس بارگذاری نمی‌شود. سایت واقعی می‌تواند خروجی تاریخ را filter کند.

```php
echo persian_kit_to_persian_digits('Order 123'), "\n";
echo persian_kit_normalize_persian('كتاب يكي ١٢'), "\n";
echo persian_kit_slug('نمونه نوشته ۱۴۰۵'), "\n";
echo persian_kit_half_space_fix('می خواهم کتاب ها را'), "\n";
echo persian_kit_date('Y/m/d', 1790899200, new DateTimeZone('UTC')), "\n";
echo persian_kit_jalali_to_gregorian('1405/07/10'), "\n";
echo persian_kit_validate_phone('+989121234567')->detail()->normalizedLocal, "\n";
```

```text
Order ۱۲۳
کتاب یکی ۱۲
نمونه-نوشته-1405
می‌خواهم کتاب‌ها را
1405/07/10
2026-10-02
09121234567
```

مرتبط: [hookها](hooks.md)، [نمونه کاربردی](recipes.md) و [رفتار تاریخ](../dates.md).

## مثال‌های قالب‌بندی

این مثال‌ها از همان محیط جدا و زمان ثابت استفاده می‌کنند.

```php
echo persian_kit_to_english_digits('۱۲۳'), "\n";
echo persian_kit_to_arabic_digits('123'), "\n";
echo persian_kit_keyboard_fix('sghl'), "\n";
echo persian_kit_keyboard_fix('سلام'), "\n";
echo persian_kit_number_format('۱۲۳۴۵۶۷'), "\n";
echo persian_kit_number_format(1234567, '٬'), "\n";
echo persian_kit_number_to_words(123), "\n";
echo persian_kit_number_to_words(12.5), "\n";
echo persian_kit_words_to_number('بیست و یک'), "\n";
echo persian_kit_words_to_number('سه صد'), "\n";
echo json_encode(persian_kit_words_to_number('سلام')), "\n";
echo persian_kit_ordinal_word(3), "\n";
echo persian_kit_ordinal_word(30), "\n";
echo persian_kit_ordinal_short(3), "\n";
echo persian_kit_ordinal_short(3, false), "\n";
echo persian_kit_currency_format(1500000), "\n";
echo persian_kit_currency_format(1500000, 'rial', false, false), "\n";
echo persian_kit_currency_convert(100, 'toman', 'rial'), "\n";
echo persian_kit_currency_convert(1235, 'rial', 'toman'), "\n";
echo persian_kit_time_ago(1700000000, 1700003600), "\n";
echo persian_kit_jalali_to_gregorian('۱۴۰۳-۰۵-۱۲ ۱۸:۳۰', 'Y-m-d H:i'), "\n";
echo json_encode(persian_kit_jalali_to_gregorian('1403/12/31')), "\n";
echo persian_kit_gregorian_date('Y-m-d', 1790899200, new DateTimeZone('UTC')), "\n";
```

```text
123
١٢٣
سلام
sghl
1,234,567
1٬234٬567
یکصد و بیست و سه
دوازده ممیز پنج
21
300
null
سوم
سی‌ام
۳ام
3ام
۱،۵۰۰،۰۰۰ تومان
1،500،000
1000
123.5
۱ ساعت پیش
2024-08-02 18:30
null
2026-10-02
```
