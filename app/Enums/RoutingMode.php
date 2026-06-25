<?php

namespace App\Enums;

enum RoutingMode: string
{
    case Waterfall = 'waterfall';
    case ParallelAuction = 'parallel_auction';
    case SequentialPing = 'sequential_ping';
    case Weighted = 'weighted';
    case RoundRobin = 'round_robin';
    case Hybrid = 'hybrid';
}
