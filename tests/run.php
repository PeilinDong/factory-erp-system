<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';
require dirname(__DIR__) . '/src/Core/Autoloader.php';

\Erp\Core\Autoloader::register(dirname(__DIR__) . '/src');

$testFiles = glob(__DIR__ . '/Unit/*Test.php') ?: [];
$total = 0;
$failed = 0;
$assertions = 0;

foreach ($testFiles as $file) {
    require $file;
}

foreach (get_declared_classes() as $class) {
    if (!is_subclass_of($class, \Tests\TestCase::class)) {
        continue;
    }

    $test = new $class();
    foreach (get_class_methods($test) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $total++;
        try {
            $test->$method();
            $assertions += $test->assertions();
            echo "PASS {$class}::{$method}\n";
        } catch (\Throwable $exception) {
            $failed++;
            echo "FAIL {$class}::{$method}\n";
            echo $exception->getMessage() . "\n";
        }
    }
}

echo "\n{$total} tests, {$assertions} assertions, {$failed} failures\n";
exit($failed === 0 ? 0 : 1);

