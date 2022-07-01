@props([
    'left'=>'',
    'right'=>''    
])

<div class="p-2 bg-gray-200 flex justify-between font-bold">
    <div>
        {{$left}}
    </div>
    <div>
        {{$right}}
    </div>
</div>