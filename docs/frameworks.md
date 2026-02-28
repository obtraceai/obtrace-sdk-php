# Frameworks

Laravel/Symfony middleware baseline:

```php
$wrapped = \Obtrace\Sdk\Framework::middleware($client, fn ($request) => $next($request));
```
