<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'rol' => 'nullable|in:admin,veterinario,asistente',
            'telefono' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'apellido' => $request->apellido,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'asistente',
            'telefono' => $request->telefono,
            'activo' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'registro',
                'User',
                $user->id,
                "Usuario {$user->email} registrado"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if (!$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->actualizarUltimoAcceso();

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'login',
                'User',
                $user->id,
                "Usuario {$user->email} inició sesión"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'logout',
                'User',
                $request->user()->id,
                "Usuario {$request->user()->email} cerró sesión"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load(['predictions', 'reports']),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'telefono' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datosAnteriores = $user->toArray();

        $user->update([
            'name' => $request->name,
            'apellido' => $request->apellido,
            'email' => $request->email,
            'telefono' => $request->telefono,
        ]);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'actualizar_perfil',
                'User',
                $user->id,
                "Usuario actualizó su perfil",
                $datosAnteriores,
                $user->toArray()
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'cambiar_password',
                'User',
                $user->id,
                "Usuario cambió su contraseña"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function listUsers(Request $request)
    {
        if (!$request->user()->esAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para esta acción',
            ], 403);
        }

        $query = User::query();

        if ($request->has('rol')) {
            $query->where('rol', $request->rol);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->activo);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function toggleUserStatus(Request $request, $id)
    {
        if (!$request->user()->esAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para esta acción',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $user->activo = !$user->activo;
        $user->save();

        $estado = $user->activo ? 'activado' : 'desactivado';

        if (class_exists(ActivityLog::class)) {
            ActivityLog::registrar(
                'cambiar_estado_usuario',
                'User',
                $user->id,
                "Usuario {$user->email} {$estado} por admin"
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Usuario {$estado} exitosamente",
            'data' => $user,
        ]);
    }
}
