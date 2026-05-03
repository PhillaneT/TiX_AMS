@extends('layouts.app')

@section('title', 'Top up — TiX')
@section('heading', 'Top up your credits')

@section('content')
<div class="max-w-3xl mx-auto pt-4 space-y-6">

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-amber-900">You're out of AI marks</h3>
        <p class="text-sm text-amber-800 mt-1">
            Manual marking still works. To use AI marking again, top up below or
            switch to a subscription plan.
        </p>
    </div>

    {{-- Top-up bundle card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Top-up bundle</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">25 marks</p>
            <p class="text-sm text-gray-500 mt-1">One-off purchase. Credits never expire.</p>
        </div>
        <div class="text-right md:text-right">
            <p class="text-3xl font-bold text-gray-900">R99</p>
            <p class="text-xs text-gray-400">excl. VAT</p>
            <button disabled title="Payments wired up in Phase B"
                class="mt-3 bg-gray-100 text-gray-400 text-sm font-medium px-5 py-2.5 rounded-lg cursor-not-allowed">
                Buy with PayFast (coming soon)
            </button>
        </div>
    </div>

    {{-- Subscription card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Portal Only subscription</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">10 marks / month</p>
            <p class="text-sm text-gray-500 mt-1">Recurring monthly. Credits roll over.</p>
        </div>
        <div class="text-right md:text-right">
            <p class="text-3xl font-bold text-gray-900">R199</p>
            <p class="text-xs text-gray-400">per month, excl. VAT</p>
            <button disabled title="Payments wired up in Phase B"
                class="mt-3 bg-gray-100 text-gray-400 text-sm font-medium px-5 py-2.5 rounded-lg cursor-not-allowed">
                Subscribe (coming soon)
            </button>
        </div>
    </div>

    <div class="text-center pt-2">
        <a href="{{ route('billing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
            ← Back to billing
        </a>
    </div>

</div>
@endsection
