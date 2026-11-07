<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Tool arguments → input DTO (Serializer) → constraints (Validator), with field-level messages (decisions/011, 013). */
final readonly class InputMapper
{
    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T>|null $class
     * @param array<string, mixed> $arguments
     *
     * @return T|null
     *
     * @throws InvalidInputException
     */
    public function map(?string $class, array $arguments): ?object
    {
        if (null === $class) {
            return null;
        }

        try {
            $input = $this->denormalizer->denormalize($arguments, $class, null, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (PartialDenormalizationException $e) {
            $messages = [];
            foreach ($e->getNotNormalizableValueErrors() as $error) {
                // Without a property type extractor the constructor's TypeError arrives path-less; its message names the argument.
                $messages[] = null === $error->getPath()
                    ? ($error->getPrevious()?->getMessage() ?? $error->getMessage())
                    : (null !== $error->getExpectedTypes() && null !== $error->getCurrentType()
                        ? \sprintf('%s: expected %s, got %s.', $error->getPath(), implode('|', $error->getExpectedTypes()), $error->getCurrentType())
                        : \sprintf('%s: %s', $error->getPath(), $error->getMessage()));
            }
            if (null !== $e->getExtraAttributesError()) {
                $messages[] = \sprintf('Unknown fields: %s.', implode(', ', $e->getExtraAttributesError()->getExtraAttributes()));
            }

            throw new InvalidInputException([] === $messages ? [$e->getMessage()] : $messages);
        } catch (MissingConstructorArgumentsException $e) {
            throw new InvalidInputException([\sprintf('Missing fields: %s.', implode(', ', $e->getMissingConstructorArguments()))]);
        } catch (ExtraAttributesException $e) {
            throw new InvalidInputException([\sprintf('Unknown fields: %s.', implode(', ', $e->getExtraAttributes()))]);
        } catch (ExceptionInterface $e) {
            throw new InvalidInputException([$e->getMessage()]);
        }

        if (!$input instanceof $class) {
            throw new \LogicException(\sprintf('Denormalizer returned %s instead of %s.', get_debug_type($input), $class));
        }

        $messages = [];
        foreach ($this->validator->validate($input) as $violation) {
            $messages[] = \sprintf('%s: %s', '' === $violation->getPropertyPath() ? 'input' : $violation->getPropertyPath(), $violation->getMessage());
        }
        if ([] !== $messages) {
            throw new InvalidInputException($messages);
        }

        return $input;
    }
}
