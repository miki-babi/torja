<?php

namespace App\Enums;

/**
 * Chat room kinds. `dm` is a two-person conversation between friends,
 * `group` is attached to a Ranks squad and `gym` to a Gym entity.
 */
enum ConversationType: string
{
    case Dm = 'dm';
    case Group = 'group';
    case Gym = 'gym';
}
