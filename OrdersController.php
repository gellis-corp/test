<?php

namespace App\Http\Controllers\Order;

use TradeEngine;
use App\Http\Requests\Order\OrderCreate;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrdersController extends Controller
{
    /**
     * @var TradeEngine
     */
    private $tradeEngine;

    public function __construct(TradeEngine $tradeEngine) {
        $this->tradeEngine = $tradeEngine;
    }

    /**
     * @param OrderCreate $request
     * @return Response
     */
    protected function create(OrderCreate $request) {
        $input = $request->all();

        try {
            /** @var bool result */
            $result = $this->tradeEngine->createOrder(
                $request->user(),
                $input['pairId'],
                $input['type'],
                $input['amount'],
                $input['rate']
            );

            return response()->json(['result' => $result]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @param Request $request
     * @param $orderId
     * @return Response
     */
    protected function delete(Request $request, $orderId) {
        try {
            $order = $this->tradeEngine->removeOrder($request->user(), $orderId);
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @param Request $request
     * @param integer|null $pairId
     * @return Response
     */
    protected function getByUser(Request $request, $pairId = null) {
        $ordersQuery = $request->user()->orders();
        if (!empty($pairId)) {
            $ordersQuery->where(['pair_id' => $pairId]);
        };

        return response()->json($ordersQuery->get());
    }

    /**
     * @param integer $pairId
     * @return Response
     */
    protected function getByPair($pairId) {
        return response()->json(Order::where(['pair_id' => $pairId])->get());
    }
}
