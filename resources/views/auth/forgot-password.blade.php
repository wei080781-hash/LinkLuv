<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('此功能僅適用於使用「信箱 + 密碼」登入的一般帳號。
    若您是使用 Google 登入，請直接透過 Google 帳號管理密碼') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- 👈 貼在這裡：當有 status 成功訊息時，顯示倒數並自動跳轉 --}}
    @if (session('status'))
        <div class="mb-4 text-sm font-medium text-green-600">
            <span id="countdown">3</span> 秒後將自動返回首頁...
        </div>

        <script>
            let seconds = 3;
            const countdownEl = document.getElementById('countdown');

            const timer = setInterval(() => {
                seconds--;
                if (countdownEl) countdownEl.innerText = seconds;

                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = "{{ url('/') }}";
                }
            }, 1000);
        </script>
    @endif 

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('寄送密碼重設連結') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
