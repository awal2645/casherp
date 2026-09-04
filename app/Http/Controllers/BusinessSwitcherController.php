<?php

namespace App\Http\Controllers;

use App\Services\BusinessContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessSwitcherController extends Controller
{
    /**
     * Switch the signed-in user to an active company they are authorised to access.
     *
     * The posted id is always resolved through the user's company access set,
     * so changing the request cannot cross into another customer's data.
     */
    public function switch(Request $request, BusinessContextService $context): RedirectResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
        ]);

        $business = $request->user()->accessibleBusinesses()
            ->where('business.is_active', true)
            ->where('business.id', $data['business_id'])
            ->with(['currency', 'industry'])
            ->firstOrFail();

        $context->activate($request, $request->user(), $business);

        return redirect()->route('home')->with('status', [
            'success' => 1,
            'msg' => 'Switched to '.$business->name.'.',
        ]);
    }
}
