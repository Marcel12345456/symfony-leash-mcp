<?php

declare(strict_types=1);

namespace App\Agent\Replay;

use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * WORKAROUND (decisions/033, BUGS.md): records per seat instead of symfony/ai's RecordingProvider, whose default
 * signature hashes every MessageBag as `{}` and whose Cassette cannot be read back in full.
 * Resolves each request at once so the result (or the failure) lands in the cassette in call order.
 */
final readonly class RecordingSeatProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $provider,
        private SeatCassette $cassette,
    ) {}

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function supports(Model|string $model): bool
    {
        return $this->provider->supports($model);
    }

    public function invoke(Model|string $model, object|array|string $input, array $options = []): DeferredResult
    {
        $seat = SeatCassette::seatOf($input);
        try {
            $deferred = $this->provider->invoke($model, $input, $options);
            $result = $deferred->getResult();
        } catch (TransportExceptionInterface $e) {
            $this->cassette->timeout($seat, $e->getMessage());

            throw $e;
        } catch (PlatformException $e) {
            $this->cassette->error($seat, $e->getMessage());

            throw $e;
        }
        $this->cassette->result($seat, $result);

        return new DeferredResult(new PlainConverter($result), $deferred->getRawResult(), $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->provider->getModelCatalog();
    }
}
