<?php

namespace App\Enums;

/**
 * The availability filters the AI tools may apply when looking up bike stations.
 */
enum BikeStationAvailability: string
{
    case Any = 'any';
    case Rentable = 'rentable';
    case Returnable = 'returnable';
}
