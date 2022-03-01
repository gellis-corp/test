<?php

namespace App\Components;

use App\Models\FloatNumber;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\History;
use App\Models\User;
use App\Models\Currency;
use App\Models\Address;
use App\Models\Pair;
use App\Models\Balance;
use App\Jobs\Order as OrderJob;
use App\Models\Withdraw;
use Cache;
use Queue;
use DB;
use Log;

class TradeEngine
{
    /** @var array */
    private $pairs = [];

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAIL = 2;

    public function __construct()
    {
        $this->pairs = Cache::remember('pairs', 60 * 24, function () {
            return Pair::all()->toArray();
        });
    }

    public function getPairs()
    {
        return $this->pairs;
    }

    public function getPairsIds()
    {
        return array_map(function ($item) {
            return $item['id'];
        }, $this->pairs);
    }

    /**
     * @param int $pairId
     * @param $interval - format "1 day" or "1 week" or "1 month"  or "6 month"
     * @return array
     */
    public function getTimeLine($pairId, $interval) {

        if (Cache::has($name = 'timeline _'.$pairId.'_'.camel_case($interval))) {
            return Cache::get($name);
        }
        else
            return [];

    }


    /**
     * @param $interval - format "2 days" or "1 week" or "2 hours" ..etc
     * @param array|null $pairIds
     * @return array
     */

    public function getMarkets($interval, $pairIds = null) {
        if (empty($pairIds)) {
            $pairIds = $this->getPairsIds();
        }
        $data = [];
        foreach ($pairIds as $pairId) {

            $data[] = Cache::remember('markets_'.snake_case($interval).'_'.$pairId, 5, function () use ($interval, $pairId) {
                return $this->market($interval, $pairId);
            });

        }

        return $data;
    }

    public function marketUpdate($pairId) {
        foreach (['1 day'] as $interval) {
            Cache::put('markets_'.snake_case($interval).'_'.$pairId, $this->market($interval, $pairId), 5);
        }
    }

    /**
     * @param $pairId
     * @return array
     */

    public function getMarketHistory($pairId) {

        return Cache::remember('histories_'.$pairId, 10, function () use ($pairId) {
            return $this->marketHistory($pairId);
        });


    }


    /**
     * @param User $user
     * @param string $pairId
     * @param string $type
     * @param float $amount
     * @param float $rate
     * @return Order
     * @throws \Exception
     */
    public function createOrder(User $user, $pairId, $type, $amount, $rate) {
        /** @var Pair $pair */
        $pair = Pair::find($pairId);
        /** @var Balance $balance */
        $balance = $user->getBalanceByPairAndType($pair, $type);
        $amount = new FloatNumber($amount);

        if (!$pair->checkAmount($amount)) {
            $minTrade = new FloatNumber($pair->min_trade);
            throw new \Exception("Min amount to create order for pair {$pair->currency_in->name_short}/{$pair->currency_out->name_short} is {$minTrade}");
        }

        if (!empty($balance) && $balance->checkOrder($type, $amount, new FloatNumber($rate, $pair->rounding), $pair->rounding)) {
            /** @var Order $order */
            $order = $user->orders()->create([
                'pair_id' => $pairId,
                'rate' => new FloatNumber($rate, $pair->rounding),
                'type' => $type,
                'amount' => $amount,
                'remaining' => $amount,
                'fee' => Balance::getFee($amount, new FloatNumber($rate, $pair->rounding), $pair->rounding),
                'active' => false
            ]);
            Queue::pushOn('order', new OrderJob(OrderJob::CREATE, $order->id));

            return $order;
        }

        throw new \Exception('Not enough balance');
    }

    /**
     * @param User $user
     * @param string $orderId
     * @return Order
     * @throws \Exception
     */
    public function removeOrder(User $user, $orderId) {
        /** @var Order $order */
        $order = $user->orders()->withTrashed()->where(['id' => $orderId])->first();
        if (!empty($order)) {
            if (!$order->trashed()) {
                Queue::pushOn('order', new OrderJob(OrderJob::REMOVE, $order->id));
                return $order;
            }
            throw new \Exception('Order already deleted');
        }

        throw new \Exception('Order is not yours');

    }


