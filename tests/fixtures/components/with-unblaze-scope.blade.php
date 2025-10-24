@blaze
<div class="wrapper">
    <h2>Title</h2>
    @unblaze(scope: ['message' => $message])
        <div class="dynamic">{{ $scope['message'] }}</div>
    @endunblaze
    <p>Static paragraph</p>
</div>
