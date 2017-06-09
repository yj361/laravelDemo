@extends('default.layout')

@section('title')
    <title>消费记录-吉鲜商城</title>
@endsection

@section('content')
<div class="bgf">

	<div class="container-fluid mt10">
		<ul>
			@foreach($list as $l)
			<li class="clearfix">{{ $l->created_at }} - {{ $l->mark }} <span class="pull-right text-danger">{{ $l->price }}</span></li>
			@endforeach
		</ul>
		<div class="pages">
	        {!! $list->links() !!}
	    </div>
	</div>
</div>
@include('default.foot')
@endsection