<?php

namespace App\Models;

/**
 * Alias de compatibilidad hacia Usuario (tabla `usuarios`).
 */
class User extends Usuario
{
    protected $table = 'usuarios';
}
