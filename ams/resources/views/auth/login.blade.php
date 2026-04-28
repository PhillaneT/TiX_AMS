<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — AjanaNova AMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center">
<div class="w-full max-w-sm px-4">

    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-[#1e3a5f] rounded-xl mb-4">
            <span class="text-white font-bold text-lg">AJ</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">AjanaNova AMS</h1>
        <p class="text-sm text-gray-500 mt-1">Assessor Management System</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-base font-semibold text-gray-900 mb-6">Sign in to your account</h2>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent @error('email') border-red-400 @enderror"
                    placeholder="assessor@yourorg.co.za">
                @error('email')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    placeholder="••••••••">
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="remember" name="remember" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                <label for="remember" class="ml-2 text-sm text-gray-600">Keep me signed in</label>
            </div>

            <button type="submit"
                class="w-full hover:bg-[#e3b64d] hover:text-[#1e3a5f] bg-[#e3b64d] text-white font-medium py-2.5 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                Sign in
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
        AjanaNova Grader &mdash; SAQA/QCTO Compliant Assessment Platform
    </p>
</div>
</body>
</html>
