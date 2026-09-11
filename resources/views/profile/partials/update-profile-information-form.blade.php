<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('個人資料') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("更換大頭貼跟姓名") }}
        </p>
    </header>

    {{-- 更新個人資料 Form --}}
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Profile Photo 區塊 --}}
        <div>
            {{-- 水平排列容器：左邊頭像，右邊按鈕 --}}
            <div class="flex items-center gap-6">

                {{-- 左側：頭像圖片 --}}
                <div class="flex-shrink-0">
                     <img id="avatar-preview" 
                        src="{{ $user->profile_photo_url }}"
                        class="w-20 h-20 rounded-full object-cover border">
                </div>
                {{-- 右側：按鈕區塊 --}}
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

            </div>
            <x-input-error class="mt-2" :messages="$errors->get('profile_photo')" />
        </div>

        {{-- 姓名 區塊 --}}
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        {{-- 儲存按鈕 區塊 --}}
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
    <form id="delete-photo-form" action="{{ route('profile.photo.delete') }}" method="POST" class="hidden">
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
