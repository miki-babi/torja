<?php

namespace App\Enums;

enum MatchConfidence: string
{
    case Exact = 'exact';
    case Close = 'close';
    case None = 'none';
}
