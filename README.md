# PHP-smpp-sms-sender-class
PHP smpp sms sender + flash sms

## Support Flash SMS!!

## Support UTF-8 Charters!!

sample code:

```php
require 'smpp.php';

$src  = "<SRC_PHONE_OR_TEXT>";
$dst  = "<DST_PHONE>";
$message = "<YOUR_MESSAGE>";

$s = new smpp(true);

$s->open("<SERVER_IP>", 2775, "<USERNAME>", "<PASSWORD>");

$utf = false;
$flash = false;
$s->sendMessage($src, $dst, $message, $utf, $flash);

$s->close();

```