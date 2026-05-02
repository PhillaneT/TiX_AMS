<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LmsConnection;
use App\Services\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use GuzzleHttp\Client;

class LmsConnectionController extends Controller
{
    public function index()
    {
        $connections = LmsConnection::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('integrations.index', compact('connections'));
    }

    public function create()
    {
        return view('integrations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'label'      => ['required', 'string', 'max:100'],
            'base_url'   => ['required', 'url', 'max:500'],
            'api_token'  => ['required', 'string', 'max:500'],
            'course_ids' => ['nullable', 'string'],
        ]);

        try {
            $courseIds = $this->parseCourseIds($request->input('course_ids', ''));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['course_ids' => $e->getMessage()]);
        }

        $connection = new LmsConnection([
            'user_id'   => auth()->id(),
            'provider'  => 'moodle',
            'label'     => $request->input('label'),
            'base_url'  => rtrim($request->input('base_url'), '/'),
            'course_ids' => $courseIds,
        ]);

        $connection->setApiToken($request->input('api_token'));
        $connection->save();

        AuditLog::record('lms.connection.created', $connection, [
            'label'    => $connection->label,
            'provider' => $connection->provider,
        ]);

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Moodle connection "' . $connection->label . '" created.');
    }

    public function edit(LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        $courseIdsStr = implode(', ', $integration->course_ids ?? []);

        return view('integrations.edit', compact('integration', 'courseIdsStr'));
    }

    public function update(Request $request, LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        $request->validate([
            'label'      => ['required', 'string', 'max:100'],
            'base_url'   => ['required', 'url', 'max:500'],
            'api_token'  => ['nullable', 'string', 'max:500'],
            'course_ids' => ['nullable', 'string'],
        ]);

        $integration->label    = $request->input('label');
        $integration->base_url = rtrim($request->input('base_url'), '/');

        try {
            $integration->course_ids = $this->parseCourseIds($request->input('course_ids', ''));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['course_ids' => $e->getMessage()]);
        }

        if ($request->filled('api_token')) {
            $integration->setApiToken($request->input('api_token'));
        }

        $integration->save();

        AuditLog::record('lms.connection.updated', $integration, [
            'label' => $integration->label,
        ]);

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Connection updated.');
    }

    public function destroy(LmsConnection $integration)
    {
        abort_if($integration->user_id !== auth()->id(), 403);

        AuditLog::record('lms.connection.deleted', null, [
            'connection_id' => $integration->id,
            'label'         => $integration->label,
        ]);

        $integration->delete();

        return redirect()
            ->route('integrations.index')
            ->with('success', 'Connection removed.');
    }

    public function test(Request $request, LmsConnection $integration)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'verify' => false, // required on Replit
            ]);

            // ✅ Correct way — use model API
            $token = trim($integration->getApiToken());

            $response = $client->post(
                rtrim($integration->base_url, '/') . '/webservice/rest/server.php',
                [
                    'form_params' => [
                        'wstoken' => $token,
                        'wsfunction' => 'core_webservice_get_site_info',
                        'moodlewsrestformat' => 'json',
                    ],
                ]
            );

            return response()->json([
                'success' => true,
                'source' => 'laravel_app',
                'moodle_response' => json_decode($response->getBody()->getContents(), true),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'source' => 'laravel_app',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function parseCourseIds(string $raw): array
    {
        if (trim($raw) === '') return [];

        $parsed = array_values(array_filter(
            array_map('trim', preg_split('/[\s,;]+/', $raw)),
            fn($v) => $v !== ''
        ));

        // Validate: course IDs must be numeric (Moodle uses integer IDs)
        foreach ($parsed as $id) {
            if (! ctype_digit($id)) {
                throw new \InvalidArgumentException("Course ID \"{$id}\" is not a valid numeric Moodle course ID.");
            }
        }

        return $parsed;
    }
}
