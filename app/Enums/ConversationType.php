<?php

namespace App\Enums;

/**
 * Chat room kinds — `dm` is a two-person conversation; the only room a
 * member can reach is the DM with their coach.
 */
enum ConversationType: string
{
    case Dm = 'dm';
}
