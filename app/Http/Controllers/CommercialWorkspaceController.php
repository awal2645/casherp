<?php

namespace App\Http\Controllers;

use App\Events\ContactCreatedOrModified;
use App\Services\CommercialWorkspaceService;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommercialWorkspaceController extends Controller
{
    public function shell(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);

        return response()->file(public_path('casherp-workspace.html'));
    }

    public function workspace(Request $request, CommercialWorkspaceService $commercial)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'view' => ['nullable', Rule::in([
                'overview', 'invoices', 'orders', 'quotations', 'drafts', 'returns',
                'fulfilment', 'payments', 'documents', 'receipts', 'customers',
            ])],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'document_type_id' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'scenario_code' => ['nullable', 'string', 'max:50'],
            'location_id' => ['nullable', 'integer', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ]);

        return response()->json(['data' => $commercial->payload($request, $data)]);
    }

    public function storeCustomer(Request $request, ContactUtil $contacts, ModuleUtil $modules)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        abort_if($businessId < 1 || ! $user->canAccessBusiness($businessId), 403);
        $isAdmin = $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
        abort_unless($isAdmin || $user->canForBusiness('customer.create', $businessId), 403);
        abort_unless($modules->isSubscribed($businessId), 422, 'The company subscription must be active before a customer can be created.');

        $data = $request->validate([
            'contact_type_radio' => ['required', Rule::in(['individual', 'business'])],
            'supplier_business_name' => ['nullable', 'required_if:contact_type_radio,business', 'string', 'max:191'],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);
        $input = $data + [
            'type' => 'customer',
            'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
            'contact_type' => $data['contact_type_radio'],
            'business_id' => $businessId,
            'created_by' => (int) $user->id,
            'credit_limit' => null,
            'opening_balance' => 0,
        ];
        unset($input['contact_type_radio']);

        $output = DB::transaction(function () use ($contacts, $modules, $input) {
            $created = $contacts->createNewContact($input);
            event(new ContactCreatedOrModified($input, 'added'));
            $modules->getModuleData('after_contact_saved', ['contact' => $created['data'], 'input' => $input]);
            $contacts->activityLog($created['data'], 'added');

            return $created;
        });

        return response()->json([
            'message' => $output['msg'] ?? 'Customer created.',
            'data' => $output['data']->only(['id', 'contact_id', 'name', 'supplier_business_name', 'mobile', 'email']),
        ], 201);
    }
}
