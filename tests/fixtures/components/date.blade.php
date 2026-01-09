@blaze(fold: true, memo: true)

@props(['date'])

<div>Date is: {{ (new DateTime($date))->format('D, M d') }}</div>