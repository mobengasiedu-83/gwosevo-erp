<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)]]);
        abort_unless($request->user()->canAccessOrganization((int) $data['organization_id']), 403);
        $old = $request->user()->active_organization_id;
        $request->user()->update(['active_organization_id' => $data['organization_id']]);
        IdentityAudit::change('organization.switched', $request->user()->id, ['organization_id' => $old], ['organization_id' => (int) $data['organization_id']]);

        return to_route('dashboard')->with('success', 'Active organization changed.');
    }
}
