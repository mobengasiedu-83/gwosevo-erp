<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\Entra;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index()
    {
        return view('identity.activity', ['logins' => DB::table('login_events')->orderByDesc('id')->paginate(25, ['*'], 'logins'), 'changes' => AuditEvent::where('event', 'like', 'identity.%')->latest('id')->paginate(25, ['*'], 'changes')]);
    }

    public function integrations(Entra $entra)
    {
        return view('identity.integrations', ['entra' => $entra]);
    }
}
