@extends('layouts.app')

@section('title', 'Edit Cohort — TiXMark IQ')
@section('heading', 'Edit Cohort')
@section('breadcrumbs')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-800 transition-colors">Dashboard</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.index') }}" class="hover:text-gray-800 transition-colors">Qualifications</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.show', $qualification) }}" class="hover:text-gray-800 transition-colors">{{ $qualification->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}" class="hover:text-gray-800 transition-colors">{{ $cohort->name }}</a>
    <span class="text-gray-300 mx-0.5">›</span>
    <span class="text-gray-700 font-medium">Edit</span>
@endsection

@section('content')
<div class="max-w-2xl mt-2">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST" action="{{ route('qualifications.cohorts.update', [$qualification, $cohort]) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Cohort name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $cohort->name) }}" required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Year</label>
                    <input type="number" name="year" value="{{ old('year', $cohort->year) }}" min="2020" max="2040"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Venue</label>
                    <input type="text" name="venue" value="{{ old('venue', $cohort->venue) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $cohort->start_date?->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">End date</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $cohort->end_date?->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Facilitator</label>
                    <input type="text" name="facilitator" value="{{ old('facilitator', $cohort->facilitator) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="active" {{ old('status', $cohort->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ old('status', $cohort->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ old('status', $cohort->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">{{ old('notes', $cohort->notes) }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 hover:bg-[#e3b64d] hover:text-[#1e3a5f] text-white text-sm font-medium rounded-lg transition-colors bg-[#1e3a5f]">
                    Save Changes
                </button>
                <a href="{{ route('qualifications.cohorts.show', [$qualification, $cohort]) }}" class="px-5 py-2.5 text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
