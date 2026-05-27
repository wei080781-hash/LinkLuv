<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Profile Photo 區塊 --}}

        <div class="flex items-center gap-6">
            <div class="flex-shrink-0">
                <img id="avatar-preview" 
                src="{{ $user->profile_photo_url }}"
                class="w-20 h-20 rounded-full object-cover border">
            </div>

            <div class="flex flex-col gap-2">
                <label for="profile_photo" class="cursor-pointer px-4 py-2
                bg-white border rounded-md text-sm hover:bg-gray-50">
                   更換頭像
                </label>
                <input id="profile_photo" name="profile_photo" type="file" class="hidden" accept="image/*">

                @if($user->profile_photo_path)
                     <button type="button"
                    onclick="document.getElementById('delete-photo-form').submit()"
                    class="text-sm text-red-500 hover:text-red-700 text-left">
                    移出頭像
                </button>
                @endif
            </div>
        <div>
        <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />    
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

    {{-- 移除頭像用的獨立隱藏 Form --}}
    <form id="delete-photo" action="{{ route('profile.photo.delete') }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    {{-- 即時預覽 JavaScript --}}
    <script>
        document.getElementById('profile_photo').onchange = e => {
            if (!e.target.files.length) return;
            const reader = new FileReader();
            reader.onload = ev => {
                document.getElementById('avatar-preview').src = ev.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        };
    </script>
</section>
