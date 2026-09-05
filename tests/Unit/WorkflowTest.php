<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Equipment\Domain\Criticality;
use App\Equipment\Domain\Equipment;
use App\ServiceRequests\Application\TriageV1;
use App\ServiceRequests\Domain\Impact;
use App\ServiceRequests\Domain\RequestState;
use App\ServiceRequests\Domain\ServiceRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Yaml\Yaml;

final class WorkflowTest extends TestCase
{
    /** @return iterable<array{string,string,bool}> */
    public static function transitions(): iterable
    {
        $allowed = ['triage' => ['submitted'], 'assign' => ['triaged'], 'reassign' => ['assigned'], 'resolve' => ['assigned'], 'cancel' => ['submitted', 'triaged', 'assigned']];
        foreach (RequestState::cases() as $state) {
            foreach ($allowed as $action => $states) {
                yield [$state->value, $action, in_array($state->value, $states, true)];
            }
        }
    }

    private function item(): ServiceRequest
    {
        $time = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        return new ServiceRequest('30000000-0000-7000-8000-000000000001', new Equipment('20000000-0000-7000-8000-000000000001', 'ASSET-1', 'Synthetic', Criticality::Normal, $time), new Principal('10000000-0000-7000-8000-000000000001', 'requester-a', 'Synthetic', Role::Requester), 'Synthetic', 'Synthetic', Impact::Stopped, $time);
    }

    #[DataProvider('transitions')]
    public function testEveryStateTransition(string $state, string $action, bool $allowed): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/workflow.yaml');
        self::assertIsArray($config);
        $framework = $config['framework'];
        self::assertIsArray($framework);
        $workflows = $framework['workflows'];
        self::assertIsArray($workflows);
        $workflow = $workflows['service_request'];
        self::assertIsArray($workflow);
        $places = $workflow['places'];
        self::assertIsArray($places);
        $builder = new DefinitionBuilder();
        foreach ($places as $place) {
            self::assertIsString($place);
            $builder->addPlace($place);
        }
        $transitions = $workflow['transitions'];
        self::assertIsArray($transitions);
        foreach ($transitions as $name => $transition) {
            self::assertIsString($name);
            self::assertIsArray($transition);
            $to = $transition['to'];
            self::assertIsString($to);
            $from = $transition['from'];
            foreach (is_array($from) ? $from : [$from] as $place) {
                self::assertIsString($place);
                $builder->addTransition(new Transition($name, $place, $to));
            }
        }
        $machine = new StateMachine($builder->build(), new MethodMarkingStore(true, 'state'));
        $item = $this->item();
        $item->setState($state);
        self::assertSame($allowed, $machine->can($item, $action));
    }

    public function testLateTriageAndPolicySnapshotRemainStable(): void
    {
        $item = $this->item();
        $decision = (new TriageV1())->decide(Criticality::Normal, Impact::Stopped, $item->submittedAt());
        $item->triage($decision);
        self::assertSame('2026-01-02T00:00:00+00:00', $decision->dueAt->format(DATE_ATOM));
        self::assertLessThan(new \DateTimeImmutable('2026-01-03T00:00:00Z'), $decision->dueAt);
        $snapshot = $item->view()['triage'];
        $newPolicy = new class implements \App\ServiceRequests\Domain\TriagePolicy {
            public function decide(Criticality $criticality, Impact $impact, \DateTimeImmutable $submittedAt): \App\ServiceRequests\Domain\TriageDecision
            {
                return new \App\ServiceRequests\Domain\TriageDecision('synthetic-test-v2', $criticality, $impact, \App\ServiceRequests\Domain\Priority::High, 3600, $submittedAt->modify('+1 hour'));
            }
        };
        try {
            $item->triage($newPolicy->decide(Criticality::Normal, Impact::Stopped, $item->submittedAt()));
            self::fail('Snapshot replacement accepted.');
        } catch (\LogicException) {
            self::assertSame($snapshot, $item->view()['triage']);
        }
    }
}
