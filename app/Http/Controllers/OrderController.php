<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Item;
use App\Models\Deliveryman;
use App\Models\Payment;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\OrderStatusChange;

class OrderController extends Controller
{
    public $menu_id;
    public $order_id;
    public $quantity;
    public $items;
    public $item;

    public $vatPercent = 15;
    public $baseFare = 50;
    public $milageBase = 10;

    public $itemTotal = 0;

    public function generateZeroes($str)
    {
        $len = Str::length($str);
        $maxLen = 6 - $len;
        $zeroes = '';
        for ($i = 0; $i < $maxLen; $i++) {
            $zeroes = '0' . $zeroes;
        }
        return $zeroes;
        // dump($zeroes);
    }

    // output: TN000004
    public function generateOrderNo()
    {
        $order = Order::latest()->first();
        if (!$order) {
            $newOrderNo = 1;
        } else {
            $newOrderNo = $order['id'] + 1;
        }

        $zeroes = $this->generateZeroes($newOrderNo);
        return 'TN' . $zeroes . $newOrderNo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Order::with(['customer', 'deliveryman'])->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            // 'order_no' => 'required|string',
            'dispatch_lat' => 'required|string',
            'dispatch_long' => 'required|string',
            'customer_id' => 'required|numeric',
            'items.*.menu_id' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',

            'payment_no' => 'required|string',
            'type' => 'required|string',
            'distanceKm' => 'required|string',
        ]);
        $request['order_no'] = $this->generateOrderNo();
        $this->items = $request['items'];
        $order = Order::create($request->all());

        foreach ($this->items as $item) {
            Item::create([
                'order_id' => $order['id'],
                'menu_id' => $item['menu_id'],
                'quantity' => $item['quantity']
            ]);
        }

        // create payment
        // milageFare = distanceKm * milageBase
        // vat = (baseFare + milageFare) * vatPercent / 100     
        $milageFare = $request['distanceKm'] * $this->milageBase;
        Payment::create([
            'payment_no'=>$request['payment_no'],
            'status'=> 'pending',
            'type'=>$request['type'],
            'distanceKm'=>$request['distanceKm'],
            'baseFare' => $this->baseFare,
            'milageFare'=> $milageFare,
            'vat' => ($this->baseFare + $milageFare) * $this->vatPercent / 100
        ]);

        return $order;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Order::where('id', $id)->with(['customer', 'deliveryman', 'items'])->get();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        $order->update($request->all());
        return $order;
    }

    /**
     * Update order to pickup status
     */
    public function orderPickup(Request $request, $id)
    {
        $order = Order::find($id);
        $latitude = $order['dispatch_lat'];
        $longitude = $order['dispatch_long'];

        // * get rider that is online and within 50km rad, sort by distance
        $deliveryman = DB::table('deliverymen')->where('is_online', 1)->select(DB::raw("id, ( 3959 * acos( cos( radians('$latitude') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians( latitude ) ) ) ) AS distance"))->havingRaw('distance < 50')->orderBy('distance')
            ->get();
        // $deliveryman = Deliveryman::select(DB::raw("id, ( 3959 * acos( cos( radians('$latitude') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians('$longitude') ) + sin( radians('$latitude') ) * sin( radians( latitude ) ) ) ) AS distance"))->havingRaw('distance < 50')->orderBy('distance')
        //     ->get();

        if(!$deliveryman) {
            return response ([
                'message' => 'No deliveryman online'
            ], 401);
        }

        $order = Order::find($id);
        $order->update(
            [
                'status' => 'pickup',
                'deliveryman_id' => $deliveryman[0]->id
            ],
        );
        event(new OrderStatusChange($id, 'pickup'));
        
        return $order;
    }

    /**
     * Update order to delivery status
     */
    public function orderDelivery(Request $request, $id)
    {
        $validatedData['status'] = 'delivery';

        $order = Order::find($id);
        $order->update($validatedData);

        event(new OrderStatusChange($id, 'delivery'));

        return $order;
    }

    /**
     * Update order to completed status
     * TODO: Create payment
     */
    public function orderComplete(Request $request, $id)
    {
        $validatedData['status'] = 'completed';

        $order = Order::find($id);
        $order->update(['status'=> 'completed']);

        event(new OrderStatusChange($id, 'completed'));

        $payment = Payment::where('payment_no', $order['payment_no'])->first();
        $payment->update(['status'=> 'paid']);

        return $order;
    }

    public function orderCancel(Request $request, $id)
    {
        $params['status'] = 'canceled';
        $order = Order::find($id);
        $order->update($params);

        event(new OrderStatusChange($id, 'canceled'));

        $payment = Payment::where('payment_no', $order['payment_no'])->first();
        $payment->update(['status'=> 'canceled']);

        return $order;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return Order::destroy($id);
    }

    public function orderComputation(Request $request)
    {
        $request->validate([
            'dispatch_lat' => 'required|string',
            'dispatch_long' => 'required|string',
            'customer_id' => 'required|numeric',
            'items.*.menu_id' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',

            'payment_no' => 'required|string',
            'type' => 'required|string',
            'distanceKm' => 'required|string',
        ]);
        
        // get total of order items
        $this->items = $request['items'];
        foreach ($this->items as $item) {
            $menu = Menu::where('id', $item['menu_id'])->first();
            $this->itemTotal = $this->itemTotal + ( $menu['price'] * $item['quantity']);
        }

        $milageFare = $request['distanceKm'] * $this->milageBase;
        $vat = ($this->baseFare + $milageFare) * $this->vatPercent / 100;
        $total = $this->itemTotal + $milageFare + $vat + $this->baseFare;

        return [
            'milageFare'=> $milageFare,
            'vat'=> $vat,
            'itemTotal' => $this->itemTotal,
            'baseFare'=> $this->baseFare,
            'total'=> $total
        ];
    }
}
