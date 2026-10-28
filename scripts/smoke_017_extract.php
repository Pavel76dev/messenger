<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$disk = storage_path('app/public');
@mkdir($disk, 0775, true);
$rel = '_smoke_017.txt';
$abs = $disk.DIRECTORY_SEPARATOR.$rel;
file_put_contents($abs, "Строка одна\nHello extract\n");

$svc = app(App\Services\AttachmentTextExtractor::class);
$ref = new ReflectionClass($svc);
$method = $ref->getMethod('extractByType');
$method->setAccessible(true);
$text = $method->invoke($svc, $abs, 'text/plain', 'txt');
@unlink($abs);

if (! str_contains($text, 'Hello extract')) {
    fwrite(STDERR, "FAIL: unexpected text: {$text}\n");
    exit(1);
}

echo "OK extract-txt\n";

$routerPrompt = config('llm.router_prompt');
$max = config('llm.context_max_chars');
if (! is_string($routerPrompt) || $routerPrompt === '' || ! is_int($max)) {
    fwrite(STDERR, "FAIL: llm config incomplete\n");
    exit(1);
}
echo "OK config\n";
