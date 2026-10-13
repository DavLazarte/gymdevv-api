<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Admin = 'admin';
    case Coach = 'coach';
    case Alumno = 'alumno';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
