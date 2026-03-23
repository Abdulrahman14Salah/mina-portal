<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(string $tab = 'overview'): View|RedirectResponse
    {
        $application = VisaApplication::with(['visaType', 'payments' => fn ($q) => $q->orderBy('stage'), 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])])->where('user_id', Auth::id())->first();

        if (! $application) {
            return view('client.no-application');
        }

        $this->authorize('view', $application);

        $validTabs = ['overview', 'documents', 'tasks', 'payments', 'profile', 'support'];

        if (! in_array($tab, $validTabs, true)) {
            $tab = 'overview';
        }

        $payments = $application->payments;

        return view('client.dashboard.index', compact('application', 'tab', 'payments'));
    }
}
