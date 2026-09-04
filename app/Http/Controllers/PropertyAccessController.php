<?php

namespace App\Http\Controllers;

use App\Property;
use App\PropertyAccessGrant;
use App\Services\PropertyAccessService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class PropertyAccessController extends Controller
{
    public function index(PropertyAccessService $access)
    {
        $businessId = $this->businessId();
        $this->authorizeManage($access, $businessId);

        $properties = Property::where('business_id', $businessId)
            ->with('businessLocation:id,name')
            ->orderBy('name')
            ->get();
        $roles = Role::where('business_id', $businessId)
            ->where('name', '!=', 'Admin#'.$businessId)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($role) => [$role->id => str_replace('#'.$businessId, '', $role->name)]);
        $users = User::forBusiness($businessId)
            ->whereNull('users.deleted_at')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->user_full_name]);
        $grants = PropertyAccessGrant::where('business_id', $businessId)
            ->with('property.businessLocation:id,name')
            ->orderBy('property_id')
            ->orderBy('subject_type')
            ->get();
        $roleNames = $roles;
        $userNames = $users;

        return view('property.access', compact('properties', 'roles', 'users', 'grants', 'roleNames', 'userNames'))
            ->with('abilities', PropertyAccessService::ABILITIES);
    }

    public function store(Request $request, PropertyAccessService $access)
    {
        $businessId = $this->businessId();
        $this->authorizeManage($access, $businessId);
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'subject_type' => ['required', Rule::in(['role', 'user'])],
            'subject_id' => ['required', 'integer'],
            'abilities' => ['required', 'array', 'min:1', 'max:7'],
            'abilities.*' => ['required', Rule::in(array_keys(PropertyAccessService::ABILITIES))],
        ]);
        Property::where('business_id', $businessId)->findOrFail($data['property_id']);
        if ($data['subject_type'] === 'role') {
            Role::where('business_id', $businessId)->findOrFail($data['subject_id']);
        } else {
            User::forBusiness($businessId)->whereKey($data['subject_id'])->firstOrFail();
        }

        $abilities = array_values(array_unique($data['abilities']));
        if (! in_array('view', $abilities, true)) {
            $abilities[] = 'view';
        }

        PropertyAccessGrant::updateOrCreate(
            [
                'business_id' => $businessId,
                'property_id' => (int) $data['property_id'],
                'subject_type' => $data['subject_type'],
                'subject_id' => (int) $data['subject_id'],
            ],
            [
                'abilities' => $abilities,
                'created_by' => auth()->id(),
            ]
        );

        return back()->with('status', ['success' => 1, 'msg' => 'Property access assignment saved.']);
    }

    public function destroy(PropertyAccessGrant $grant, PropertyAccessService $access)
    {
        $businessId = $this->businessId();
        $this->authorizeManage($access, $businessId);
        abort_unless((int) $grant->business_id === $businessId, 404);
        $grant->delete();

        return back()->with('status', ['success' => 1, 'msg' => 'Property access assignment removed.']);
    }

    private function authorizeManage(PropertyAccessService $access, int $businessId): void
    {
        abort_unless(
            $access->isBusinessAdmin(auth()->user(), $businessId)
                || auth()->user()->canForBusiness('property.access.manage', $businessId),
            403,
            'Only an authorized company administrator may assign property access.'
        );
    }

    private function businessId(): int
    {
        return (int) request()->session()->get('user.business_id');
    }
}
