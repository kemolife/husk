<?php

namespace App\Domain\Pipeline;

enum JobType: string
{
    case SCRIPT = 'script';
    case APPROVAL = 'approval';
}
