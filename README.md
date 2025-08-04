# Central-PcPos-SamanKish-Laravel
راهنمای ادغام سرویس PC POS سامان کیش (SepPay)
این راهنما دستورالعمل‌های جامعی برای استفاده از کلاس PcPosService جهت پردازش پرداخت‌ها از طریق سیستم Central PC POS سامان کیش ارائه می‌دهد. این سرویس کل جریان تراکنش را مدیریت می‌کند، از جمله دریافت توکن دسترسی، دریافت شناسه یکتا، استعلام وضعیت تراکنش، ارسال درخواست پرداخت و در صورت نیاز لغو درخواست‌های در حال انتظار.
فهرست مطالب

پیش‌نیازها
پیکربندی
تنظیمات دستگاه
نحوه استفاده
جریان تراکنش
مدیریت خطاها
وابستگی‌ها
نمونه پاسخ
رفع اشکال

پیش‌نیازها

پروژه لاراول: این سرویس برای استفاده در پروژه‌های لاراول (ترجیحاً نسخه 8.x یا بالاتر) طراحی شده است.
اطلاعات سامان کیش: موارد زیر را از سامان کیش دریافت کنید:
client_secret
username
password
terminal_id


دستگاه PC POS مرکزی: اطمینان حاصل کنید که دستگاه POS شما از حالت Central PC POS پشتیبانی می‌کند و به درستی پیکربندی شده است.
وابستگی‌های PHP:
illuminate/support (برای مدیریت تنظیمات و کش)
illuminate/http (برای درخواست‌های HTTP)



پیکربندی
فایل config/seppay.php را در پروژه لاراول خود با تنظیمات زیر ایجاد کنید:
return [
    'client_secret' => env('SEPPAY_CLIENT_SECRET'), // دریافت از سامان کیش
    'username' => env('SEPPAY_USERNAME'), // دریافت از سامان کیش
    'password' => env('SEPPAY_PASSWORD'), // دریافت از سامان کیش
    'terminal_id' => env('SEPPAY_TERMINAL_ID'), // شماره ترمینال دستگاه
    'scope' => env('SEPPAY_SCOPE', 'SepCentralPcPos openid'),
    'token_url' => env('SEPPAY_TOKEN_URL', 'https://idn.seppay.ir/connect/token'),
    'identifier_url' => env('SEPPAY_IDENTIFIER_URL', 'https://cpcpos.seppay.ir/v1/PcPosTransaction/ReciveIdentifier'),
    'inquiry_url' => env('SEPPAY_INQUIRY_URL', 'https://cpcpos.seppay.ir/v1/PcPosTransaction/Inquery'),
    'payment_url' => env('SEPPAY_PAYMENT_URL', 'https://cpcpos.seppay.ir/v1/PcPosTransaction/StartPayment'),
];

متغیرهای محیطی مربوطه را به فایل .env خود اضافه کنید:
SEPPAY_CLIENT_SECRET=your_client_secret
SEPPAY_USERNAME=your_username
SEPPAY_PASSWORD=your_password
SEPPAY_TERMINAL_ID=your_terminal_id
SEPPAY_SCOPE=SepCentralPcPos openid
SEPPAY_TOKEN_URL=https://idn.seppay.ir/connect/token
SEPPAY_IDENTIFIER_URL=https://cpcpos.seppay.ir/v1/PcPosTransaction/ReciveIdentifier
SEPPAY_INQUIRY_URL=https://cpcpos.seppay.ir/v1/PcPosTransaction/Inquery
SEPPAY_PAYMENT_URL=https://cpcpos.seppay.ir/v1/PcPosTransaction/StartPayment

تنظیمات دستگاه
برای استفاده از قابلیت Central PC POS:

فعال‌سازی حالت Central PC POS:
به منوی تنظیمات دستگاه POS دسترسی پیدا کنید.
اطمینان حاصل کنید که دستگاه روی حالت Central PC POS تنظیم شده است (نه حالت مستقل).
این پیکربندی بسیار مهم است، زیرا در غیر این صورت دستگاه پرداخت را به درستی پردازش نمی‌کند.


اتصال شبکه:
مطمئن شوید دستگاه POS به اینترنت متصل است و می‌تواند با سرورهای سامان کیش ارتباط برقرار کند.


رفتار تراکنش:
در حالت Central PC POS، مبلغ پرداخت تا زمانی که تابع processPayment فراخوانی نشود روی دستگاه نمایش داده نمی‌شود.
پس از فراخوانی تابع، کارت را روی دستگاه بکشید، سپس مبلغ برای تأیید روی دستگاه نمایش داده می‌شود.



نحوه استفاده
کلاس PcPosService در فضای نام App\Services قرار دارد. برای استفاده، سرویس را تزریق یا نمونه‌سازی کنید و متد processPayment را با orderId و amount فراخوانی کنید.
مثال
use App\Services\PcPosService;

$pcPosService = new PcPosService();
$response = $pcPosService->processPayment('ORDER123', 100000); // مبلغ به ریال

