# Persian Kit

[English](README.md)

Persian Kit تاریخ شمسی، ابزارهای نوشتن فارسی و اتصال اختیاری به WooCommerce و فرم‌سازها را به وردپرس اضافه می‌کند. نسخه این کد **1.0.2** است.

ساخته [ارم](https://eram.dev/fa/). صفحه افزونه و راهنماها: [eram.dev/fa/projects/persian-kit](https://eram.dev/fa/projects/persian-kit/) و [eram.dev/fa/docs/persian-kit](https://eram.dev/fa/docs/persian-kit/overview/).

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

## نرم‌افزارهای شخص ثالث

پرشین کیت این اجزای شخص ثالث را همراه دارد:

- [eram/abzar](https://eram.dev/fa/projects/abzar-php/): ابزارهای PHP برای کار با متن و اعداد فارسی، با مجوز MIT.
- [eram/daynum](https://eram.dev/fa/projects/daynum/): کتابخانهٔ PHP تاریخ شمسی، با مجوز MIT.
- [Alpine.js](https://alpinejs.dev): فریم‌ورک جاوااسکریپت با مجوز MIT که همراه وابستگی‌های دارای مجوز MIT خود (`@vue/reactivity` و `@vue/shared`) در اسکریپت مدیریت گنجانده شده است.
- [Vazirmatn](https://github.com/rastikerdar/vazirmatn): فونت فارسی از صابر راستی‌کردار، با مجوز SIL Open Font License 1.1.
- [Noto Sans Arabic](https://github.com/notofonts/arabic): فونتی از Google و Noto Project Authors، با مجوز SIL Open Font License 1.1.
- [IBM Plex Sans Arabic](https://github.com/IBM/plex): فونتی از IBM، با مجوز SIL Open Font License 1.1.
- [intl-datepicker](https://eram.dev/fa/projects/intl-datepicker/): وب‌کامپوننت انتخاب تاریخ با مجوز MIT، گنجانده‌شده در `public/js/datepicker.js`.
- [@internationalized/date](https://github.com/adobe/react-spectrum/tree/main/packages/@internationalized/date): کتابخانهٔ تقویم Adobe با مجوز Apache License 2.0، گنجانده‌شده در `public/js/datepicker.js`.
- فهرست استان‌ها و شهرهای Tapin از [Persian WooCommerce Shipping](https://wordpress.org/plugins/persian-woocommerce-shipping/) (GPLv3) در `public/data/pws-tapin.json`. فقط برای خواندن نشانی‌هایی استفاده می‌شود که آن افزونه با شناسه‌های Tapin ذخیره کرده است.

چون `public/data/pws-tapin.json` تحت GPLv3 است، افزونه به شکل توزیع‌شده تحت GPLv3 است. کد خود افزونه همچنان GPLv2 یا بالاتر است.

همهٔ اجزای همراه با GPL سازگارند. متن مجوز آن‌ها همراه افزونه است: `packages/eram/*/LICENSE`، `public/fonts/noto-sans-arabic/OFL.txt`، `public/fonts/vazirmatn/OFL.txt`، `public/fonts/ibm-plex-sans-arabic/OFL.txt`، `public/licenses/` و سربرگ `public/js/admin.js`.
