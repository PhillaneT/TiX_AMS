@extends('layouts.app')

@section('title', 'Admin — Accounts')
@section('heading', 'Admin · Billing accounts')

@section('content')
<div class="max-w-6xl mx-auto pt-4 space-y-4">

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">All billing accounts</h3>
                <p class="text-xs text-gray-500">{{ $accounts->count() }} account(s)</p>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-2 text-left">Account</th>
                    <th class="px-5 py-2 text-left">Owner</th>
                    <th class="px-5 py-2 text-left">Plan</th>
                    <th class="px-5 py-2 text-left">Status</th>
                    <th class="px-5 py-2 text-right">Balance</th>
                    <th class="px-5 py-2 text-right">Grant credits</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($accounts as $a)
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-900">{{ $a->name }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($a->type) }} · #{{ $a->id }}</p>
                    </td>
                    <td class="px-5 py-3 text-gray-700">
                        @if($a->owner)
                            {{ $a->owner->name }}<br>
                            <span class="text-xs text-gray-500">{{ $a->owner->email }}</span>
                        @else
                            <span class="text-gray-400 italic">no owner</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-gray-700">{{ $a->plan_code ?? '—' }}</td>
                    <td class="px-5 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $a->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ ucfirst($a->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-semibold {{ $a->balance <= 3 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($a->balance) }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('admin.accounts.grant', $a) }}"
                              class="inline-flex items-center gap-2 justify-end">
                            @csrf
                            <input type="number" name="credits" min="1" max="1000" value="10"
                                class="w-20 rounded border border-gray-300 px-2 py-1 text-sm">
                            <input type="text" name="note" placeholder="note (optional)"
                                class="w-32 rounded border border-gray-300 px-2 py-1 text-xs">
                            <button type="submit"
                                class="bg-[#e3b64d] text-white text-xs font-medium px-3 py-1.5 rounded hover:opacity-90">
                                Grant
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No accounts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
