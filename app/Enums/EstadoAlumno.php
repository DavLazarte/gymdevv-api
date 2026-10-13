<?php

namespace App\Enums;

enum EstadoAlumno: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
    case Suspendido = 'suspendido';
    case Prospecto = 'prospecto';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
