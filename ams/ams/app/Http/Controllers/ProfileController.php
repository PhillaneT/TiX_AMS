<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Per-assessor profile: ETQA registration number, reusable signature image,
 * and reusable official stamp image.  These are pulled into every Assessor
 * Declaration / Marking Report PDF so the assessor never has to re-sign or
 * re-stamp manually.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'etqa_registration'    => ['nullable', 'string', 'max:100'],
            'signature_image'      => ['nullable', 'string'],            // data URL from canvas
            'signature_file'       => ['nullable', 'image', 'max:2048'], // OR PNG/JPG upload
            'stamp_file'           => ['nullable', 'image', 'max:4096'],
            'remove_signature'     => ['nullable', 'boolean'],
            'remove_stamp'         => ['nullable', 'boolean'],
            'stamp_org_top'        => ['nullable', 'string', 'max:60'],
            'stamp_org_bottom'     => ['nullable', 'string', 'max:60'],
            'stamp_role'           => ['nullable', 'string', 'max:40'],
            'stamp_holder_name'    => ['nullable', 'string', 'max:60'],
            'stamp_use_generated'  => ['nullable', 'boolean'],
        ]);

        $user->etqa_registration   = $data['etqa_registration'] ?? $user->etqa_registration;
        $user->stamp_org_top       = $data['stamp_org_top']     ?? $user->stamp_org_top;
        $user->stamp_org_bottom    = $data['stamp_org_bottom']  ?? $user->stamp_org_bottom;
        $user->stamp_role          = $data['stamp_role']        ?? $user->stamp_role;
        $user->stamp_holder_name   = $data['stamp_holder_name'] ?? $user->stamp_holder_name;
        $user->stamp_use_generated = $request->boolean('stamp_use_generated');

        // ── Signature ──────────────────────────────────────────────────────
        if ($request->boolean('remove_signature')) {
            $this->deleteIfExists($user->signature_path);
            $user->signature_path = null;
        } elseif ($request->filled('signature_image')) {
            $png = $this->dataUrlToBinary($data['signature_image']);
            if ($png !== null) {
                $this->deleteIfExists($user->signature_path);
                $path = "private/profile/{$user->id}/signature.png";
                Storage::put($path, $png);
                $user->signature_path = $path;
            }
        } elseif ($request->hasFile('signature_file')) {
            $this->deleteIfExists($user->signature_path);
            $ext  = $request->file('signature_file')->getClientOriginalExtension() ?: 'png';
            $path = $request->file('signature_file')->storeAs(
                "private/profile/{$user->id}", "signature.{$ext}"
            );
            $user->signature_path = $path;
        }

        // ── Stamp ──────────────────────────────────────────────────────────
        if ($request->boolean('remove_stamp')) {
            $this->deleteIfExists($user->stamp_path);
            $user->stamp_path = null;
        } elseif ($request->hasFile('stamp_file')) {
            $this->deleteIfExists($user->stamp_path);
            $ext  = $request->file('stamp_file')->getClientOriginalExtension() ?: 'png';
            $path = $request->file('stamp_file')->storeAs(
                "private/profile/{$user->id}", "stamp.{$ext}"
            );
            $user->stamp_path = $path;
        }

        $user->save();

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }

    /** Stream the signed-in user's signature/stamp image (auth-gated, not public). */
    public function asset(Request $request, string $kind)
    {
        $user = $request->user();
        $path = match ($kind) {
            'signature' => $user->signature_path,
            'stamp'     => $user->stamp_path,
            default     => null,
        };
        abort_unless($path && Storage::exists($path), 404);
        return response()->file(Storage::path($path));
    }

    private function deleteIfExists(?string $path): void
    {
        if ($path && Storage::exists($path)) Storage::delete($path);
    }

    private function dataUrlToBinary(string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $dataUrl, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        return $bin === false ? null : $bin;
    }
}
