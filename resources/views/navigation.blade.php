{{-- =========================================================
   RISK SIGNAL — GLOBAL NAVIGATION
========================================================= --}}

<nav
    class="fixed top-0 left-0 w-full z-[1000]"
    style="
        background:rgba(11,19,43,.92);
        backdrop-filter:blur(20px);
        border-bottom:1px solid rgba(255,255,255,.06);
        box-sizing:border-box;
    "
    role="navigation"
    aria-label="Primary Navigation">

    {{-- CONTAINER --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- NAV INNER --}}
        <div
            class="flex items-center justify-between h-20 w-full">

            {{-- =====================================================
               LOGO
            ====================================================== --}}
            <a
                href="{{ url('/') }}"

                class="shrink-0"

                style="
                    font-size:2rem;
                    font-weight:800;
                    letter-spacing:-1px;
                    color:#f8fafc;
                    text-decoration:none;
                    line-height:1;
                    white-space:nowrap;
                ">
                Risk<span style="color:#fbbf24;">Signal</span>
            </a>

            {{-- =====================================================
               GUEST NAVIGATION
            ====================================================== --}}
            @guest

            <div
                class="hidden md:flex items-center"
                style="
                    gap:2rem;
                    margin-left:auto;
                    margin-right:2rem;
                ">

                <a
                    href="{{ url('/#services') }}"
                    class="nav-link">
                    Services
                </a>

                <a
                    href="{{ url('/#how-it-works') }}"
                    class="nav-link">
                    How it works
                </a>

                <a
                    href="{{ url('/#pricing') }}"
                    class="nav-link">
                    Pricing
                </a>

                <a
                    href="{{ url('/#sample-report') }}"
                    class="nav-link">
                    Sample report
                </a>

                <a
                    href="{{ route('login') }}"
                    class="nav-link">
                    Login
                </a>

                {{-- CTA --}}
                <a
                    href="{{ url('/#free-trial') }}"

                    style="
                        padding:.9rem 1.6rem;
                        border-radius:14px;
                        border:1px solid rgba(255,255,255,.12);

                        background:rgba(255,255,255,.03);

                        color:#f8fafc;

                        font-weight:700;

                        text-decoration:none;

                        transition:.2s ease;

                        white-space:nowrap;
                    "

                    onmouseover="
                        this.style.background='rgba(255,255,255,.08)';
                        this.style.borderColor='rgba(255,255,255,.22)';
                    "

                    onmouseout="
                        this.style.background='rgba(255,255,255,.03)';
                        this.style.borderColor='rgba(255,255,255,.12)';
                    ">
                    Start free trial
                </a>

            </div>

            @endguest

            {{-- =====================================================
               AUTH NAVIGATION
            ====================================================== --}}
            @auth

            <div
                class="hidden md:flex items-center"
                style="
                    gap:2rem;
                    margin-left:auto;
                ">

                <a
                    href="{{ route('dashboard') }}"
                    class="nav-link">
                    Dashboard
                </a>

                <a
                    href="{{ route('portfolio.manage') }}"
                    class="nav-link">
                    Portfolios
                </a>

                <a
                    href="{{ route('portfolio.upload') }}"
                    class="nav-link">
                    Upload
                </a>

                <a
                    href="{{ route('subscription.index') }}"
                    class="nav-link">
                    Subscription
                </a>

                <a
                    href="{{ route('profile.edit') }}"
                    class="nav-link">
                    Profile
                </a>

                @if(Auth::user()->is_admin)
                <a
                    href="{{ route('admin.dashboard') }}"
                    style="
                        padding:.55rem 1.1rem;
                        border-radius:10px;
                        border:1px solid rgba(250,204,21,.3);
                        background:rgba(250,204,21,.08);
                        color:#facc15;
                        font-weight:800;
                        font-size:.85rem;
                        text-decoration:none;
                        transition:.2s ease;
                        white-space:nowrap;
                    "
                    onmouseover="this.style.background='rgba(250,204,21,.16)'"
                    onmouseout="this.style.background='rgba(250,204,21,.08)'">
                    ⚙ Admin
                </a>
                @endif

                <form
                    method="POST"
                    action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"

                        style="
                            background:none;
                            border:none;
                            color:#fca5a5;
                            font-weight:700;
                            cursor:pointer;
                            font-size:1rem;
                            transition:.2s ease;
                        "

                        onmouseover="
                            this.style.color='#fecaca';
                        "

                        onmouseout="
                            this.style.color='#fca5a5';
                        ">
                        Logout
                    </button>

                </form>

            </div>

            @endauth

            {{-- =====================================================
               MOBILE MENU BUTTON
            ====================================================== --}}
            <button
                id="menu-toggle"
                type="button"

                class="md:hidden inline-flex items-center justify-center p-2 rounded-md"

                style="color:white;"

                aria-label="Toggle navigation menu">

                <svg
                    class="h-7 w-7"

                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16" />
                </svg>

            </button>

        </div>

    </div>

    {{-- =========================================================
       MOBILE MENU
    ========================================================== --}}
    <div
        id="mobile-menu"

        class="hidden md:hidden border-t"

        style="
            background:rgba(2,6,23,.98);
            border-color:rgba(255,255,255,.06);
        ">

        <div
            class="px-6 py-6 flex flex-col gap-6">

            @guest

            <a
                href="{{ url('/#services') }}"
                class="nav-link mobile-link">
                Services
            </a>

            <a
                href="{{ url('/#how-it-works') }}"
                class="nav-link mobile-link">
                How it works
            </a>

            <a
                href="{{ url('/#pricing') }}"
                class="nav-link mobile-link">
                Pricing
            </a>

            <a
                href="{{ url('/#sample-report') }}"
                class="nav-link mobile-link">
                Sample report
            </a>

            <a
                href="{{ route('login') }}"
                class="nav-link mobile-link">
                Login
            </a>

            <a
                href="{{ url('/#free-trial') }}"

                style="
                    padding:1rem 1.4rem;
                    border-radius:14px;
                    border:1px solid rgba(255,255,255,.12);

                    background:rgba(255,255,255,.03);

                    color:#f8fafc;

                    font-weight:700;

                    text-decoration:none;

                    text-align:center;
                ">
                Start free trial
            </a>

            @endguest

            @auth

            <a
                href="{{ route('dashboard') }}"
                class="nav-link mobile-link">
                Dashboard
            </a>

            <a
                href="{{ route('portfolio.manage') }}"
                class="nav-link mobile-link">
                Portfolios
            </a>

            <a
                href="{{ route('portfolio.upload') }}"
                class="nav-link mobile-link">
                Upload
            </a>

            <a
                href="{{ route('subscription.index') }}"
                class="nav-link mobile-link">
                Subscription
            </a>

            <a
                href="{{ route('profile.edit') }}"
                class="nav-link mobile-link">
                Profile
            </a>

            @if(Auth::user()->is_admin)
            <a
                href="{{ route('admin.dashboard') }}"
                style="
                    padding:.75rem 1.2rem;
                    border-radius:12px;
                    border:1px solid rgba(250,204,21,.3);
                    background:rgba(250,204,21,.08);
                    color:#facc15;
                    font-weight:800;
                    font-size:.95rem;
                    text-decoration:none;
                    text-align:center;
                ">
                ⚙ Admin Panel
            </a>
            @endif

            <form
                method="POST"
                action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"

                    style="
                        background:none;
                        border:none;
                        color:#fca5a5;
                        font-weight:700;
                        cursor:pointer;
                        font-size:1rem;
                    ">
                    Logout
                </button>

            </form>

            @endauth

        </div>

    </div>

