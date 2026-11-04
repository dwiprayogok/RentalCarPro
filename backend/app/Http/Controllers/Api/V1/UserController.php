<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\Permissions;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** Pengguna & role. Semua aksi butuh user.manage (owner). */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', Search::like($s))->orWhere('email', 'like', Search::like($s))))
            ->when($f['role'] ?? null, fn ($q, $r) => $q->role($r))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('is_active', $s === 'aktif'))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate($f['per_page'] ?? 20)->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated(), $request->user());

        return (new UserResource($user))->additional(['message' => 'Pengguna berhasil ditambahkan.'])->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return (new UserResource($this->users->update($user, $request->validated(), $request->user())))
            ->additional(['message' => 'Data pengguna diperbarui.']);
    }

    public function status(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);
        $active = (bool) $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        return (new UserResource($this->users->setActive($user, $active, $request->user())))
            ->additional(['message' => $active ? "{$user->name} diaktifkan." : "{$user->name} dinonaktifkan dan keluar dari semua perangkat."]);
    }

    public function forceReset(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);
        $this->users->forcePasswordReset($user, $request->user());

        return response()->json(['message' => "Kata sandi {$user->name} direset. Tautan atur ulang dikirim ke {$user->email}."]);
    }

    /** Daftar role beserta permission (untuk tampilan matriks di halaman Pengguna & Role). */
    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::USER_MANAGE), 403);
        $roles = Role::query()->with('permissions:id,name')->get();
        // Jumlah pengguna per role (relasi users milik Spatie butuh guard model, jadi dihitung langsung).
        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(*) as n')->groupBy('role_id')->pluck('n', 'role_id');

        return response()->json(['data' => [
            'roles' => collect(UserRole::cases())->map(fn (UserRole $r) => [
                'name' => $r->value,
                'label' => $r->label(),
                'users_count' => (int) ($counts[$roles->firstWhere('name', $r->value)?->id] ?? 0),
                'permissions' => $roles->firstWhere('name', $r->value)?->permissions->pluck('name')->sort()->values() ?? [],
            ]),
            'permissions' => Permissions::all(),
        ]]);
    }
}
