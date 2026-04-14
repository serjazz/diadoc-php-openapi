<?php

declare(strict_types=1);

/**
 * Smoke/compat тесты для проверки PHP 7.1 совместимости:
 * - синтаксис и автозагрузка
 * - protobuf runtime <-> сгенерированный код (минимальный roundtrip)
 * - пару критичных функций без интеграционных вызовов к Diadoc
 *
 * Запуск:
 *   php tests/smoke/run.php
 *
 * Опции:
 *   SMOKE_SYNTAX=1  - прогонять php -l по src/ и phpProto/ (может занять время)
 */

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function fail(string $message, array $context = []): void
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    if (!empty($context)) {
        fwrite(STDERR, 'Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function assertEquals($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        fail($message, ['expected' => $expected, 'actual' => $actual]);
    }
}

function iterPhpFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $files[] = $fileInfo->getPathname();
        }
    }

    return $files;
}

function lintWithPhp(string $phpBinary, string $filePath): array
{
    // proc_open/exec могут быть отключены в php.ini; попробуем несколько вариантов.
    $cmd = $phpBinary . ' -l ' . escapeshellarg($filePath);

    if (function_exists('exec')) {
        $output = [];
        $exitCode = 0;
        @exec($cmd . ' 2>&1', $output, $exitCode);
        return ['exitCode' => $exitCode, 'output' => implode("\n", $output)];
    }

    if (function_exists('proc_open')) {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['exitCode' => 1, 'output' => 'proc_open failed'];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return ['exitCode' => $exitCode, 'output' => trim($stdout . "\n" . $stderr)];
    }

    return ['exitCode' => 1, 'output' => 'Both exec/proc_open are unavailable (check disable_functions in php.ini).'];
}

function requireAutoload(string $projectRoot): void
{
    $autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoloadPath)) {
        fail('Не найден vendor/autoload.php. Сначала выполните composer install.', ['path' => $autoloadPath]);
    }

    require_once $autoloadPath;
}

// -------------------------
// Start
// -------------------------

$projectRoot = dirname(__DIR__, 2);
out('Project root: ' . $projectRoot);

$phpVersion = phpversion();
out('PHP version: ' . ($phpVersion ?: 'unknown'));

// Целевая среда: PHP 7.1.* (желательно 7.1.33)
assertTrue(is_string($phpVersion) && strpos($phpVersion, '7.1.') === 0, 'Ожидался PHP 7.1.x');

assertTrue(extension_loaded('curl'), 'Не загружен ext-curl');
assertTrue(extension_loaded('json'), 'Не загружен ext-json');

requireAutoload($projectRoot);

// Простейшие проверки классов (контракт автозагрузки)
$classesToCheck = [
    'MagDv\\Diadoc\\DiadocApi',
    'MagDv\\Diadoc\\Filter\\DocumentsFilter',
    'MagDv\\Diadoc\\Signer\\OpensslSignerProvider',
];

foreach ($classesToCheck as $className) {
    assertTrue(class_exists($className), 'Не найден класс: ' . $className);
}

// Проверка синтаксиса (сильно снижает риск "разные версии" после даунгрейда)
$smokeSyntax = getenv('SMOKE_SYNTAX');
$smokeSyntax = ($smokeSyntax === false || $smokeSyntax === '') ? '1' : (string) $smokeSyntax;

if ($smokeSyntax === '1') {
    out('Running syntax checks (php -l) ...');

    $phpBinary = PHP_BINARY;
    $roots = [
        $projectRoot . DIRECTORY_SEPARATOR . 'src',
        $projectRoot . DIRECTORY_SEPARATOR . 'phpProto',
    ];

    foreach ($roots as $rootDir) {
        $files = iterPhpFiles($rootDir);
        out('Lint files in: ' . $rootDir . ' count=' . count($files));
        foreach ($files as $filePath) {
            $result = lintWithPhp($phpBinary, $filePath);
            if ((int) $result['exitCode'] !== 0) {
                fail('Syntax error detected in file: ' . $filePath, ['php -l output' => $result['output']]);
            }
        }
    }
}

