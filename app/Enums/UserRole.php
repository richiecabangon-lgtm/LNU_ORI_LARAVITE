<?php

namespace App\Enums;

enum UserRole: string
{
    case Faculty = 'faculty';
    case Coordinator = 'coordinator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Faculty => 'Faculty',
            self::Coordinator => 'Research Coordinator',
            self::Admin => 'Administrator',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
