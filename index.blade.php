@extends('master7')

@section('content')
    <main>
        <div class="wrapper">
            <div class="columns">
                <div class="content sign">

                    <div class="sign">
                        <h1>{{ trans('menu.sign_up') }}</h1>
                        <ul class="sign-tabs">
                            <li class="sign-tabs__tab active">
                                <a href="javascript:void(0)">
                                    <span class="iconc-star-1"></span>{{ trans('register.personal_data') }}
                                </a>
                            </li>
                            <li class="sign-tabs__tab">
                                <a href="javascript:void(0)">
                                    <span class="iconc-user-1"></span>{{ trans('register.account') }}
                                </a>
                            </li>
                        </ul>
                        {!! Form::open(['route' => session('lang').'::register'.($isPartner ? '::partner' : '').'::store', 'class' => 'form-validate']) !!}
                        <div class="tabs-item" data-id="1">
                            <div class="form-group-line">
                                <div class="form-group dark">
                                    <label>{{ trans('register.name') }}</label>
                                    <div class="form-input">
                                        <input name="name" id="name"  type="text">
                                    </div>
                                </div>
                                <div class="form-group dark">
                                    <label>{{ trans('register.surname') }}</label>
                                    <div class="form-input">
                                        <input name="lastname" id="lastname" type="text">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group-line">
                                <div class="form-group dark">
                                    <label>{{ trans('register.country') }}</label>
                                    <div class="form-input">
                                        {{ Form::select('country', $country, session('lang') == 'ir' ? 99 : (session('lang') == 'az' ? 78 : (session('lang') == 'tr' ? 47 : 74)), ['class' => 'darkselect2']) }}
                                    </div>
                                </div>
                                <div class="form-group dark">
                                    <label>{{ trans('register.date_birth') }}</label>
                                    <div class="form-input icon">
                                        <input type="text" class="datesingle" name="datefake" id="datefake">
                                        <input type="hidden" name="date" id="date">
                                        <div class="icon">
                                            <span class="iconc-calendar-icon"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group-center">
                                <div class="form-group mt3">
                                    <input type="submit" class="green" id="next" value="{{ trans('register.continue') }}">
                                </div>
                            </div>
                        </div>
                        <div style="display: none" class="tabs-item" data-id="2">
                            <div class="form-group-line">
                                <div class="form-group dark">
                                    <label>{{ trans('register.email') }}</label>
                                    <div class="form-input">
                                        <input type="text" name="email">
                                    </div>
                                </div>
                                <div class="form-group dark">
                                    <label>{{ trans('register.currency') }}</label>
                                    <div class="form-input">
                                        {{ Form::select('currency', $currency, session('lang') == 'ir' ? 4 : (session('lang') == 'az' ? 6 : (session('lang') == 'ru' ? 3 : (session('lang') == 'tr' ? 8 : 1))), ['class' => 'darkselect2']) }}
                                    </div>
                                </div>
                            </div>
                            @if ($isPartner)
                            <div class="form-group-line">
                                <div class="form-group dark">
                                    <label>{{ trans('account.partner_affiliate_program') }}</label>
                                    <div class="form-input">
                                        {{ Form::select('type', ['1' => trans('account.percentage_from_refill'),  '2' => trans('account.percentage_from_profit')], 1, ['class' => 'darkselect2']) }}
                                    </div>
                                </div>
                                <div class="form-group dark">
                                    <label>Telegram ID</label>
                                    <div class="form-input">
                                        <input type="text" name="telegram_id" id="telegram_id">
                                    </div>
                                </div>
                            </div>
                            @endif
                            <div class="form-group-line">
                                <div class="form-group dark">
                                    <label>{{ trans('register.password') }}</label>
                                    <div class="form-input">
                                        <input type="password" name="password" id="password">
                                    </div>
                                </div>
                                <div class="form-group dark">
                                    <label>{{ trans('register.password_confirmation') }}</label>
                                    <div class="form-input">
                                        <input type="password" name="password_confirmation" id="password_confirmation">
                                    </div>
                                </div>
                            </div>
                            @if(( in_array(session('lang'), ['en', 'ru', 'az'])) || env('APP_DEBUG'))
                            <div class="form-group-line">
                                <div class="form-group checkbox dark">
                                    <input type="checkbox" name="terms" id="terms">
                                    <label for="terms">{{ trans('register.terms') }}&nbsp;<a href="{{ route(session('lang').'::terms') }}" target="_blank">{{ trans('rules.terms') }}</a></label>
                                </div>
                            </div>
                            <div class="form-group-line">
                                <div class="form-group checkbox dark">
                                    <input type="checkbox" name="privacy" id="privacy">
                                    <label for="privacy">{{ trans('register.privacy') }}&nbsp;<a href="{{ route(session('lang').'::privacy') }}" target="_blank">{{ trans('rules.privacy') }}</a></label>
                                </div>
                            </div>
                            @endif
                            <div class="form-group-center">
                                <div class="form-group mt3">
                                    <input type="submit" class="green" value="{{ trans('register.sign_up') }}">
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>

                </div>
            </div>
        </div>
    </main>
@stop