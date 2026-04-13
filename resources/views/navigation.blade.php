<!-- NAVBAR -->
<nav class="fixed top-0 left-0 w-full z-50 border-b backdrop-blur"
     style="background:var(--nav-bg); border-color:var(--paper-3);">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex justify-between h-16 items-center">

            <!-- LOGO -->
            <a href="{{ url('/') }}"
               class="text-lg font-bold tracking-tight"
               style="color:var(--ink);">
                Risk<span style="color:var(--gold);">Signal</span>
            </a>

            <!-- DESKTOP MENU -->
            <div class="hidden md:flex gap-6 items-center">

                <a href="#service" class="nav-link">Services</a>
                <a href="#how" class="nav-link">How it works</a>
                <a href="#pricing" class="nav-link">Pricing</a>
                <a href="#sample-report" class="nav-link">Sample report</a>

                @auth
                    <span class="text-sm opacity-80 truncate max-w-[120px]"
                          style="color:var(--ink);"
                          title="{{ Auth::user()->name }}">
                        {{ Auth::user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm text-red-500 hover:opacity-80">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="nav-link">Login</a>
                @endauth

            </div>

            <!-- MOBILE BUTTON -->
            <button id="menu-toggle"
                    class="md:hidden p-2 rounded focus:outline-none"
                    style="color:var(--ink);"
                    aria-label="Toggle menu"
                    aria-expanded="false"
                    aria-controls="mobile-menu">

                <!-- OPEN -->
                <svg id="icon-open"
                     class="h-6 w-6 block"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>

                <!-- CLOSE -->
                <svg id="icon-close"
                     class="h-6 w-6 hidden"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>

            </button>

        </div>
    </div>

    <!-- MOBILE MENU -->
    <div id="mobile-menu"
        class="hidden md:hidden absolute top-16 left-0 w-full z-40 border-t shadow-sm"
        style="background:var(--nav-bg); border-color:var(--paper-3);">

        <div class="px-4 py-6 flex flex-col gap-5 max-w-md mx-auto">

            <a href="#service" class="nav-link text-base">Services</a>
            <a href="#how" class="nav-link text-base">How it works</a>
            <a href="#pricing" class="nav-link text-base">Pricing</a>
            <a href="#sample-report" class="nav-link text-base">Sample report</a>

            <div class="border-t pt-4" style="border-color:var(--paper-3);"></div>

            @auth
                <div class="text-sm opacity-80 truncate"
                     style="color:var(--ink);">
                    {{ Auth::user()->name }}
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm text-red-500 hover:opacity-80">
                        Logout
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}"
                class="btn-primary text-center"
                style="display:inline-block;">
                    Login
                </a>
            @endauth

        </div>
    </div>

</nav>