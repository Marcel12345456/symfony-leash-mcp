<?php

declare(strict_types=1);

namespace App\Dashboard;

use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Mermaid text of a state machine with the current place highlighted (decisions/037): the dumper only adds a 4px
 * stroke for the marking, so one more `style` line fills the current place.
 */
final class WorkflowDiagram
{
    public const string CURRENT_STYLE = 'fill:#f59e0b,stroke:#b45309,stroke-width:4px,color:#111';

    public function mermaid(WorkflowInterface $workflow, string $place): string
    {
        $definition = $workflow->getDefinition();
        $dump = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_STATEMACHINE, MermaidDumper::DIRECTION_LEFT_TO_RIGHT)
            ->dump($definition, new Marking([$place => 1]));
        // The dumper numbers places in definition order (place0, place1, …).
        $index = array_search($place, array_values($definition->getPlaces()), true);

        return false === $index ? $dump : $dump . \sprintf("\nstyle place%d %s", $index, self::CURRENT_STYLE);
    }
}
