<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlumnoResource;
use App\Http\Resources\InstructorResource;
use App\Http\Resources\UsuarioResource;
use App\Models\Alumno;
use App\Models\Usuario;
use App\Services\AlumnoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    public function __construct(
        private readonly AlumnoService $alumnoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->input('tipo_persona') === 'instructor') {
            $paginator = $this->alumnoService->paginateInstructores($request->all());
            $items = InstructorResource::collection($paginator->items());

            return response()->json([
                'socios' => $items,
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        $paginator = $this->alumnoService->paginateAlumnos($request->all());
        $items = AlumnoResource::collection($paginator->items());

        return response()->json([
            'socios' => $items,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'telefono' => ['nullable', 'string', 'max:40'],
        ]);

        if ($request->input('tipo_persona') === 'instructor') {
            $instructor = $this->alumnoService->createInstructor($request->all());
            $resource = new InstructorResource($instructor);

            return response()->json([
                'message' => 'Instructor creado exitosamente.',
                'socio' => $resource,
                'data' => $resource,
            ], 201);
        }

        $alumno = $this->alumnoService->createAlumno($request->all());
        $resource = new AlumnoResource($alumno);

        return response()->json([
            'message' => 'Socio creado exitosamente.',
            'socio' => $resource,
            'data' => $resource,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if ($request->input('tipo_persona') === 'instructor') {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $resource = new InstructorResource($instructor);

            return response()->json([
                'socio' => $resource,
                'data' => $resource,
            ]);
        }

        $relations = ['usuario'];
        if (\Illuminate\Support\Facades\Schema::hasTable('membresias')) {
            $relations[] = 'membresiaActiva.tipoMembresia';
        }

        $alumno = Alumno::query()->with($relations)->find($id);
        if (! $alumno) {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $resource = new InstructorResource($instructor);

            return response()->json([
                'socio' => $resource,
                'data' => $resource,
            ]);
        }

        $resource = new AlumnoResource($alumno);

        return response()->json([
            'socio' => $resource,
            'data' => $resource,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $isInstructorRequest = $request->input('tipo_persona') === 'instructor'
            || $request->has('especialidades')
            || $request->has('biografia');

        if ($isInstructorRequest) {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $updated = $this->alumnoService->updateInstructor($instructor, $request->all());
            $resource = new InstructorResource($updated);

            return response()->json([
                'message' => 'Instructor actualizado correctamente.',
                'socio' => $resource,
                'data' => $resource,
            ]);
        }

        $alumno = Alumno::query()->with('usuario')->find($id);
        if (! $alumno) {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $updated = $this->alumnoService->updateInstructor($instructor, $request->all());
            $resource = new InstructorResource($updated);

            return response()->json([
                'message' => 'Instructor actualizado correctamente.',
                'socio' => $resource,
                'data' => $resource,
            ]);
        }

        $updated = $this->alumnoService->updateAlumno($alumno, $request->all());
        $resource = new AlumnoResource($updated);

        return response()->json([
            'message' => 'Socio actualizado correctamente.',
            'socio' => $resource,
            'data' => $resource,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($request->input('tipo_persona') === 'instructor') {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $instructor->tokens()->delete();
            $instructor->delete();

            return response()->json([
                'message' => 'Instructor eliminado correctamente.',
            ]);
        }

        $alumno = Alumno::query()->with('usuario')->find($id);
        if (! $alumno) {
            $instructor = Usuario::query()->where('rol', RolUsuario::Coach->value)->findOrFail($id);
            $instructor->tokens()->delete();
            $instructor->delete();

            return response()->json([
                'message' => 'Instructor eliminado correctamente.',
            ]);
        }

        $this->alumnoService->deleteAlumno($alumno);

        return response()->json([
            'message' => 'Socio eliminado correctamente.',
        ]);
    }

    public function createUser(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $usuario = $this->alumnoService->configureUserAccess(
            $id,
            $validated['email'],
            $validated['password']
        );

        return response()->json([
            'message' => 'Cuenta de acceso configurada exitosamente.',
            'user' => new UsuarioResource($usuario),
            'data' => new UsuarioResource($usuario),
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $relations = ['usuario'];
        if (\Illuminate\Support\Facades\Schema::hasTable('membresias')) {
            $relations[] = 'membresiaActiva.tipoMembresia';
        }
        $alumno = $usuario->alumno()->with($relations)->first();

        return response()->json([
            'socio' => $alumno ? new AlumnoResource($alumno) : new InstructorResource($usuario),
            'user' => new UsuarioResource($usuario),
            'membresia_activa' => $alumno?->membresiaActiva,
            'stats' => [
                'asistencias_mes' => 0,
                'clases_reservadas' => 0,
            ],
        ]);
    }

    public function updatePerfilSocio(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $alumno = $usuario->alumno;

        if ($alumno) {
            $updated = $this->alumnoService->updateAlumno($alumno, $request->all());
            return response()->json([
                'message' => 'Perfil actualizado correctamente.',
                'socio' => new AlumnoResource($updated),
            ]);
        }

        $updated = $this->alumnoService->updateInstructor($usuario, $request->all());
        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'socio' => new InstructorResource($updated),
        ]);
    }
}
