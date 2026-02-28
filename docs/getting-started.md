# Getting Started

```php
<?php
require_once "../src/ObtraceClient.php";

use Obtrace\Sdk\ObtraceClient;
use Obtrace\Sdk\ObtraceConfig;

$cfg = new ObtraceConfig(
    apiKey: "<API_KEY>",
    ingestBaseUrl: "https://injet.obtrace.ai",
    serviceName: "php-api"
);

$client = new ObtraceClient($cfg);
$client->log("info", "started");
$client->flush();
```
