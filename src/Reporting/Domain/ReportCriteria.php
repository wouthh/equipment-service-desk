<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

final readonly class ReportCriteria
{
    public function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0 || $seconds > 31 * 86400) {
            throw new \InvalidArgumentException('Invalid report interval.');
        }
    }

    public static function parse(string $from, string $to): self
    {
        return new self(self::instant($from), self::instant($to));
    }

    private static function instant(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new \InvalidArgumentException('Invalid timestamp.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Invalid timestamp.');
        }

        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
