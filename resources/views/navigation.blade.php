<nav x-data="{ open: false }"
     style="background:var(--nav-bg); border-bottom:1px solid var(--paper-3);">

    <!-- CONTAINER -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex justify-between h-16 items-center">

            <!-- LEFT: LOGO -->
            <div class="flex items-center gap-8">

                <a href="/" class="text-lg font-bold"
                   style="color:var(--ink);">
                    Risk<span style="color:var(--gold);">Signal</span>
                </a>

                <!-- DESKTOP MENU -->
                <div class="hidden md:flex gap-6">

                    <a href="#" class="text-sm whitespace-nowrap"
                       style="color:var(--ink);opacity:0.8;">Services</a>

                    <a href="#" class="text-sm whitespace-nowrap"
                       style="color:var(--ink);opacity:0.8;">How it works</a>

                    <a href="#" class="text-sm whitespace-nowrap"
                       style="color:var(--ink);opacity:0.8;">Pricing</a>

                    <a href="#" class="text-sm whitespace-nowrap"
                       style="color:var(--ink);opacity:0.8;">Sample Log report</a>

                </div>
            </div>

            <!-- RIGHT: AUTH + THEME -->
            <div class="hidden md:flex items-center gap-4">

                <!-- THEME BUTTON -->
                <button onclick="toggleTheme()"
                        style="background:none;border:none;cursor:pointer;font-size:18px;">
                    <span class="theme-icon">🌙</span>
                </button>

                @auth
                    <div class="text-sm"
                         style="color:var(--ink);opacity:0.8;">
                        {{ Auth::user()->name }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-sm" style="color:red;">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="text-sm"
                       style="color:var(--ink);">
                        Login
                    </a>
                @endauth

            </div>

            <!-- MOBILE BUTTON -->
            <div class="md:hidden">
                <button @click="open = !open" class="p-2">
                    <svg class="h-6 w-6" style="color:var(--ink);"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'block': !open }"
                              class="block"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'block': open, 'hidden': !open }"
                              class="hidden"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- MOBILE MENU -->
    <div x-show="open"
         class="md:hidden px-4 pb-4 space-y-3"
         style="background:var(--nav-bg);">

        <!-- NAV LINKS -->
        <a href="#" class="block text-sm"
           style="color:var(--ink);">Services</a>

        <a href="#" class="block text-sm"
           style="color:var(--ink);">How it works</a>

        <a href="#" class="block text-sm"
           style="color:var(--ink);">Pricing</a>

        <a href="#" class="block text-sm"
           style="color:var(--ink);">Sample Log report</a>

        <hr style="border-color:var(--paper-3);">

        <!-- AUTH + THEME -->
        <div class="flex items-center justify-between">

            <div>
                @auth
                    <div class="text-sm"
                         style="color:var(--ink);opacity:0.8;">
                        {{ Auth::user()->name }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-sm" style="color:red;">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="text-sm"
                       style="color:var(--ink);">
                        Login
                    </a>
                @endauth
            </div>

            <!-- THEME BUTTON -->
            <button onclick="toggleTheme()"
                    style="background:none;border:none;cursor:pointer;font-size:18px;">
                <span class="theme-icon">🌙</span>
            </button>

        </div>

    </div>

</nav>