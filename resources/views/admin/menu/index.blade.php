@extends('admin.right')

@if(App::make('com')->ifCan('menu-add'))
@section('rmenu')
	<div data-url="{{ url('/xyshop/menu/add') }}" data-title="添加菜单" data-toggle='modal' data-target='#myModal' class="btn btn-info btn_modal">添加菜单</div>
@endsection
@endif

@section('content')

<table class="table table-striped table-hover">
	<thead>
		<tr class="success">
			<td width="60">排序</td>
			<td width="60">ID</td>
			<td>菜单名称</td>
			<td>url</td>
			<td>显示</td>
			<td>操作</td>
		</tr>
	</thead>
	<tbody>
	{!! $treeHtml !!}
	</tbody>
</table>
@endsection