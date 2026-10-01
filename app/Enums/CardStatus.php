<?php

namespace App\Enums;

/**
 * Card lifecycle status — the single source of truth for the
 * vocabulary used by the `cards.status` column.
 *
 * Values are the uppercase CM lifecycle names; legacy lowercase form
 * input is mapped through self::fromLegacy().
 */
enum CardStatus: string
{
    case NEW = 'NEW';
    case REGISTERED = 'REGISTERED';
    case INITIALIZED = 'INITIALIZED';
    case ISSUED = 'ISSUED';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case BLOCKED = 'BLOCKED';
    case REPLACED = 'REPLACED';

    /** Map a legacy lowercase form value onto the lifecycle enum. */
    public static function fromLegacy(?string $status): self
    {
        return match (strtolower((string) $status)) {
            'active' => self::ACTIVE,
            'inactive' => self::INACTIVE,
            'blocked' => self::BLOCKED,
            'registered' => self::REGISTERED,
            'initialized' => self::INITIALIZED,
            'issued' => self::ISSUED,
            'replaced' => self::REPLACED,
            'new' => self::NEW,
            default => self::NEW,
        };
    }
}
