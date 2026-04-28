<?php

namespace App\Http\Controllers;

use App\Models\ActiveContext;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ActiveContextController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'qualification_id' => ['nullable', 'exists:qualifications,id'],
            'cohort_id'        => ['nullable', 'exists:cohorts,id'],
        ]);

        ActiveContext::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'qualification_id' => $data['qualification_id'] ?? null,
                'cohort_id'        => $data['cohort_id'] ?? null,
            ]
        );

        AuditLog::record('context.switched', null, $data);

        return back()->with('success', 'Assessing context updated.');
    }
}
