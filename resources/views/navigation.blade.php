{{-- =========================================================
   NAVIGATION
   RiskSignal — Production Grade Navbar
========================================================= --}}

@php
    $navLinks = [
        [
            'label' => 'Services',
            'href'  => url('/#services'),
        ],
        [
            'label' => 'How it works',
            'href'  => url('/#how-it-works'),
        ],
        [
            'label' => 'Pricing',
            'href'  => url('/#pricing'),
        ],
        [
            'label' => 'Sample report',
            'href'  => url('/#sample-report'),
        ],
    ];
@endphp

<nav
    class="nav-default fixed top-0 left-0 w-full z-[1000] border-b backdrop-blur"
    style="
        background:var(--nav-bg);
        border-color:var(--paper-3);
        box-sizing:border-box;
    "
    role="navigation"
    aria-label="Primary Navigation"
>

    {{-- CONTAINER --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- INNER --}}
        <div class="flex items-center justify-between h-16 w-full">

            {{-- LOGO --}}
            <a
                href="{{ url('/') }}"
                class="shrink-0 text-lg font-bold tracking-tight"
                style="color:var(--ink);"
                aria-label="RiskSignal Home"
            >
                Risk<span style="color:var(--gold);">Signal</span>
            </a>

            {{-- DESKTOP NAV --}}
            <div class="hidden md:flex items-center gap-6">

                {{-- NAV LINKS --}}
                <div class="flex items-center gap-5 whitespace-nowrap">

                    @foreach ($navLinks as $link)

                        <a
                            href="{{ $link['href'] }}"
                            class="nav-link"
                        >
                            {{ $link['label'] }}
                        </a>

                    @endforeach

                </div>

                {{-- CTA --}}
                <a
                    href="{{ url('/#contact') }}"
                    class="btn-outline shrink-0 inline-flex items-center justify-center"
                    style="
                        height:38px;
                        padding-inline:1rem;
                        white-space:nowrap;
                    "
                >
                    Start free trial
                </a>

            </div>

            {{-- MOBILE TOGGLE --}}
            <button
                id="menu-toggle"
                type="button"
                class="md:hidden inline-flex items-center justify-center p-2 rounded-md transition"
                style="color:var(--ink);"
                aria-label="Toggle navigation menu"
                aria-controls="mobile-menu"
                aria-expanded="false"
            >

                {{-- OPEN ICON --}}
                <svg
                    id="icon-open"
                    class="h-6 w-6 block"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>

                {{-- CLOSE ICON --}}
                <svg
                    id="icon-close"
                    class="h-6 w-6 hidden"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>

            </button>

        </div>
    </div>

    {{-- =====================================================
         MOBILE MENU
    ====================================================== --}}

    <div
        id="mobile-menu"
        class="hidden md:hidden absolute top-16 left-0 w-full z-40 border-t shadow-sm"
        style="
            background:var(--nav-bg);
            border-color:var(--paper-3);
        "
    >

        <div class="max-w-md mx-auto px-4 py-6 flex flex-col gap-5">

            {{-- MOBILE LINKS --}}
            @foreach ($navLinks as $link)

                <a
                    href="{{ $link['href'] }}"
                    class="nav-link text-base"
                >
                    {{ $link['label'] }}
                </a>

            @endforeach

            {{-- DIVIDER --}}
            <div
                class="border-t pt-4"
                style="border-color:var(--paper-3);"
            ></div>

            {{-- MOBILE CTA --}}
            <a
                href="{{ url('/#contact') }}"
                class="btn-outline flex items-center justify-center"
                style="height:42px;"
            >
                Start free trial
            </a>

        </div>
    </div>

</nav>
