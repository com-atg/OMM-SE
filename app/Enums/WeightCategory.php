<?php

namespace App\Enums;

enum WeightCategory: string
{
    case Teaching = 'teaching';
    case Clinic = 'clinic';
    case Didactics = 'didactics';
    case Leadership = 'leadership';

    public function label(): string
    {
        return match ($this) {
            self::Teaching => 'Teaching',
            self::Clinic => 'Clinic',
            self::Didactics => 'Didactics',
            self::Leadership => 'Leadership',
        };
    }
}
