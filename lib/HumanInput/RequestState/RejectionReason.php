<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

/** Why a retry's request state was refused. */
enum RejectionReason: string
{
    case Tampered = 'tampered';
    case Expired = 'expired';
    case WrongTarget = 'wrong_target';
    case WrongCaller = 'wrong_caller';
    case ArgumentsMismatch = 'arguments_mismatch';

    public function message(): string
    {
        return match ($this) {
            self::Tampered => 'The request state is malformed or its signature does not match.',
            self::Expired => 'The request state has expired; call the tool again without inputResponses to get a new one.',
            self::WrongTarget => 'The request state was issued for a different subject or tool.',
            self::WrongCaller => 'The request state was issued to a different caller; only the original caller may resume it.',
            self::ArgumentsMismatch => 'The arguments differ from the original call; resend the original arguments unchanged.',
        };
    }
}
