@if($errors->any())
    <div class="notice notice-error" role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())">
        <p class="font-bold">Please check the following</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
