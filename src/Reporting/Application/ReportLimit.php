<?php

declare(strict_types=1);

namespace App\Reporting\Application;

final class ReportLimit extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Report limit exceeded.');
    }
}
