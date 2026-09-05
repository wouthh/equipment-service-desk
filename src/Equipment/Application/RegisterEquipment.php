<?php

declare(strict_types=1);

namespace App\Equipment\Application;

use App\Access\Domain\Principal;
use App\Audit\Application\AuditWriter;
use App\Equipment\Application\Input\RegisterEquipmentInput;
use App\Equipment\Domain\Criticality;
use App\Equipment\Domain\Equipment;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;

final readonly class RegisterEquipment
{
    public function __construct(private Store $store, private Ids $ids, private ClockInterface $clock, private AuditWriter $audit)
    {
    }

    public function execute(Principal $actor, RegisterEquipmentInput $input, string $correlation): Equipment
    {
        $item = new Equipment($this->ids->next(), $input->assetTag, trim($input->name), Criticality::from($input->criticality), $this->clock->now());
        $this->store->em()->persist($item);
        $this->audit->record($actor, $item->id(), 'equipment.registered', ['criticality' => $input->criticality], $correlation);
        $this->store->em()->flush();

        return $item;
    }
}
