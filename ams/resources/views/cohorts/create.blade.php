@extends('layouts.app')

@section('title', 'New Cohort — AjanaNova AMS')
@section('heading', 'New Cohort')
@section('breadcrumb', $qualification->name . ' → New Cohort')

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <p class="text-sm text-gray-500 mb-5">A cohort is a group of learners enrolled together — a class, intake, or batch for <strong>{{ $qualification->name }}</strong>.</p>

        <form method="POST" action="{{ route('qualifications.cohorts.store', $qualification) }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Cohort name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Group A — Johannesburg 2025">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Year</label>
                    <input type="number" name="year" value="{{ old('year', date('Y')) }}" min="2020" max="2040"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Venue / Campus</label>
                    <input type="text" name="venue" value="{{ old('venue') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="e.g. Sandton Office">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">End date</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Facilitator name</label>
                    <input type="text" name="facilitator" value="{{ old('facilitator') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Lead facilitator for this cohort">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                        placeholder="Any notes about this cohort...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Create Cohort
                </button>
                <a href="{{ route('qualifications.show', $qualification) }}" class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
