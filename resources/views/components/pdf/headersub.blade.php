@props([
    'title'=>''    
])
<div class="text-right mt-4">
    <div>{{$title}}</div>
    <div>{{$slot}}</div>
</div>