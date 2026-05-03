<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account — TiX</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center bg-[#f5f5f0]">
<div class="w-full max-w-sm px-4">

    <div class="text-center mb-8">
        <img src="/images/tix-logo.png" alt="TiX" class="h-36 mx-auto object-contain">
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Create your account</h2>
        <p class="text-xs text-gray-500 mb-6">3 free AI marks included &mdash; no card required.</p>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">Your name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent @error('name') border-red-400 @enderror"
                    placeholder="Jane Doe">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent @error('email') border-red-400 @enderror"
                    placeholder="you@yourorg.co.za">
                @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    placeholder="Minimum 8 characters">
                @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
            </div>

            <button type="submit"
                class="w-full hover:bg-[#e3b64d] hover:text-[#1e3a5f] bg-[#e3b64d] text-white font-medium py-2.5 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                Create account
            </button>
        </form>

        <p class="text-center text-xs text-gray-500 mt-6">
            Already have an account?
            <a href="{{ route('login') }}" class="text-orange-600 hover:underline font-medium">Sign in</a>
        </p>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
        TiX &mdash; Intelligent marking you can trust
    </p>
</div>
</body>
</html>
