<?php

declare(strict_types=1);

use Test\enums\ConfigNames;
use Test\helpers\ApiClient;

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envPath)) {
    $params = parse_ini_file($envPath);
    if (is_array($params)) {
        foreach ($params as $name => $value) {
            putenv($name . '=' . $value);
        }
    }
}

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function fail(string $message): void
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

try {
    $client = new ApiClient();
    $api = $client->getApi();
} catch (\Throwable $e) {
    fail('Не удалось инициализировать ApiClient: ' . $e->getMessage());
}

out('== Diadoc live org checks ==');

try {
    $organizations = $api->getMyOrganizations();
} catch (\Throwable $e) {
    fail('getMyOrganizations() завершился ошибкой: ' . $e->getMessage());
}

$orgItems = $organizations->getOrganizations();
$orgCount = is_array($orgItems) ? count($orgItems) : 0;
out('Organizations count: ' . $orgCount);
if ($orgCount === 0) {
    fail('Для текущего токена не найдено организаций в этой среде (DIADOC_URL).');
}

out('Available organizations:');
foreach ($orgItems as $org) {
    $name = method_exists($org, 'getFullName') ? (string) $org->getFullName() : '';
    $inn = method_exists($org, 'getInn') ? (string) $org->getInn() : '';
    $orgId = method_exists($org, 'getOrgId') ? (string) $org->getOrgId() : '';
    out('- ' . $orgId . ' | ' . $inn . ' | ' . $name);
}

$orgId = getenv(ConfigNames::ORG_ID);
if (!is_string($orgId) || trim($orgId) === '') {
    $first = reset($orgItems);
    if ($first && method_exists($first, 'getOrgId')) {
        $orgId = (string) $first->getOrgId();
        out('ORG_ID не задан, используется первый доступный orgId: ' . $orgId);
    } else {
        fail('ORG_ID не задан и не удалось взять первый orgId.');
    }
}
$orgId = trim((string) $orgId);

out('Selected ORG_ID: ' . $orgId);

try {
    $permissions = $api->getMyPermissions($orgId);
    out('getMyPermissions(): OK');
    if (method_exists($permissions, 'serializeToString')) {
        out('Permissions payload bytes: ' . strlen((string) $permissions->serializeToString()));
    }
} catch (\Throwable $e) {
    out('getMyPermissions(): ERROR: ' . $e->getMessage());
}

try {
    $counteragents = $api->getCountragentsV2($orgId);
    out('getCountragentsV2(): OK');
    out('Counteragents totalCount: ' . $counteragents->getTotalCount());
} catch (\Throwable $e) {
    out('getCountragentsV2(): ERROR: ' . $e->getMessage());
}

out('Done.');

