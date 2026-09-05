<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Routing\RouterInterface;

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$root = dirname(__DIR__);
$schema = (new ValidatorBuilder())->fromYamlFile($root.'/openapi/openapi.yaml')->getResponseValidator()->getSchema();
if (!$schema->validate()) {
    throw new RuntimeException('OpenAPI document is invalid.');
}
$kernel = new App\Kernel('test', true);
$kernel->boot();
$router = $kernel->getContainer()->get('router');
if (!$router instanceof RouterInterface) {
    throw new LogicException();
}
$actual = [];
foreach ($router->getRouteCollection() as $route) {
    $paths = [$route->getPath()];
    if (str_contains($route->getPath(), '{action}')) {
        $paths = array_map(static fn (string $action): string => str_replace('{action}', $action, $route->getPath()), explode('|', $route->getRequirement('action') ?? ''));
    }
    foreach ($paths as $path) {
        foreach ($route->getMethods() as $method) {
            $actual[] = strtolower($method).' '.$path;
        }
    }
}
$declared = [];
if (null === $schema->paths) {
    throw new RuntimeException('API paths missing.');
}
foreach ($schema->paths as $path => $item) {
    foreach ($item->getOperations() as $method => $operation) {
        $declared[] = $method.' '.$path;
    }
}
sort($actual);
sort($declared);
if ($actual !== $declared) {
    throw new RuntimeException('Routes and OpenAPI operations differ.');
}
$files = ['README.md', 'AGENTS.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CHANGELOG.md', 'LICENSE', 'docs/architecture.md', 'docs/domain.md', 'docs/api.md', 'docs/security.md', 'docs/testing.md', 'docs/operations.md', 'docs/walkthrough.md', 'docs/dependencies.md'];
foreach ($files as $file) {
    $content = file_get_contents($root.'/'.$file);
    if (false === $content || strlen(trim($content)) < 40) {
        throw new RuntimeException('Required documentation missing: '.$file);
    }
    if (preg_match('~/home/|simplelogin|residual risk accepted|TODO|TBD~i', $content)) {
        throw new RuntimeException('Private or unfinished documentation marker: '.$file);
    }
    preg_match_all('~\]\(([^)]+)\)~', $content, $matches);
    foreach ($matches[1] as $target) {
        if (preg_match('~^https://~', $target)) {
            continue;
        }
        $path = explode('#', $target, 2)[0];
        if ('' !== $path && !file_exists(dirname($root.'/'.$file).'/'.$path)) {
            throw new RuntimeException('Broken local document link: '.$file);
        }
    }
}
$license = file_get_contents($root.'/LICENSE');
if (false === $license || !str_contains($license, 'Apache License') || !str_contains($license, 'Version 2.0')) {
    throw new RuntimeException('License missing.');
}
$kernel->shutdown();
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$inventory = file_get_contents($root.'/docs/dependencies.md');
if (!is_array($lock) || !is_string($inventory)) {
    throw new RuntimeException('Dependency inventory unavailable.');
}
foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
    $line = '| '.$package['name'].' | '.$package['version'].' | '.implode(', ', $package['license'] ?? []).' |';
    if (!str_contains($inventory, $line)) {
        throw new RuntimeException('Dependency inventory differs from lockfile.');
    }
}
echo "PASS: OpenAPI, route parity, required documents, local links and license scope.\n";
