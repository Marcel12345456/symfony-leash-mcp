<?php

declare(strict_types=1);

namespace App\Inbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Signed, expiring item URLs: a phone opens them from email or Telegram without logging in (decisions/024). */
final readonly class InboxLinks
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private UriSigner $signer,
        #[Autowire('%env(LEASH_INBOX_LINK_TTL)%')]
        private string $ttl,
    ) {}

    public function item(string $pendingId): string
    {
        return $this->signer->sign(
            $this->urls->generate('inbox_item', ['id' => $pendingId], UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateInterval($this->ttl),
        );
    }
}
