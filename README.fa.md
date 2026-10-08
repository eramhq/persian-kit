# Persian Kit

[English](README.md)

Persian Kit تاریخ شمسی، ابزارهای نوشتن فارسی و اتصال اختیاری به WooCommerce و فرم‌سازها را به وردپرس اضافه می‌کند. نسخه این کد **1.0.1** است.

## پیش‌نیازها

وردپرس **6.8+** و PHP **8.1+** با `mbstring` لازم است؛ مرتب‌سازی درست فارسی به `intl` نیاز دارد. فقط برای اتصال فروشگاه به WooCommerce **9.9+** نیاز دارید. [نصب](docs/fa/installation.md) و [سازگاری](docs/fa/compatibility.md) را ببینید.

## نصب

فایل ساخته‌شده `persian-kit.zip` را از بخش افزونه‌های وردپرس بارگذاری، نصب و فعال کنید. برای استفاده از کد مخزن:

```bash
composer install
npm ci
npm run build
```

دستور `npm run dist` فایل ZIP را می‌سازد؛ [پیش‌نیازهای ساخت](docs/fa/installation.md) را بخوانید.

## شروع کار

در **Persian Kit > نمایش**، تاریخ شمسی پیش‌فرض روشن و اعداد فارسی خاموش است. اعداد فارسی را روشن و ذخیره کنید تا `شماره 123` در محتوای پشتیبانی‌شده به شکل `شماره ۱۲۳` دیده شود، بدون تغییر متن ذخیره‌شده. در **نگارش**، تا وقتی نمی‌خواهید متن ذخیره‌شده تغییر کند، **اصلاح حروف هنگام ذخیره** را خاموش نگه دارید. [راهنمای شروع](docs/fa/overview.md) را دنبال کنید.

## مستندات

- [راهنمای فارسی](docs/fa/overview.md) · [English guides](docs/en/overview.md)
- [تنظیمات](docs/fa/settings.md) · [API توسعه‌دهنده](docs/fa/developers/api.md)
- [توسعه](docs/DEVELOPMENT.md) · [نگهداری مستندات](docs/README.md)
- [تغییرات نسخه‌ها](CHANGELOG.md) · [مجوز GPL-2.0-or-later](LICENSE)
