<?php

namespace App\Services;

use App\Enums\EstadoAlumno;
use App\Enums\RolUsuario;
use App\Models\Alumno;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlumnoService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    public function paginateAlumnos(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $relations = ['usuario'];
        if (\Illuminate\Support\Facades\Schema::hasTable('membresias')) {
            $relations[] = 'membresiaActiva.tipoMembresia';
        }

        $query = Alumno::query()
            ->with($relations)
            ->orderByDesc('id');

        if (! empty($filters['estado']) && $filters['estado'] !== 'todos') {
            $query->where('estado', $filters['estado']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('dni', 'like', $search)
                    ->orWhereHas('usuario', function ($uq) use ($search) {
                        $uq->where('nombre', 'like', $search)
                            ->orWhere('apellido', 'like', $search)
                            ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellido, '')) LIKE ?", [$search])
                            ->orWhere('email', 'like', $search)
                            ->orWhere('telefono', 'like', $search);
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function paginateInstructores(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 50);
        $query = Usuario::query()
            ->where('rol', RolUsuario::Coach->value)
            ->orderBy('nombre');

        if (! empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', $search)
                    ->orWhere('apellido', 'like', $search)
                    ->orWhereRaw("CONCAT(nombre, ' ', COALESCE(apellido, '')) LIKE ?", [$search])
                    ->orWhere('email', 'like', $search)
                    ->orWhere('telefono', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    public function createAlumno(array $data): Alumno
    {
        $tenantId = $this->tenantContext->id();
        $email = strtolower(trim($data['email']));

        $this->ensureUniqueEmail($email);

        [$nombre, $apellido] = $this->splitNombre($data['nombre'], $data['apellido'] ?? null);

        // Si no se envía contraseña explícita, asignamos una contraseña por defecto ('12345678')
        $password = ! empty($data['password']) ? $data['password'] : '12345678';

        return DB::transaction(function () use ($tenantId, $nombre, $apellido, $email, $password, $data) {
            $avatarUrl = $this->resolveAvatarUrl($data['foto'] ?? $data['avatar_url'] ?? null);

            $usuario = Usuario::create([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'password' => $password,
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'avatar_url' => $avatarUrl,
                'rol' => RolUsuario::Alumno,
                'activo' => true,
            ]);

            $alumno = Alumno::create([
                'tenant_id' => $tenantId,
                'usuario_id' => $usuario->id,
                'dni' => $data['dni'] ?? $data['num_documento'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'fecha_nacimiento' => ! empty($data['fechaNacimiento'] ?? $data['fecha_nacimiento'] ?? null)
                    ? ($data['fechaNacimiento'] ?? $data['fecha_nacimiento'])
                    : null,
                'fecha_alta' => ! empty($data['fecha_alta']) ? $data['fecha_alta'] : now()->toDateString(),
                'estado' => $data['estado'] ?? EstadoAlumno::Activo->value,
                'notas' => $data['notas'] ?? $data['observaciones'] ?? null,
            ]);

            return $alumno->load('usuario');
        });
    }

    public function updateAlumno(Alumno $alumno, array $data): Alumno
    {
        $usuario = $alumno->usuario;
        $email = isset($data['email']) ? strtolower(trim($data['email'])) : $usuario->email;

        $this->ensureUniqueEmail($email, $usuario->id);

        [$nombre, $apellido] = isset($data['nombre'])
            ? $this->splitNombre($data['nombre'], $data['apellido'] ?? null)
            : [$usuario->nombre, $usuario->apellido];

        return DB::transaction(function () use ($alumno, $usuario, $nombre, $apellido, $email, $data) {
            $avatarUrl = $this->resolveAvatarUrl(
                $data['foto'] ?? $data['avatar_url'] ?? null,
                $usuario->avatar_url
            );

            $usuarioData = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'telefono' => $data['telefono'] ?? $usuario->telefono,
                'direccion' => $data['direccion'] ?? $usuario->direccion,
                'avatar_url' => $avatarUrl,
            ];

            if (! empty($data['password'])) {
                $usuarioData['password'] = $data['password'];
            }

            if (isset($data['estado'])) {
                $usuarioData['activo'] = $data['estado'] === EstadoAlumno::Activo->value;
            }

            $usuario->update($usuarioData);

            $alumno->update([
                'dni' => $data['dni'] ?? $data['num_documento'] ?? $alumno->dni,
                'direccion' => $data['direccion'] ?? $alumno->direccion,
                'fecha_nacimiento' => array_key_exists('fechaNacimiento', $data) || array_key_exists('fecha_nacimiento', $data)
                    ? (($data['fechaNacimiento'] ?? $data['fecha_nacimiento']) ?: null)
                    : $alumno->fecha_nacimiento,
                'estado' => $data['estado'] ?? $alumno->estado_value,
                'notas' => $data['notas'] ?? $data['observaciones'] ?? $alumno->notas,
            ]);

            return $alumno->refresh()->load('usuario');
        });
    }

    public function deleteAlumno(Alumno $alumno): void
    {
        DB::transaction(function () use ($alumno) {
            $usuario = $alumno->usuario;
            // Soft delete: marcamos inactivo y seteamos deleted_at sin destruir el registro físico
            $alumno->update(['estado' => EstadoAlumno::Inactivo->value]);
            $alumno->delete();

            if ($usuario) {
                $usuario->update(['activo' => false]);
                $usuario->tokens()->delete();
                $usuario->delete();
            }
        });
    }

    public function createInstructor(array $data): Usuario
    {
        $tenantId = $this->tenantContext->id();
        $email = strtolower(trim($data['email']));

        $this->ensureUniqueEmail($email);

        [$nombre, $apellido] = $this->splitNombre($data['nombre'], $data['apellido'] ?? null);

        $especialidades = is_array($data['especialidades'] ?? null)
            ? implode(', ', $data['especialidades'])
            : ($data['especialidades'] ?? null);

        $password = ! empty($data['password']) ? $data['password'] : '12345678';
        $avatarUrl = $this->resolveAvatarUrl($data['foto'] ?? $data['avatar_url'] ?? null);

        return Usuario::create([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'password' => $password,
            'telefono' => $data['telefono'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'especialidades' => $especialidades,
            'biografia' => $data['biografia'] ?? $data['observaciones'] ?? null,
            'avatar_url' => $avatarUrl,
            'rol' => RolUsuario::Coach,
            'activo' => ($data['estado'] ?? 'activo') === 'activo',
        ]);
    }

    public function updateInstructor(Usuario $instructor, array $data): Usuario
    {
        $email = isset($data['email']) ? strtolower(trim($data['email'])) : $instructor->email;
        $this->ensureUniqueEmail($email, $instructor->id);

        [$nombre, $apellido] = isset($data['nombre'])
            ? $this->splitNombre($data['nombre'], $data['apellido'] ?? null)
            : [$instructor->nombre, $instructor->apellido];

        $especialidades = array_key_exists('especialidades', $data)
            ? (is_array($data['especialidades']) ? implode(', ', $data['especialidades']) : $data['especialidades'])
            : $instructor->especialidades;

        $avatarUrl = $this->resolveAvatarUrl(
            $data['foto'] ?? $data['avatar_url'] ?? null,
            $instructor->avatar_url
        );

        $updateData = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'telefono' => $data['telefono'] ?? $instructor->telefono,
            'direccion' => $data['direccion'] ?? $instructor->direccion,
            'especialidades' => $especialidades,
            'biografia' => $data['biografia'] ?? $data['observaciones'] ?? $instructor->biografia,
            'avatar_url' => $avatarUrl,
            'activo' => isset($data['estado']) ? ($data['estado'] === 'activo') : $instructor->activo,
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $instructor->update($updateData);

        return $instructor->refresh();
    }

    public function configureUserAccess(int|string $id, string $email, string $password): Usuario
    {
        $alumno = Alumno::query()->with('usuario')->find($id);
        $usuario = $alumno ? $alumno->usuario : Usuario::query()->findOrFail($id);

        $cleanEmail = strtolower(trim($email));
        $this->ensureUniqueEmail($cleanEmail, $usuario->id);

        $usuario->update([
            'email' => $cleanEmail,
            'password' => $password,
            'activo' => true,
        ]);

        return $usuario->refresh();
    }

    public function resolveAvatarUrl(?string $rawAvatar, ?string $fallback = null): ?string
    {
        if ($rawAvatar === null || trim($rawAvatar) === '') {
            return $fallback;
        }

        $trimmed = trim($rawAvatar);
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $trimmed, $matches)) {
            $ext = strtolower($matches[1] ?? 'jpg');
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $binary = base64_decode($matches[2], true);
            if ($binary !== false) {
                $dir = public_path('uploads/avatars');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = 'avatar_' . ($this->tenantContext->id() ?? 0) . '_' . uniqid() . '.' . $ext;
                @file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $binary);

                return url('uploads/avatars/' . $filename);
            }
        }

        return $trimmed;
    }

    private function ensureUniqueEmail(string $email, ?int $ignoreUserId = null): void
    {
        $query = Usuario::query()->where('email', $email);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Ya existe una persona registrada con este email en el gimnasio.'],
            ]);
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitNombre(string $fullName, ?string $apellidoExplicit = null): array
    {
        $trimmed = trim($fullName);
        if ($apellidoExplicit !== null && trim($apellidoExplicit) !== '') {
            return [$trimmed, trim($apellidoExplicit)];
        }

        $parts = preg_split('/\s+/', $trimmed, 2);
        return [$parts[0] ?? $trimmed, $parts[1] ?? null];
    }
}
