<?php

declare(strict_types=1);

namespace App\Inbox;

use Leash\HumanInput\Pending\HumanInputNotifier;
use Leash\HumanInput\Pending\LoggingHumanInputNotifier;
use Leash\HumanInput\Pending\PendingInput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Email channel (PLAN §3.3): the approver gets the question and a signed link to the inbox item. */
final readonly class EmailHumanInputNotifier implements HumanInputNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private InboxLinks $links,
        private LoggingHumanInputNotifier $log,
        #[Autowire('%env(LEASH_APPROVER_EMAIL)%')]
        private string $approver,
    ) {}

    public function notify(PendingInput $pending): void
    {
        $this->log->notify($pending);

        $questions = array_filter(array_map(
            static fn(array $ask): ?string => \is_array($ask['params'] ?? null) && \is_string($ask['params']['message'] ?? null) ? $ask['params']['message'] : null,
            $pending->inputRequests,
        ), static fn(?string $question): bool => null !== $question);
        $link = $this->links->item($pending->id);

        $this->mailer->send((new Email())
            ->from('leash@leash.local')
            ->to($this->approver)
            ->subject(\sprintf('Input needed #%s: %s on %s', $pending->id, $pending->tool, $pending->subjectRef))
            ->text(\sprintf("%s\n\nAnswer here (link expires): %s\n", implode("\n", $questions), $link))
            ->html(\sprintf('<p>%s</p><p><a href="%s">Answer request #%s</a></p>', nl2br(htmlspecialchars(implode("\n", $questions))), htmlspecialchars($link), $pending->id)));
    }
}