// -------------------------
// Protobuf runtime <-> generated compatibility
// -------------------------

out('Checking protobuf roundtrip ...');

$protoClasses = [
    'Diadoc\\Proto\\Organization',
    'Diadoc\\Proto\\Department',
    'Diadoc\\Proto\\Box',
    'Diadoc\\Proto\\Counteragent',
    'Diadoc\\Proto\\Events\\Message',
    'Diadoc\\Proto\\Documents\\Document',
];

foreach ($protoClasses as $protoClass) {
    assertTrue(class_exists($protoClass), 'Не найден protobuf класс: ' . $protoClass);
}

foreach ($protoClasses as $protoClass) {
    /** @var \Google\Protobuf\Internal\Message $msg */
    $msg = new $protoClass();
    $bin1 = $msg->serializeToString();

    /** @var \Google\Protobuf\Internal\Message $msg2 */
    $msg2 = new $protoClass();
    $msg2->mergeFromString($bin1);

    $bin2 = $msg2->serializeToString();

    assertTrue(is_string($bin1) && is_string($bin2), 'serializeToString вернул не строку: ' . $protoClass);
    // Для пустых сообщений формат сериализации должен быть детерминированным.
    assertEquals($bin2, $bin1, 'Protobuf roundtrip mismatch: ' . $protoClass);
}

out('Protobuf roundtrip ok');

// -------------------------
// Critical helpers / constructors
// -------------------------

out('Checking DateHelper roundtrip ...');

$dt = new DateTime('2020-01-02 03:04:05', new DateTimeZone('UTC'));
$ticks = \MagDv\Diadoc\Helper\DateHelper::convertDateTimeToTicks($dt);
assertTrue(is_int($ticks), 'convertDateTimeToTicks должен вернуть int');
$dt2 = \MagDv\Diadoc\Helper\DateHelper::convertTicksToDateTime($ticks);
assertTrue($dt2 instanceof DateTime, 'convertTicksToDateTime должен вернуть DateTime');
assertEquals($dt2->getTimestamp(), $dt->getTimestamp(), 'DateHelper ticks->datetime roundtrip mismatch');

out('Checking constructors ...');

$api = new \MagDv\Diadoc\DiadocApi('dummy-client-id', 'dummy-secret');
assertTrue($api instanceof \MagDv\Diadoc\DiadocApi, 'DiadocApi constructor failed');

$filter = \MagDv\Diadoc\Filter\DocumentsFilter::create();
assertTrue($filter instanceof \MagDv\Diadoc\Filter\DocumentsFilter, 'DocumentsFilter::create failed');
assertEquals($filter->getFilterDocumentType(), \MagDv\Diadoc\Filter\DocumentsFilter::DOCUMENT_TYPE_ANY, 'Unexpected default filterDocumentType');
assertEquals($filter->getFilterDocumentClass(), \MagDv\Diadoc\Filter\DocumentsFilter::DOCUMENT_CLASS_INTERNAL, 'Unexpected default filterDocumentClass');

$filterArr = $filter->toFilter();
assertTrue(is_array($filterArr), 'toFilter() должен вернуть массив');
assertTrue(isset($filterArr['filterCategory']), 'toFilter(): отсутствует key filterCategory');
assertEquals($filterArr['filterCategory'], 'Any.Internal', 'Unexpected default filterCategory');

$signer = new \MagDv\Diadoc\Signer\OpensslSignerProvider(
    '/tmp/ca.pem',
    '/tmp/cert.pem',
    '/tmp/key.pem'
);
assertTrue($signer instanceof \MagDv\Diadoc\Signer\OpensslSignerProvider, 'OpensslSignerProvider constructor failed');

out('OK: smoke compat tests passed');

return;

