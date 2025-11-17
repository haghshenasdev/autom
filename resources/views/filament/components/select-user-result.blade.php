<div class="flex items-center gap-1.5">

    <div>
        <x-filament-panels::avatar.user
            :user="$user"
            size="lg"
        />
    </div>
    <div>
        {{$name}}
    </div>

</div>
