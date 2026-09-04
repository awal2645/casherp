<?php

namespace App\Http\Controllers\Superadmin;

use App\Business;
use App\BusinessDocumentSetting;
use App\DocumentType;
use App\Industry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class IndustryDocumentProfileController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');

        return view('superadmin::industry_documents.index', [
            'industries' => Industry::with('documentTypes')->orderBy('sort_order')->get(),
            'documentTypes' => DocumentType::where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function update(Request $request, Industry $industry)
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');
        $data = $request->validate([
            'document_type_ids' => ['nullable', 'array', 'max:100'],
            'document_type_ids.*' => ['integer', 'exists:document_types,id'],
            'display_names' => ['nullable', 'array', 'max:100'],
            'display_names.*' => ['nullable', 'string', 'max:255'],
            'apply_to_existing' => ['nullable', 'boolean'],
        ]);
        $selectedIds = DocumentType::where('is_active', true)
            ->whereIn('id', $data['document_type_ids'] ?? [])
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $requiredCommon = DocumentType::whereIn('code', [
            'standard_quotation', 'standard_invoice', 'payment_receipt', 'credit_note',
        ])->pluck('id')->map(fn ($id) => (int) $id);
        if ($requiredCommon->diff($selectedIds)->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document_type_ids' => 'Every industry profile must retain Standard Quotation, Standard Invoice, Payment Receipt, and Credit Note.',
            ]);
        }

        DB::transaction(function () use ($industry, $selectedIds, $request, $data) {
            $pivot = [];
            foreach ($selectedIds as $index => $id) {
                $displayName = trim((string) data_get($data, 'display_names.'.$id));
                $pivot[$id] = [
                    'enabled_by_default' => true,
                    'display_name' => $displayName !== '' ? $displayName : null,
                    'sort_order' => ($index + 1) * 10,
                ];
            }
            // Keep disabled pivot rows so issued history remains classifiable
            // and discoverable. The enabled flag governs only new creation.
            DB::table('industry_document_types')
                ->where('industry_id', $industry->id)
                ->update(['enabled_by_default' => false, 'updated_at' => now()]);
            $industry->documentTypes()->syncWithoutDetaching($pivot);

            if ($request->boolean('apply_to_existing')) {
                $types = DocumentType::whereIn('id', $selectedIds)->get()->keyBy('id');
                Business::where('industry_id', $industry->id)
                    ->select(['id', 'industry_id'])
                    ->orderBy('id')
                    ->chunkById(100, function ($businesses) use ($selectedIds, $types) {
                        foreach ($businesses as $business) {
                            BusinessDocumentSetting::where('business_id', $business->id)
                                ->where('source', 'industry_default')
                                ->whereNotIn('document_type_id', $selectedIds)
                                ->update(['is_enabled' => false]);

                            foreach ($selectedIds as $id) {
                                $type = $types->get($id);
                                $setting = BusinessDocumentSetting::where('business_id', $business->id)
                                    ->where('document_type_id', $id)
                                    ->first();
                                if (! $setting) {
                                    BusinessDocumentSetting::create([
                                        'business_id' => $business->id,
                                        'document_type_id' => $id,
                                        'is_enabled' => true,
                                        'source' => 'industry_default',
                                        'prefix' => $type->default_prefix,
                                    ]);
                                } elseif ($setting->source === 'industry_default') {
                                    $setting->update(['is_enabled' => true]);
                                }
                            }
                        }
                    });
            }
        });

        return redirect()->route('superadmin.industry-documents.index')
            ->with('status', ['success' => 1, 'msg' => 'Industry document profile updated.']);
    }
}