</nav>

{{-- =========================================================
   NAVIGATION STYLES
========================================================= --}}
<style>
    html {
        scroll-behavior: smooth;
    }

    .nav-link {

        color: #9ca3af;

        text-decoration: none;

        font-weight: 600;

        transition: .2s ease;

        position: relative;

        padding-bottom: .2rem;

        white-space: nowrap;
    }

    .nav-link:hover {

        color: #f8fafc;
    }

    .nav-link::after {

        content: '';

        position: absolute;

        left: 0;

        bottom: -6px;

        width: 0;

        height: 2px;

        background: #fbbf24;

        transition: .25s ease;
    }

    .nav-link:hover::after {

        width: 100%;
    }

    @media (max-width:1200px) {

        .nav-link {

            font-size: .95rem;
        }
    }
</style>

{{-- =========================================================
   MOBILE MENU SCRIPT
========================================================= --}}
<script>
    document.addEventListener("DOMContentLoaded", () => {

        const toggle =
            document.getElementById("menu-toggle");

        const mobileMenu =
            document.getElementById("mobile-menu");

        const mobileLinks =
            document.querySelectorAll(".mobile-link");

        if (!toggle || !mobileMenu) {
            return;
        }

        toggle.addEventListener("click", () => {

            mobileMenu.classList.toggle("hidden");
        });

        mobileLinks.forEach(link => {

            link.addEventListener("click", () => {

                mobileMenu.classList.add("hidden");
            });
        });

    });
</script>
