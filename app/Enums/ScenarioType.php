<?php

namespace App\Enums;

enum ScenarioType: string
{
    case ANNOUNCEMENT = 'announcement';
    case SALE = 'sale';
    case PRICE_CHANGED = 'price_changed';
    case AGENT_CHANGED = 'agent_changed';
    case BOOKING = 'booking';
    case SOLD = 'sold';
    case FEEDBACK = 'feedback';
    case WITHDRAWN = 'withdrawn';
    case DELAYED = 'delayed';
    case DELETED = 'deleted';
}
