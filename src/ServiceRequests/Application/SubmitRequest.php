<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application;

use App\Access\Domain\Principal;
use App\Audit\Application\AuditWriter;
use App\Equipment\Application\Catalog;
use App\ServiceRequests\Application\Input\SubmitInput;
use App\ServiceRequests\Domain\Impact;
use App\ServiceRequests\Domain\ServiceRequest;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;

final readonly class SubmitRequest
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock, private Catalog $catalog, private AuditWriter $audit)
    {
    }

    public function execute(Principal $actor, SubmitInput $input, string $correlation): ServiceRequest
    {
        $item = new ServiceRequest($this->ids->next(), $this->catalog->get($input->equipmentId), $actor, trim($input->title), trim($input->description), Impact::from($input->reportedImpact), $this->clock->now());
        $this->store->em()->persist($item);
        $this->audit->record($actor, $item->id(), 'request.submitted', ['impact' => $input->reportedImpact], $correlation);
        $this->store->em()->flush();

        return $item;
    }
}
