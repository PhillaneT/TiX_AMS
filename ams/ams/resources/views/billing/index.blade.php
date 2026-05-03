@extends('layouts.app')

@section('title', 'Billing — TiX')
@section('heading', 'Billing & Credits')

@section('content')
<div class="max-w-5xl mx-auto pt-4 space-y-6">

    {{-- Top cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Balance --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Credit balance</p>
            <p class="mt-2 text-3xl font-bold {{ $account->balance <= 3 ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($account->balance) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">AI marks available</p>
        </div>

        {{-- Plan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Current plan</p>
            @if($plan)
                <p class="mt-2 text-lg font-semibold text-gray-900">{{ $plan->name }}</p>
                <p class="text-sm text-gray-500">R{{ number_format($plan->price_cents / 100, 2) }}/month
                    &mdash; {{ $plan->monthly_credits }} marks included</p>
            @else
                <p class="mt-2 text-lg font-semibold text-gray-900">Trial</p>
                <p class="text-sm text-gray-500">No subscription yet.</p>
            @endif
            <span class="mt-3 inline-block text-xs px-2 py-0.5 rounded-full
                {{ $account->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                {{ ucfirst($account->status) }}
            </span>
        </div>

        {{-- Quick actions --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-2">
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Quick actions</p>
            <a href="{{ route('billing.topup') }}"
               class="text-center bg-[#e3b64d] text-white text-sm font-medium py-2 rounded-lg hover:opacity-90 transition">
                Top up credits
            </a>
            <button disabled title="Coming in Phase B"
                class="text-center bg-gray-100 text-gray-400 text-sm font-medium py-2 rounded-lg cursor-not-allowed">
                Change plan (soon)
            </button>
        </div>
    </div>

    {{-- Account info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Account</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-gray-500 text-xs">Account name</dt><dd class="text-gray-900 font-medium">{{ $account->name }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Type</dt><dd class="text-gray-900 font-medium capitalize">{{ $account->type }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Billing email</dt><dd class="text-gray-900 font-medium">{{ $account->billing_email ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Ledger --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Recent credit activity</h3>
            <p class="text-xs text-gray-500">Last 20 entries</p>
        </div>
        @if($ledger->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">No credit activity yet.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-2 text-left">When</th>
                    <th class="px-5 py-2 text-left">Reason</th>
                    <th class="px-5 py-2 text-right">Change</th>
                    <th class="px-5 py-2 text-right">Balance after</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach($ledger as $row)
                <tr>
                    <td class="px-5 py-2 text-gray-600">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-5 py-2 text-gray-700">
                        @switch($row->reason)
                            @case('trial')              Trial credit grant @break
                            @case('subscription_grant') Monthly subscription credits @break
                            @case('topup')              Top-up purchase @break
                            @case('ai_mark')            AI marking @break
                            @case('admin_adjust')       Admin adjustment @break
                            @case('refund')             Refund @break
                            @default                    {{ $row->reason }}
                        @endswitch
                    </td>
                    <td class="px-5 py-2 text-right font-medium {{ $row->delta >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $row->delta >= 0 ? '+' : '' }}{{ $row->delta }}
                    </td>
                    <td class="px-5 py-2 text-right text-gray-900 font-medium">{{ $row->balance_after }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
