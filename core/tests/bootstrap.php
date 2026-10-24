<?php

declare(strict_types=1);

/*
 * PHPUnit applies the <php><env> block of phpunit.xml to $_ENV and putenv(),
 * but it never touches $_SERVER. Laravel's env() helper reads $_SERVER first,
 * and both the Docker services and the CI runner already export DB_* into
 * $_SERVER. The container therefore won the race: config('database...') came
 * back as "argus" (the development database) even though phpunit.xml asked
 * for "argus_test", and RefreshDatabase truncated the seeded development data
 * on every test run.
 *
 * Re-applying the forced entries to all three channels makes phpunit.xml the
 * single source of truth for the test environment. Only entries marked
 * force="true" are applied; the rest keep coming from the environment, because
 * the compose services reach MySQL at host "mysql" while CI reaches it at
 * 127.0.0.1.
 */

require __DIR__ . '/../vendor/autoload.php';

$configuration = simplexml_load_file(__DIR__ . '/../phpunit.xml');

if ($configuration !== false && isset($configuration->php->env)) {
    foreach ($configuration->php->env as $variable) {
        $name = (string) $variable['name'];
        $value = (string) $variable['value'];

        if (((string) $variable['force']) !== 'true') {
            continue;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
