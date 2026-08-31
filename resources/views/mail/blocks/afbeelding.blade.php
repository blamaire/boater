@if (! empty($content['url']))
    <img src="{{ $content['url'] }}" alt="{{ $content['alt'] ?? '' }}"
        style="display:block; max-width:100%; height:auto; margin:0 0 16px; border:0;">
@endif
