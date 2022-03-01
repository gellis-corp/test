<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Config;

class Order extends Model
{
    use SoftDeletes;

    const SELL = 'sell';
    const BUY = 'buy';

    protected $table = 'orders';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $hidden = ['active', 'updated_at', 'deleted_at'];

    protected $casts = [
        'active' => 'bool',
        'amount' => 'string',
        'remaining' => 'string',
        'rate' => 'string',
        'fee' => 'string',
        'user_id' => 'integer',
        'pair_id' => 'integer',
        'created_at' => 'timestamp',
    ];

    protected $fillable = [
        'active',
        'amount',
        'remaining',
        'rate',
        'type',
        'fee',
        'user_id',
        'pair_id'
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order-active', function (Builder $builder) {
            $builder->where('active', true);
        });
    }

    /**
     * @param string $value
     * @return FloatNumber
     */
    public function getAmountAttribute($value) {
        return new FloatNumber($value);
    }

    /**
     * @param FloatNumber $value
     */
    public function setAmountAttribute($value) {
        $this->attributes['amount'] = $value->getValue();
    }


    /**
     * @param string $value
     * @return FloatNumber
     */
    public function getRemainingAttribute($value) {
        return new FloatNumber($value);
    }

    /**
     * @param FloatNumber $value
     */
    public function setRemainingAttribute($value) {
        $this->attributes['remaining'] = $value->getValue();
    }

    public function pair() {
        return $this->belongsTo('App\Models\Pair');
    }

    public function user() {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * @param Order $order
     * @return FloatNumber
     */
    public function resolveWithOrder($order) {
        $amount = $this->remaining->compareTo($order->remaining) === 1 ? $order->remaining : $this->remaining;
        $this->resolveAmount($amount, $order->rate);
        $order->resolveAmount($amount, $order->rate);

        return $amount;
    }

    /**
     * Returns sell if $type is buy and vice versa
     * @param string $type
     * @return string
     */
    public static function getAnotherType($type) {
        return $type === self::BUY ? self::SELL : self::BUY;
    }

    public static function getBuyPriceForPair($pairId) {
        return self::getPriceForPair(self::BUY, $pairId);
    }

    public static function getSellPriceForPair($pairId) {
        return self::getPriceForPair(self::SELL, $pairId);
    }

    /**
     * @param FloatNumber $amount
     * @param FloatNumber $finalRate
     */
    private function resolveAmount($amount, $finalRate) {
        /** @var User $user */
        $user = $this->user()->first();
        /** @var Pair $pair */
        $pair = $this->pair()->first();
        $balanceIn = $user->getBalanceByPairAndType($pair, $this->type);
        $balanceOut = $user->getBalanceByPairAndType($pair, self::getAnotherType($this->type));

        $balanceIn->cancel($this->type, $amount, $this->rate, $pair->rounding);

        // Memorise how much user had before resolve
        $amountIn = $balanceIn->available->fresh();
        $amountOut = $balanceOut->available->fresh();

        $balanceIn->finalWriteOff($this->type, $amount, $finalRate, $pair->rounding);
        $balanceOut->finalWriteOn($this->type, $amount, $finalRate, $pair->rounding);

        // Count what's the change in balances
        $amountIn->subtract($balanceIn->available);
        $amountOut->subtract($balanceOut->available)->multiply(-1);

        $this->remaining = $this->remaining->subtract($amount);
        $this->save();

        $this->user->orderNotification(
            $this->type,
            [
                'amount1' => $amountIn,
                'currency1' => strtoupper($balanceIn->currency->name_short),
                'amount2' => $amountOut,
                'currency2' => strtoupper($balanceOut->currency->name_short)
            ]
        );
    }

    private static function getPriceForPair($type, $pairId) {
        $price = Order::where('type', $type)
            ->where('pair_id', $pairId)
            ->orderBy('id', 'desc')
            ->value('rate');

        return $price ?: '0';
    }

}