if (isset($response['transaction_number'])) {
    echo "پرداخت موفق! شماره تراکنش: " . $response['transaction_number'];
} else {
    echo "پرداخت ناموفق: " . $response['response']['ErrorDescription'];
}

جریان تراکنش

فراخوانی processPayment:
این تابع فرآیند پرداخت را با دریافت توکن دسترسی، دریافت شناسه، بررسی وضعیت تراکنش و ارسال درخواست پرداخت آغاز می‌کند.


کشیدن کارت:
پس از فراخوانی processPayment، کارت را روی دستگاه POS بکشید.


نمایش مبلغ:
مبلغ پرداخت روی دستگاه POS برای تأیید نمایش داده می‌شود.


نتیجه:
سرویس نتیجه تراکنش را برمی‌گرداند، شامل شماره تراکنش در صورت موفقیت یا توضیح خطا در صورت شکست.



جریان تراکنش جزئی
متد processPayment مراحل زیر را طی می‌کند:

دریافت توکن دسترسی:
یک توکن جدید یا کش‌شده از سرور احراز هویت سامان کیش دریافت می‌کند.
در صورت وجود و معتبر بودن، از توکن‌های تازه‌سازی استفاده می‌کند.


دریافت شناسه:
یک شناسه یکتا برای تراکنش از سامان کیش درخواست می‌کند.


استعلام وضعیت تراکنش:
وضعیت تراکنش را با استفاده از شناسه بررسی می‌کند.
اگر کد خطای 30 (درخواست در حال انتظار) دریافت شود، درخواست را لغو کرده و پیشنهاد retry می‌دهد.


ارسال درخواست پرداخت:
جزئیات پرداخت شامل orderId، amount و terminal_id را ارسال می‌کند.
شامل داده‌های اضافی مانند پیام سفارشی سفارش و اقلام رسید است.


بازگرداندن نتیجه:
یک پاسخ ساختارمند با شماره تراکنش یا جزئیات خطا برمی‌گرداند.



مدیریت خطاها
سرویس خطاها را به طور مناسب مدیریت می‌کند:

خطاهای HTTP: با نام مرحله و توضیح خطا (مثلاً «خطا در دریافت شناسه تراکنش») قالب‌بندی می‌شوند.
مشکلات اتصال: پیامی کاربرپسند مانند «مهلت زمانی اتصال به سرویس پرداخت به پایان رسید» برمی‌گرداند.
خطاهای عمومی: پیامی پیش‌فرض برای خطاهای غیرمنتظره ارائه می‌دهد.
درخواست‌های در حال انتظار: درخواست‌های در حال انتظار (کد خطا 30) را به طور خودکار لغو کرده و پیشنهاد retry می‌دهد.

وابستگی‌ها

فریم‌ورک لاراول: برای مدیریت تنظیمات و کلاینت HTTP.
PHP: نسخه 7.4 یا بالاتر.
کلاینت Guzzle HTTP: همراه با illuminate/http لاراول ارائه می‌شود.
درایور کش: اطمینان حاصل کنید که یک درایور کش (مانند Redis یا فایل) در لاراول برای کش کردن توکن پیکربندی شده است.

نمونه پاسخ
پرداخت موفق
[
    'step' => 'Transaction Sent',
    'transaction_number' => '123456789',
    'response' => [
        'IsSuccess' => true,
        'TraceNumber' => '123456789',
        'ErrorDescription' => null,
    ],
]

پرداخت ناموفق
[
    'step' => 'ReciveIdentifier Failed',
    'response' => [
        'ErrorDescription' => 'خطا در دریافت شناسه تراکنش',
    ],
]

لغو درخواست در حال انتظار
[
    'step' => 'Cancelled',
    'response' => [
        'message' => 'درخواست لغو با موفقیت انجام شد. لطفاً مجدداً تلاش کنید.',
    ],
]

رفع اشکال

عدم نمایش مبلغ روی دستگاه:
مطمئن شوید دستگاه در حالت Central PC POS تنظیم شده است.
اتصال شبکه را بررسی کنید.
تأیید کنید که processPayment قبل از کشیدن کارت فراخوانی شده است.


خطا در دریافت توکن:
متغیرهای SEPPAY_CLIENT_SECRET، SEPPAY_USERNAME و SEPPAY_PASSWORD را در فایل .env بررسی کنید.
مطمئن شوید که token_url صحیح و در دسترس است.


کد خطا 30 (درخواست در حال انتظار):
سرویس به طور خودکار درخواست را لغو می‌کند. پس از 2 ثانیه تأخیر، پرداخت را دوباره امتحان کنید.


خطاهای اتصال:
اتصال اینترنت سرور و وضعیت API سامان کیش را بررسی کنید.
در صورت نیاز، تنظیمات مهلت زمانی در کلاینت HTTP لاراول را افزایش دهید.



برای پشتیبانی بیشتر، با تیم پشتیبانی سامان کیش تماس بگیرید یا به مستندات رسمی آنها مراجعه کنید.
