<!-- NAVBAR -->
<nav class="nav-default fixed top-0 left-0 w-full z-[1000] border-b backdrop-blur"
     style="box-sizing:border-box;">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex justify-between items-center h-16 w-full">

            <!-- LOGO -->
            <a href="{{ url('/') }}"
               class="text-lg font-bold tracking-tight shrink-0"
               style="color:var(--ink);">
                Risk<span style="color:var(--gold);">Signal</span>
            </a>

            <!-- DESKTOP MENU -->
            <div class="hidden md:flex items-center gap-5 overflow-hidden">

                <!-- LINKS -->
                <div class="flex items-center gap-5 whitespace-nowrap">
                    <a href="#service" class="nav-link">Services</a>
                    <a href="#how-it-works" class="nav-link">How it works</a>
                    <a href="#pricing" class="nav-link">Pricing</a>
                    <a href="#sample-report" class="nav-link">Sample report</a>
                </div>

                <!-- CTA -->
                <a href="#contact"
                   class="btn-outline shrink-0 flex items-center justify-center"
                   style="
                        height:36px;
                        padding:0 0.9rem;
                        white-space:nowrap;
                   ">
                    Start free trial
                </a>

            </div>

            <!-- MOBILE BUTTON -->
            <button id="menu-toggle"
                    class="md:hidden p-2 rounded focus:outline-none shrink-0"
                    style="color:var(--ink);"
                    aria-label="Toggle menu">

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
            <a href="#how-it-works" class="nav-link text-base">How it works</a>
            <a href="#pricing" class="nav-link text-base">Pricing</a>
            <a href="#sample-report" class="nav-link text-base">Sample report</a>

            <div class="border-t pt-4" style="border-color:var(--paper-3);"></div>

            <a href="#contact" class="btn-outline flex justify-center items-center"
               style="height:40px;">
                Start free trial
            </a>

        </div>
    </div>
</nav>