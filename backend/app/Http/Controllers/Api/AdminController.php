<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403, 'Administrator access is required.');
    }

    private function log(Request $request, string $action, array $meta = []): void
    {
        DB::table('admin_activity_logs')->insert(['admin_id' => $request->user()->id, 'action' => $action, 'meta' => json_encode($meta), 'created_at' => now()]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorize($request);
        $query = DB::table('users')->select('id', 'name', 'email', 'role', 'created_at');
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

return response()->json($query->latest('id')->paginate(20));
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate(['role' => ['required', 'in:user,admin']]);
        DB::table('users')->where('id', $id)->update($data);
        $this->log($request, 'updated user', ['user_id' => $id, ...$data]);

        return response()->noContent();
    }

    public function currencies(Request $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(['currencies' => DB::table('currencies')->orderBy('code')->get()]);
    }

    public function updateCurrency(Request $request, int $id): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::table('currencies')->where('id', $id)->update([...$data, 'updated_at' => now()]);
        $this->log($request, 'updated currency', ['currency_id' => $id, ...$data]);

        return response()->noContent();
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(['users' => DB::table('users')->count(), 'conversions' => DB::table('conversions')->count(), 'active_alerts' => DB::table('rate_alerts')->where('status', 'active')->count()]);
    }

    public function logs(Request $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(['logs' => DB::table('admin_activity_logs')->join('users', 'users.id', '=', 'admin_activity_logs.admin_id')->select('admin_activity_logs.*', 'users.name as admin_name')->latest('admin_activity_logs.id')->limit(100)->get()]);
    }
}
