<?php

namespace App\Enums;

enum LogCategory: string
{
    case Application = 'application';
    case Api = 'api';
    case Monitor = 'monitor';
    case Domain = 'domain';
    case Ssl = 'ssl';
    case Security = 'security';
    case System = 'system';
    case User = 'user';
}
