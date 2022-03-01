<?php
/**
 * Created by PhpStorm.
 * User: gray
 * Date: 03/08/2020
 * Time: 17:08
 */

namespace App\Components;
use App\Models\Currency;
use App\Models\Poker\Bet;
use App\Models\Poker\Game;
use App\Models\Poker\Operation;
use App\Models\Poker\Place;
use App\Models\Poker\Table;
use App\Models\User;
use Queue;
use App\Jobs\Poker as OperationPoker;
use App\Jobs\Operation as OperationJob;
use App\Components\Exchange;
use App\Models\Operation as OperationModel;
use Lang;
use App\Components\SocketClient;
use Cache;
use DB;


class PokerEngine {

    static function getUser($data) {

        if (isset($data['user_id']) && !empty($data['key'])) {

            $api_key = DB::table('api')
                ->where('user_id', $data['user_id'])
                ->where('key', $data['key'])
                ->first();

            if ($api_key) {
                return User::find($api_key->user_id);
            }
            else return null;

        }

        return null;

    }

    static function rateChips($data) {

        $user = self::getUser($data);

        if ($user) {

            return [
                'success' => true,
                'action' => 'rateChips',
                'rate' => self::getRateChips($user),
                'bonus' => $user->bonus_poker,
                'currency' => $user->currency->symbol,
                'precision' => self::getPrecision($user->currency->id)
            ];

        }

        return [
            'success' => false,
            'msg' => 'Not found user'
        ];

    }

    static function getRateChips(User $user) {

        return round(Exchange::getRate(1, $user->currencyId)*env('RATE_CHIPS', 0.00005), 7);

    }

    static function fold($data) {

        Queue::pushOn('poker', new OperationPoker('fold', $data));

    }

    function foldJob($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->fold();
                self::updatePokerTable($place->pokerTable);
            }
        }

    }

    static function muck($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->muck();
                $info = Cache::get('table_'.$place->table_id, []);
                if (!empty($info))
                    return array_merge(['toTableId' => $place->table_id], $info);
            }
        }
    }

    static function call($data) {

        Queue::pushOn('poker', new OperationPoker('call', $data));

    }

    function callJob($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->call();
                self::updatePokerTable($place->pokerTable);
            }
        }

    }

    static function check($data) {

        Queue::pushOn('poker', new OperationPoker('check', $data));

    }

    function checkJob($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->check();
                self::updatePokerTable($place->pokerTable);
            }
        }

    }

    static function allIn($data) {

        Queue::pushOn('poker', new OperationPoker('allIn', $data));

    }

    function allInJob($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->allIn();
                self::updatePokerTable($place->pokerTable);
            }
        }

    }

    static function bet($data) {

        Queue::pushOn('poker', new OperationPoker('bet', $data));

    }

    function betJob($data) {

        if (isset($data['place_key'], $data['user_id'], $data['amount'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->bet($data['amount']);
                self::updatePokerTable($place->pokerTable);

            }
        }

    }

    static function sitUp($data) {

        Queue::pushOn('poker', new OperationPoker('sitUp', $data));

    }

    static function timeout($data) {

        Queue::pushOn('poker', new OperationPoker('timeout', $data));

    }

    function timeoutJob($data) {

        if (isset($data['place_key'], $data['user_id'])) {
            $place = Place::where('user_id', $data['user_id'])->where('key', $data['place_key'])->first();
            if ($place) {
                $place->timeout();
                self::updatePokerTable($place->pokerTable);
            }
        }

    }

    function sitDownJob($data) {

        if (empty($data['connectionId'])) return;

        $client = new SocketClient();

        $user = User::find($data['userId']);

        $place = Place::find($data['placeId']);

        if (!$place) {
            $client->sendToConnection($data['connectionId'], [
                'success' => false,
                'msg' => 'Error place id',
            ]);
            return;
        }

        if ($place->status != Place::STATUS_FREE) {
            $client->sendToConnection($data['connectionId'], [
                'success' => false,
                'msg' => 'Place taken',
            ]);
            return;
        }

        $isUserAlready = (boolean)$place->pokerTable->places()->where('user_id', $user->id)->count();

        if ($isUserAlready) {
            $client->sendToConnection($data['connectionId'], [
                'success' => false,
                'msg' => 'You are already sitting at this table',
            ]);
            return;
        }

        if (isset($data['bonus'])) {

            if ($user->bonus_poker != $data['bonus']) {
                $client->sendToConnection($data['connectionId'], [
                    'success' => false,
                    'msg' => 'Number of bonuses changed',
                ]);
                return;
            }

            $place = $place->sitDownWithBonus($user, $data['bonus']);

        }
        else {

            if ($place->pokerTable->buyIn > $data['balance']) {
                $client->sendToConnection($data['connectionId'], [
                    'success' => false,
                    'msg' => 'The number of chips is less than the minimum',
                ]);
                return;
            }

            if ($data['rate'] != self::getRateChips($user)) {
                $client->sendToConnection($data['connectionId'], [
                    'success' => false,
                    'msg' => 'Amount changed',
                ]);
                return;
            }

            if ($user->balance < $data['balance']*$data['rate']) {
                $client->sendToConnection($data['connectionId'], [
                    'success' => false,
                    'msg' => 'Amount less than your balance',
                ]);
                return;
            }

            $place = $place->sitDown($user, $data['rate'], $data['balance']);

        }

        $client->sendToConnection($data['connectionId'], [
            'success' => true,
            'place_id' => $place->id,
            'place_key' => $place->key,
        ]);

    }

    static function updatePokerTable($table) {

        $client = new SocketClient();

        $table = Table::find($table->id);

        $info = $table->info();

        Cache::put('table_'.$table->id, $info, 10);

        $client->sendToTable($table->id, $info);

    }

    static function newGame($table, $delay) {

        $info = Cache::get('table_'.$table->id, []);

        if (!isset($info['table']) || !isset($info['table']['start'])) {

            if (empty($info)) {
                $table = Table::find($table->id);
                $info = $table->info();
            }

            $info['table']['start'] = [
                'time' => $delay,
                'end' => time() + $delay,
                'all' => $delay
            ];

            Cache::put('table_'.$table->id, $info, 10);

            $client = new SocketClient();

            $client->sendToTable($table->id, $info);

        }

        Queue::laterOn('poker', $delay, new OperationPoker('newGame', ['tableId' => $table->id]));

    }

    function newGameJob($data) {

        $client = new SocketClient();

        $table = Table::find($data['tableId']);

        $table->newGame();

        self::updatePokerTable($table);

    }

    static function sendInfo($tableId) {

        $info = Cache::get('table_'.$tableId, []);

        $client = new SocketClient();

        $client->sendToTable($tableId, $info);


    }

    static function sendToUser($tableId, $userId, $data) {

        $client = new SocketClient();

        $client->sendToUser($tableId, $userId, $data);

    }

}