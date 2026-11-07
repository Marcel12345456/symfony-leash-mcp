<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Subject;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Leash\WorkflowMcp\Metadata\WorkflowMetadata;
use Symfony\Component\Workflow\WorkflowInterface;

/** Finds the subject by id in the Doctrine entity named by workflow metadata `mcp_subject`. */
final readonly class DoctrineSubjectProvider implements SubjectProviderInterface
{
    public function __construct(private ManagerRegistry $doctrine) {}

    public function find(WorkflowInterface $workflow, string $id): ?object
    {
        $class = WorkflowMetadata::subjectClass($workflow);
        if (null === $class || !class_exists($class)) {
            throw new \LogicException(\sprintf('Workflow "%s" needs metadata mcp_subject naming an entity class.', $workflow->getName()));
        }

        return $this->manager($class)->find($class, $id);
    }

    public function save(object $subject): void
    {
        $manager = $this->manager($subject::class);
        $manager->persist($subject);
        $manager->flush();
    }

    /** @param class-string $class */
    private function manager(string $class): ObjectManager
    {
        return $this->doctrine->getManagerForClass($class)
            ?? throw new \LogicException(\sprintf('%s is not a Doctrine entity.', $class));
    }
}
