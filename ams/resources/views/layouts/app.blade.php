<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AjanaNova AMS')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50:  '#fff7ed',
                            100: '#ffedd5',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        },
                        navy: {
                            700: '#1e3a5f',
                            800: '#162d4a',
                            900: '#0f1f33',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-link { @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-navy-700 hover:text-white transition-colors; }
        .sidebar-link.active { @apply bg-brand-600 text-white; }
    </style>
</head>
<body class="h-full flex">

{{-- Sidebar --}}
<aside class="w-64 bg-navy-900 flex flex-col fixed inset-y-0 left-0 z-40">
    {{-- Logo --}}
    <div class="flex items-center gap-2 px-4 py-5 border-b border-navy-700">
        <div class="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-sm">AJ</span>
        </div>
        <div>
            <p class="text-white font-bold text-sm leading-none">AjanaNova</p>
            <p class="text-slate-400 text-xs">Assessor Portal</p>
        </div>
    </div>

    {{-- Active Context Badge --}}
    @php $ctx = auth()->check() ? \App\Models\ActiveContext::with(['qualification','cohort'])->where('user_id', auth()->id())->first() : null; @endphp
    <div class="mx-3 mt-4 mb-2 p-3 rounded-lg {{ $ctx?->cohort_id ? 'bg-brand-700' : 'bg-navy-700 border border-dashed border-navy-500' }}">
        @if($ctx?->qualification_id)
            <p class="text-xs text-slate-300 font-medium uppercase tracking-wide">Currently Assessing</p>
            <p class="text-white text-sm font-semibold mt-0.5 leading-tight">{{ Str::limit($ctx->qualification->name ?? '—', 28) }}</p>
            @if($ctx?->cohort_id)
                <p class="text-brand-200 text-xs mt-0.5">{{ $ctx->cohort->name ?? '' }}</p>
            @else
                <p class="text-slate-400 text-xs mt-0.5 italic">No cohort selected</p>
            @endif
        @else
            <p class="text-slate-400 text-xs font-medium">No context set</p>
            <a href="{{ route('dashboard') }}" class="text-brand-400 text-xs hover:underline">Set your context →</a>
        @endif
    </div>

    {{-- Nav --}}
    <nav class="flex-1 px-3 py-3 space-y-1 overflow-y-auto">
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        <div class="pt-3 pb-1">
            <p class="text-xs text-slate-500 uppercase tracking-wider px-3 font-semibold">Setup</p>
        </div>

        <a href="{{ route('qualifications.index') }}"
           class="sidebar-link {{ request()->routeIs('qualifications.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Qualifications
        </a>

        <div class="pt-3 pb-1">
            <p class="text-xs text-slate-500 uppercase tracking-wider px-3 font-semibold">Assessor</p>
        </div>

        @if($ctx?->qualification_id)
        <a href="{{ route('qualifications.assignments.index', $ctx->qualification_id) }}"
           class="sidebar-link {{ request()->routeIs('qualifications.assignments.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Assignments
        </a>
        @else
        <a href="{{ route('qualifications.index') }}" class="sidebar-link text-slate-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Assignments
            <span class="ml-auto text-xs text-slate-500 italic">pick qual →</span>
        </a>
        @endif

        <a href="#" class="sidebar-link text-slate-500 cursor-not-allowed">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Gradebook
            <span class="ml-auto text-xs bg-navy-700 text-slate-400 px-1.5 py-0.5 rounded">Soon</span>
        </a>
    </nav>

    {{-- User footer --}}
    <div class="border-t border-navy-700 p-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-brand-600 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xs font-bold">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name ?? 'Assessor' }}</p>
                <p class="text-slate-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full text-left text-xs text-slate-400 hover:text-white transition-colors">
                Sign out →
            </button>
        </form>
    </div>
</aside>

{{-- Main content --}}
<div class="ml-64 flex-1 flex flex-col min-h-screen">
    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">@yield('heading', 'Dashboard')</h1>
            @hasSection('breadcrumb')
                <p class="text-sm text-gray-500 mt-0.5">@yield('breadcrumb')</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if(config('app.mock_mode', true))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 border border-amber-200 text-amber-700 text-xs font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    MOCK MODE
                </span>
            @endif
            @yield('page-actions')
        </div>
    </header>

    {{-- Flash messages --}}
    <div class="px-6 pt-4">
        @if(session('success'))
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm mb-4">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-4">
                <p class="font-medium mb-1">Please fix the following:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Page content --}}
    <main class="flex-1 px-6 pb-8">
        @yield('content')
    </main>
</div>

</body>
</html>
