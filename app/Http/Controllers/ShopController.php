<?php

namespace App\Http\Controllers;

use App;
use App\Http\Controllers\BaseController;
use App\Models\Cart;
use App\Models\Good;
use App\Models\GoodCate;
use App\Models\Order;
use App\Models\OrderGood;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class ShopController extends BaseController
{
    /*
     * 分类页面
     * 添加筛选功能 
     */
    public function getGoodcate(Request $req,$id = 0)
    {   
        // 如果没有标明分类，取第一个
        if ($id == 0) {
            $info = GoodCate::where('parentid',0)->where('status',1)->orderBy('sort','asc')->orderBy('id','asc')->first();
        }
        else
        {
            $info = GoodCate::findOrFail($id);
        }
        // 如果是一级分类，打开分类列表，如果是二级分类打开产品列表
        if ($info->parentid == 0) {
            // 找出所有的一级分类来
            $allcate = GoodCate::where('parentid',0)->where('status',1)->orderBy('sort','asc')->orderBy('id','asc')->get();
            // 找当前分类的所有子分类
            $childid = explode(',',$info->arrchildid);
            unset($childid[0]);
            $subcate = GoodCate::whereIn('id',$childid)->where('status',1)->orderBy('sort','asc')->orderBy('id','asc')->get();
            return view($this->theme.'.goodcate',compact('info','allcate','subcate'));
        }
        else
        {
            $sort = isset($req->sort) ? $req->sort : 'sort';
            $sc = isset($req->sc) ? $req->sc : 'asc';
            $list = Good::where('cate_id',$id)->orderBy($sort,$sc)->orderBy('id','desc')->paginate(21);
            return view($this->theme.'.goodlist',compact('info','list'));
        }
    }
    /*
     * 当传了属性时，按属性值计算，没传时按第一个计算
     */
    public function getGood($id = '')
    {
        $info = Good::with(['goodcate'])->findOrFail($id);
        $info->pid = $info->goodcate->parentid == 0 ? $info->catid : $info->goodcate->parentid;
        return view($this->theme.'.good',compact('info'));
    }
    // 购物车
    public function getCart()
    {
        // 找出购物车
        $goods = Cart::where(function($q){
                if (!is_null(session('member'))) {
                    $q->where('user_id',session('member')->id);
                }
                else
                {
                    $q->where('session_id',session()->getId());
                }
            })->orderBy('updated_at','desc')->get();
        $goodlists = [];
        $total_prices = 0;
        // 如果有购物车
        $goods = $goods->toArray();
        // 循环查商品，方便带出属性来
        foreach ($goods as $k => $v) {
            $goodlists[$k] = Good::where('id',$v['good_id'])->where('status',1)->first();
            $goodlists[$k]['num'] = $v['nums'];
            $goodlists[$k]['price'] = $v['price'];
            $tmp_total_price = number_format($v['nums'] * $v['price'],2,'.','');
            $goodlists[$k]['total_prices'] = $tmp_total_price;
            $total_prices += $tmp_total_price;
            // 如果属性值不为0，查属性值
            if ($v['format_id']) {
                $tmp_format = GoodFormat::where('id',$v['format_id'])->value('attr_ids');
                $tmp_format = str_replace('-','.',trim($tmp_format,'-'));
                $tmp_format_name = $attrs->whereIn('id',explode('.',$tmp_format))->pluck('value')->toArray();
                $goodlists[$k]['format'] = ['fid'=>$v['format_id'],'format'=>$tmp_format,'format_name'=>implode('-',$tmp_format_name)];
            }
            else
            {
                $goodlists[$k]['format'] = ['fid'=>0,'format'=>'','format_name'=>''];
            }
        }
        // 找出所有商品来
        $info = (object) ['pid'=>0];
        $total_prices = number_format($total_prices,2,'.','');
        return view($this->theme.'.cart',compact('goods','goodlists','info','total_prices'));
    }
    // 提交订单
    public function getAddorder(Request $req)
    {
        // 找出所有 购物车
        $ids = Cart::where('user_id',session('member')->id)->orderBy('updated_at','desc')->get();
        if ($ids->count() == 0) {
            return back()->with('message','购物车里是空的，请先购物！');
        }
        // 所有产品总价
        $prices = Cart::where('user_id',session('member')->id)->sum('total_prices');
        $uid = session('member')->id;
        // 创建订单
        $order_id = App::make('com')->orderid();
        $order = ['order_id'=>$order_id,'user_id'=>$uid,'total_prices'=>$prices,'create_ip'=>$req->ip()];
        // 事务
        DB::beginTransaction();
        try {
            $oid = Order::create($order);
            // 组合order_goods数组
            $order_goods = [];
            $clear_ids = [];
            foreach ($ids as $k => $v) {
                $order_goods[$k] = ['user_id'=>$uid,'order_id'=>$oid->id,'good_id'=>$v->good_id,'format_id'=>$v->format_id,'nums'=>$v->nums,'price'=>$v->price,'total_prices'=>$v->total_prices,'created_at'=>Carbon::now(),'updated_at'=>Carbon::now()];
                $clear_ids[] = $v->id;
            }
            // 插入
            OrderGood::insert($order_goods);
            // 没出错，提交事务
            DB::commit();
            // 清空购物车里的这几个产品
            Cart::whereIn('id',$clear_ids)->delete();
            $info = (object)['pid'=>0];
            $oid = $oid->id;
            return view($this->theme.'.addorder',compact('info','oid'));
        } catch (\Exception $e) {
            // 出错回滚
            DB::rollBack();
            return back()->with('message','添加失败，请稍后再试！');
        }
        // return view('shop.cart',compact('seo','goodcateid','pcatid','goods','goodlists'));
    }
    // 订单列表
    public function getOrder(Request $req)
    {
        $info = (object) ['pid'=>0];
        // 找出订单
        $orders = Order::with(['good'=>function($q){
                    $q->with('good');
                }])->where('user_id',session('member')->id)->orderBy('id','desc')->paginate(10);
        return view($this->theme.'.order',compact('info','orders'));

    }
    // 添加购物车
    public function getAddcart(Request $req)
    {
        // 先清除一天以上的无用购物车
        Cart::where('user_id',0)->where('updated_at','<',Carbon::now()->subday())->delete();
        // 清除完成
        $sid = session()->getId();
        $id = $req->gid;
        $num = $req->num;
        if ($num < 1) {
            return back()->with('message','请选择购买数量！');
        }
        $price = $req->gp;
        // 如果用户已经登陆，查以前的购物车
        if (session()->has('member')) {
            // 当前用户此次登陆添加的
            $tmp = Cart::where('session_id',$sid)->where('user_id',session('member')->id)->where('good_id',$id)->orderBy('id','desc')->first();
            // 如果没有，看以前有没有添加过这类商品
            if(is_null($tmp))
            {
                $tmp = Cart::where('user_id',session('member')->id)->where('good_id',$id)->orderBy('id','desc')->first();
            }
        }
        else
        {
            $tmp = Cart::where('session_id',$sid)->where('good_id',$id)->orderBy('id','desc')->first();
        }
        // 查看有没有在购物车里，有累计数量
        if (!is_null($tmp)) {
            $nums = $num + $tmp->nums;
        }
        else
        {
            $nums = $num;
        }
        $userid = !is_null(session('member')) ? session('member')->id : 0;
        $total_prices = $price * $nums;
        $a = ['session_id'=>$sid,'user_id'=>$userid,'good_id'=>$id,'format_id'=>0,'nums'=>$nums,'price'=>$price,'total_prices'=>$total_prices];
        // 查看有没有在购物车里，有累计数量
        if (!is_null($tmp)) {
            Cart::where('id',$tmp->id)->update($a);
        }
        else
        {
            Cart::create($a);
        }
        // 找出所有商品来
        $info = (object) ['pid'=>0];
        return view($this->theme.'.addcart',compact('info'));
    }
    // 修改数量
    public function postChangecart(Request $req)
    {
        try {
            $id = $req->gid;
            $num = $req->num < 1 ? 1 : $req->num;
            $price = $req->price;
            Cart::where('session_id',session()->getId())->where('good_id',$id)->update(['nums'=>$num,'total_prices'=>$num * $price]);
            echo $num;
        } catch (\Exception $e) {
            echo 0;
        }
    }
    // 移除
    public function postRemovecart(Request $req)
    {
        try {
            $id = $req->id;
            Cart::where('session_id',session()->getId())->where('good_id',$id)->delete();
            echo 1;
        } catch (\Exception $e) {
            echo 0;
        }
    }
    // 取购物车数量
    public function getCartnums()
    {
        if (is_null(session('member'))) {
            $tmp = Cart::where('session_id',session()->getId())->sum('nums');
        }
        else
        {
            $tmp = Cart::where('user_id',session('member')->id)->sum('nums');
        }
        echo $tmp;
    }
}