    /**
     * @param User $user
     * @param array $pairIds
     * @param int $offset
     * @param int $limit
     * @return array
     */

    function getOpenOrders(User $user, $pairIds = [], $offset = 0, $limit = 50) {

        $data = [];

        $orders = $user->orders()->with('pair', 'pair.currency_in', 'pair.currency_out');

        if (!empty($pairIds))
            $orders->whereIn('pair_id', $pairIds);

        $orders = $orders->orderBy('id', 'desc')
            ->take($limit)
            ->offset($offset)
            ->get();

        if ($orders) {

            foreach ($orders as $order) {

                $data[] = [
                    'id' => $order->id,
                    'pairId' => $order->pair_id,
                    'label' => $order->pair->currency_in->name_short.'/'.$order->pair->currency_out->name_short,
                    'rate' => $order->pair->rounding != 8 ? $order->rate->setScale($order->pair->rounding)->fresh() : $order->rate,
                    'type' => $order->type,
                    'amount' => $order->amount,
                    'fee' => $order->pair->rounding != 8 ? $order->fee->setScale($order->pair->rounding)->fresh() : $order->fee,
                    'remaining' => $order->pair->rounding != 8 ? $order->remaining->setScale($order->pair->rounding)->fresh() : $order->remaining,
                    'time' => $order->created_at
                ];

            }

        }
        return $data;


    }


    function insertWithdraw(User $user, $currencyId, array $information, $amount, $fee, $min) {

        if ($user->blocked)
            throw new \Exception('Withdraw is forbidden you');

        $balance = $user->balances()->where('currency_id' , '=', $currencyId)->first();

        if ($balance && $balance->available->compareTo($amount) !== -1) {

            if ($amount->compareTo($min) === -1)
                throw new \Exception('Requested amount is less than minimum');

            /** @var Withdraw $withdraw */
            $withdraw = $user->withdraws()->create([
                'fee' => $fee,
                'currency_id' => $currencyId,
                'amount' => $amount,
                'status' => self::STATUS_PENDING,
                'data' => $information,
                'amount_btc' => $amount->fresh()->multiply($balance->currency->rate_btc)
            ]);

            Queue::pushOn('order', new OrderJob(OrderJob::WITHDRAW, $withdraw->id));

            return $withdraw;
        }

        throw new \Exception('Requested amount is more than balance');
    }


    /**
     * @param $withdrawId
     * @param $result true | false
     * @return Withdraw
     */
    function completeWithdraw($withdrawId, $result) {

        $withdraw = Withdraw::findOrFail($withdrawId);

        if ($result) {
            $withdraw->status = self::STATUS_SUCCESS;
            $withdraw->save();
        }
        else {
            Queue::pushOn('order', new OrderJob(OrderJob::CANCEL_WITHDRAW, $withdraw->id));
        }

        return $withdraw;

    }

    function insertDeposit(User $user, $currencyId, array $information, $amount, $fee) {

        return $user->deposits()->create([
            'fee' => $fee,
            'currency_id' => $currencyId,
            'amount' => $amount,
            'data' => $information
        ]);
    }

    /**
     * @param $depositId
     * @param $result true | false
     * @param $information
     * @return Deposit
     */
    function depositComplete($depositId, $result, array $information = []) {

        $deposit = Deposit::findOrFail($depositId);

        if ($result) {
            $deposit->status = self::STATUS_SUCCESS;

            $user = User::find($deposit->user_id);

            $balance = $user->balances()->where('currency_id' , '=', $deposit->currency_id)->firstOrFail();

            $balance->available += $deposit->amount;

            $balance->save();
        }
        else {
            $deposit->status = self::STATUS_FAIL;
        }

        if (!$information) {
            $deposit->information = array_merge($deposit->information, $information);
        }

        return $deposit->save();

    }


}
