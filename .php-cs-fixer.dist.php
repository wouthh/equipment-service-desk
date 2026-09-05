<?php

declare(strict_types=1);
$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/migrations'])->notPath('reference.php')->append([__FILE__, __DIR__.'/public/index.php', __DIR__.'/bin/console', __DIR__.'/bin/docs-check.php']);

return (new PhpCsFixer\Config())->setRules(['@Symfony' => true])->setRiskyAllowed(false)->setFinder($finder);
