@extends('layouts.app')

@section('title', 'My Profile — RiskSignal')

@section('content')

@push('styles')
<style>
    .profile-wrap{max-width:720px;margin:0 auto;padding:2rem 0;}
    .section-card{background:var(--paper-2,rgba(255,255,255,.03));border:1px solid var(--paper-3,rgba(255,255,255,.08));border-radius:16px;padding:1.75rem;margin-bottom:1.5rem;}
    .section-title{font-size:1rem;font-weight:800;margin-bottom:.35rem;}
    .section-sub{color:var(--ink-3);font-size:.875rem;margin-bottom:1.5rem;line-height:1.5;}
    .field-group{margin-bottom:1.25rem;}
    .field-label{display:block;font-size:.78rem;font-weight:700;color:var(--ink-3);letter-spacing:.05em;text-transform:uppercase;margin-bottom:.5rem;}
    .field-input{width:100%;padding:.8rem 1rem;border-radius:12px;border:1px solid var(--paper-3,rgba(255,255,255,.08));background:var(--paper,#020817);color:inherit;font-size:.9rem;font-family:inherit;outline:none;transition:.15s ease;}
    .field-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
    .field-hint{font-size:.78rem;color:var(--ink-3);margin-top:.35rem;}
    .field-error{font-size:.78rem;color:#fca5a5;margin-top:.35rem;}
    .btn-save{padding:.8rem 1.75rem;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;font-size:.9rem;border:none;cursor:pointer;transition:.2s ease;font-family:inherit;}
    .btn-save:hover{background:#1d4ed8;}
    .btn-danger-outline{padding:.8rem 1.75rem;border-radius:12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#fca5a5;font-weight:700;font-size:.9rem;cursor:pointer;transition:.2s ease;font-family:inherit;}
    .btn-danger-outline:hover{background:rgba(239,68,68,.18);}
    .saved-note{color:#86efac;font-size:.82rem;font-weight:600;margin-left:.75rem;}
    hr.divider{border:none;border-top:1px solid var(--paper-3,rgba(255,255,255,.08));margin:1.5rem 0;}

    /* MODAL */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:32px;width:100%;max-width:460px;}
    .modal-title{font-size:1.1rem;font-weight:800;margin-bottom:.5rem;}
    .modal-sub{color:#64748b;font-size:.875rem;line-height:1.6;margin-bottom:1.25rem;}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:1.25rem;}
    .btn-cancel{padding:.7rem 1.4rem;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:transparent;color:#94a3b8;font-weight:700;font-size:.875rem;cursor:pointer;font-family:inherit;}
    .btn-cancel:hover{background:rgba(255,255,255,.06);}
    .btn-delete-confirm{padding:.7rem 1.4rem;border-radius:10px;background:#ef4444;border:none;color:#fff;font-weight:700;font-size:.875rem;cursor:pointer;font-family:inherit;}
    .btn-delete-confirm:hover{background:#dc2626;}
    .btn-delete-confirm:disabled{opacity:.45;cursor:not-allowed;}
    [x-cloak]{display:none!important;}
</style>
@endpush

<div class="profile-wrap">

    {{-- HEADER --}}
    <div style="margin-bottom:2rem;">
        <div class="eyebrow" style="margin-bottom:.5rem;">Account</div>
        <h1 style="font-size:1.75rem;font-weight:800;">My Profile</h1>
    </div>

    {{-- ================================================================
       PROFILE INFORMATION
    ================================================================ --}}
    <div class="section-card">

        <div class="section-title">Profile Information</div>
        <div class="section-sub">Update your name, phone number, and email address.</div>

        @if(session('status') === 'profile-updated')
            <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.7rem 1rem;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:1.25rem;">
                ✓ Profile saved successfully.
            </div>
        @endif

        @if($errors->default->any())
            <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.7rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1.25rem;">
                @foreach($errors->default->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('patch')

            <div class="field-group">
                <label class="field-label" for="name">Full Name</label>
                <input class="field-input" id="name" type="text" name="name"
                       value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field-group">
                <label class="field-label" for="phone">Phone Number</label>
                <input class="field-input" id="phone" type="tel" name="phone"
                       value="{{ old('phone', $user->phone) }}"
                       placeholder="e.g. 9876543210" autocomplete="tel">
                <div class="field-hint">Optional. Leave blank to skip.</div>
                @error('phone')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field-group">
                <label class="field-label" for="email">Email Address</label>
                <input class="field-input" id="email" type="email" name="email"
                       value="{{ old('email', $user->email) }}" required autocomplete="email">
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field-group" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;background:var(--paper-2,rgba(255,255,255,.03));border:1px solid var(--paper-3,rgba(255,255,255,.08));border-radius:12px;">
                <div>
                    <div style="font-size:.875rem;font-weight:600;">Report emails</div>
                    <div class="field-hint" style="margin-top:.2rem;">Receive your risk report PDF by email after each portfolio upload.</div>
                </div>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;flex-shrink:0;">
                    <input type="hidden" name="email_reports" value="0">
                    <input type="checkbox" name="email_reports" value="1" id="email_reports"
                           {{ $user->email_reports ?? true ? 'checked' : '' }}
                           style="width:18px;height:18px;cursor:pointer;accent-color:#2563eb;">
                    <span style="font-size:.8rem;color:var(--ink-3);">On</span>
                </label>
            </div>

            <button type="submit" class="btn-save">Save Profile</button>

        </form>

    </div>

    {{-- ================================================================
       CHANGE PASSWORD
    ================================================================ --}}
    <div class="section-card">

        <div class="section-title">Change Password</div>
        <div class="section-sub">Use a long, random password to keep your account secure.</div>

        @if(session('status') === 'password-updated')
            <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.7rem 1rem;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:1.25rem;">
                ✓ Password updated successfully.
            </div>
        @endif

        @if($errors->updatePassword->any())
            <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.7rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1.25rem;">
                @foreach($errors->updatePassword->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="field-group">
                <label class="field-label" for="current_password">Current Password</label>
                <input class="field-input" id="current_password" type="password" name="current_password" autocomplete="current-password" placeholder="••••••••">
                @if($errors->updatePassword->has('current_password'))
                    <div class="field-error">{{ $errors->updatePassword->first('current_password') }}</div>
                @endif
            </div>

            <div class="field-group">
                <label class="field-label" for="new_password">New Password</label>
                <input class="field-input" id="new_password" type="password" name="password" autocomplete="new-password" placeholder="Min. 8 characters">
                @if($errors->updatePassword->has('password'))
                    <div class="field-error">{{ $errors->updatePassword->first('password') }}</div>
                @endif
            </div>

            <div class="field-group">
                <label class="field-label" for="password_confirmation">Confirm New Password</label>
                <input class="field-input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Repeat new password">
                @if($errors->updatePassword->has('password_confirmation'))
                    <div class="field-error">{{ $errors->updatePassword->first('password_confirmation') }}</div>
                @endif
            </div>

            <button type="submit" class="btn-save">Update Password</button>

        </form>

    </div>

    {{-- ================================================================
       DANGER ZONE — DELETE ACCOUNT
    ================================================================ --}}
    <div class="section-card" style="border-color:rgba(239,68,68,.2);">

        <div class="section-title" style="color:#fca5a5;">Delete Account</div>
        <div class="section-sub">
            Your account will be deactivated and recoverable for 30 days. Your RiskSignal data stays attached during the recovery window.
        </div>

        <button type="button" class="btn-danger-outline" x-data x-on:click="$dispatch('open-delete-modal')">
            Delete My Account
        </button>

    </div>

    {{-- BACK --}}
    <div>
        <a href="{{ route('dashboard') }}" style="color:var(--ink-3);font-size:.875rem;text-decoration:underline;">
            ← Back to Dashboard
        </a>
    </div>

</div>

{{-- ================================================================
   DELETE CONFIRMATION MODAL
================================================================ --}}
<div
    class="modal-overlay"
    id="deleteModal"
    x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }}, confirmation: '' }"
    x-on:open-delete-modal.window="open = true"
    x-bind:class="{ 'open': open }"
    x-cloak>
    <div class="modal-box">
        <div class="modal-title" style="color:#fca5a5;">Delete account?</div>
        <div class="modal-sub">
            Your account will be deactivated and scheduled for permanent deletion after 30 days. Type DELETE to continue.
        </div>

        @if(($user->hasActiveSubscription() || $user->isTrial()) && $user->subscription)
            <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25);color:#fcd34d;padding:.75rem .9rem;border-radius:10px;font-size:.84rem;line-height:1.5;margin-bottom:1rem;">
                <strong>Warning:</strong> You have an active {{ $user->subscription->plan?->name ?? 'RiskSignal' }} subscription.<br>
                Deleting your account will NOT automatically cancel Razorpay billing.<br>
                Email <a href="mailto:support@risksignal.in" style="color:#fde68a;text-decoration:underline;">support@risksignal.in</a> to cancel billing first.
            </div>
        @endif

        @if($errors->userDeletion->has('password'))
            <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.6rem .9rem;border-radius:8px;font-size:.82rem;margin-bottom:1rem;">
                {{ $errors->userDeletion->first('password') }}
            </div>
        @endif

        <form method="POST" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <div class="field-group">
                <label class="field-label" for="delete_password">Your Password</label>
                <input class="field-input" id="delete_password" type="password" name="password" placeholder="Confirm your password">
            </div>

            <div class="field-group">
                <label class="field-label" for="delete_confirmation">Type DELETE to continue</label>
                <input class="field-input" id="delete_confirmation" type="text" x-model="confirmation" autocomplete="off" placeholder="DELETE">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" x-on:click="open = false; confirmation = ''">
                    Cancel
                </button>
                <button type="submit" class="btn-delete-confirm" x-bind:disabled="confirmation !== 'DELETE'">
                    Yes, Delete Account
                </button>
            </div>

        </form>
    </div>
</div>

@endsection
