<?php

namespace App\Enums;

enum CampaignChannel: string
{
    case Lead = 'lead';
    case Call = 'call';
    case Hybrid = 'hybrid';
}
