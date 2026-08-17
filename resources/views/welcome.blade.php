<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

@use('App\Support\AppVersion')

@php
    $configuredName = config('app.name');
    $instanceName = filled($configuredName) && $configuredName !== 'Laravel' ? $configuredName : 'Wiwit';

    $version = AppVersion::fromConfig();
    $versionLabel = $version->displayString();
    $versionUrl = $version->url();

    // Scribe only registers its route once the docs have been generated.
    $docsUrl = Route::has('scribe') ? route('scribe') : null;
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $instanceName }} - Finance Tracker</title>

    @include('partials.shared-head')

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] font-sans antialiased flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
    <header class="w-full lg:max-w-4xl max-w-83.75 text-sm mb-6">
        <nav class="flex items-center justify-between gap-4">
            <span class="flex items-center gap-2 font-medium">
                <x-wiwit-logo class="w-6 h-6" />
                <span>{{ $instanceName }}</span>
            </span>

            <span class="flex items-center gap-4">
                @auth
                    <a href="{{ route('filament.admin.pages.dashboard') }}"
                        class="inline-block px-5 py-1.5 border-[#19140035] hover:border-[#1915014a] border dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('filament.admin.auth.login') }}"
                        class="inline-block px-5 py-1.5 border-[#19140035] hover:border-[#1915014a] border dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                        Log in
                    </a>
                @endauth
            </span>
        </nav>
    </header>

    <div
        class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <main class="flex max-w-83.75 w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
            <div
                class="text-[13px] leading-5 flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-bl-lg rounded-br-lg lg:rounded-tl-lg lg:rounded-br-none">
                <h1 class="mb-1 text-lg font-medium">Welcome to {{ $instanceName }}</h1>
                <p class="mb-4 text-[#706f6c] dark:text-[#A1A09A]">
                    Your self-hosted <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Wiwit</span>
                    instance; a finance tracker built for speed and convenience. Sign in to pick up where you left off.
                </p>

                <ul class="flex flex-col mb-4 lg:mb-6">
                    <li
                        class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-1/2 before:bottom-0 before:left-[0.4rem] before:absolute">
                        <x-wiwit-bullet />
                        <span>Log income and spending, sorted by your own categories</span>
                    </li>
                    <li
                        class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-0 before:bottom-0 before:left-[0.4rem] before:absolute">
                        <x-wiwit-bullet />
                        <span>Keep track of debts and who still owes what</span>
                    </li>
                    <li
                        class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-0 before:bottom-0 before:left-[0.4rem] before:absolute">
                        <x-wiwit-bullet />
                        <span>See income vs spending at a glance on the dashboard</span>
                    </li>
                    <li
                        class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-0 before:bottom-1/2 before:left-[0.4rem] before:absolute">
                        <x-wiwit-bullet />
                        <span>
                            Automate it over the
                            @if (filled($docsUrl))
                                <a href="{{ $docsUrl }}"
                                    class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#5A9E00] dark:text-[#7CCF00] ml-1">
                                    <span>REST API</span>
                                    <svg width="10" height="11" viewBox="0 0 10 11" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5">
                                        <path d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001"
                                            stroke="currentColor" stroke-linecap="square" />
                                    </svg>
                                </a>
                            @else
                                <span class="font-medium">REST API</span>
                            @endif
                            with scoped access tokens
                        </span>
                    </li>
                </ul>

                <ul class="flex flex-wrap gap-3 text-sm leading-normal">
                    <li>
                        @auth
                            <a href="{{ route('filament.admin.pages.dashboard') }}"
                                class="inline-block dark:bg-[#eeeeec] dark:border-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white dark:hover:border-white hover:bg-black hover:border-black px-5 py-1.5 bg-[#1b1b18] rounded-sm border border-black text-white text-sm leading-normal">
                                Open dashboard
                            </a>
                        @else
                            <a href="{{ route('filament.admin.auth.login') }}"
                                class="inline-block dark:bg-[#eeeeec] dark:border-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white dark:hover:border-white hover:bg-black hover:border-black px-5 py-1.5 bg-[#1b1b18] rounded-sm border border-black text-white text-sm leading-normal">
                                Log in
                            </a>
                        @endauth
                    </li>
                    @if (filled($docsUrl))
                        <li>
                            <a href="{{ $docsUrl }}"
                                class="inline-block px-5 py-1.5 border border-[#19140035] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                API docs
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            <div
                class="bg-[#F4FBE7] dark:bg-[#111A00] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg aspect-335/376 lg:aspect-auto w-full lg:w-109.5 shrink-0 overflow-hidden flex items-center justify-center shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                <div
                    class="absolute w-64 h-64 rounded-full bg-[#7CCF00]/25 dark:bg-[#7CCF00]/15 blur-3xl pointer-events-none">
                </div>
                <x-wiwit-logo
                    class="relative w-56 lg:w-72 -rotate-12 transition-all duration-750 delay-300 translate-y-0 opacity-100 starting:opacity-0 starting:translate-y-6" />
            </div>
        </main>
    </div>

    <footer
        class="w-full lg:max-w-4xl max-w-83.75 mt-6 text-[12px] leading-5 text-[#706f6c] dark:text-[#A1A09A] flex flex-wrap items-center justify-between gap-2">
        <span class="flex flex-wrap items-center gap-4">
            <a href="https://github.com/wiwit-project/wiwit" target="_blank" rel="noopener"
                class="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">GitHub</a>
            <a href="https://docs.wiwit.iqfareez.com" target="_blank" rel="noopener"
                class="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">Docs</a>
            <a href="https://x.com/iqfareez" target="_blank" rel="noopener"
                class="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">X</a>
        </span>

        @if (filled($versionLabel))
            <span>
                @if (filled($versionUrl))
                    <a href="{{ $versionUrl }}" target="_blank" rel="noopener"
                        class="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">{{ $versionLabel }}</a>
                @else
                    {{ $versionLabel }}
                @endif
            </span>
        @endif
    </footer>
</body>

</html>
