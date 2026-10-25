<?php

namespace App\Enums;

enum EstadoAlumno: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
    case Vencido = 'vencido';
    case Suspendido = 'suspendido';
    case Prospecto = 'prospecto';
    case Baja = 'baja';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
