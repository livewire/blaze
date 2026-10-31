@blaze(fold: true)

@php $href = is_string($href = $attributes->get('href')) ? htmlspecialchars_decode($href, ENT_QUOTES) : $href; @endphp

@unblaze(scope: ['href' => $href])
<a href="{{ $scope['href'] }}"></a>
@endunblaze
