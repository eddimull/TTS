<?php

namespace App\Enums;

enum BandResource: string
{
    case Events = 'events';
    case Proposals = 'proposals';
    case Invoices = 'invoices';
    case Colors = 'colors';
    case Charts = 'charts';
    case Bookings = 'bookings';
    case Rehearsals = 'rehearsals';
    case Media = 'media';
    case Songs = 'songs';

    public function readPermission(): string
    {
        return 'read:' . $this->value;
    }

    public function writePermission(): string
    {
        return 'write:' . $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
