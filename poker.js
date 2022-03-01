import {socket} from './socket/socket';
import Vue from 'vue';
import App from './poker/App.vue';
import {languages} from './poker/config/lang';
import $ from 'jquery';

window.$ = window.jQuery = $;

Vue.config.productionTip = false;

const getGame = (game) => {
    return game;
};
const getPlaces = (places) => {
    return places.map(e => ({
        ...e,
        cards: e.cards ? e.cards.map(c => c.toUpperCase()) : []
    }));
};
const getTable = (table) => {
    return table;
};

socket.subscribe((data) => {
    if (data.hasOwnProperty('toTableId')) {
        window.poker.action_allow = true;
    }
    if (data.hasOwnProperty('game')) {
        window.poker.game = getGame(data.game);
    }
    if (data.hasOwnProperty('places')) {
        window.poker.last_places = window.poker.places;
        window.poker.places = getPlaces(data.places);
    }
    if (data.hasOwnProperty('table')) {
        window.poker.table = getTable(data.table);
        if (data.table.hasOwnProperty('buyIn')) {
            let modal = window.poker.$children[0].$refs.room.$children.find(el => el.$vnode.tag.indexOf('ModalSit') !== -1);
            if (modal.amount === 0) {
                modal.amount = data.table.buyIn;
            }
        }
    }
    if (data.hasOwnProperty('action')) {
        if (data.success) {
            switch (data.action) {
                case 'rateChips':
                    window.poker.chip_cost = data.rate;
                    window.poker.chip_currency = data.currency;
                    window.poker.chip_precision = data.precision;
                    window.poker.bonus = data.bonus;
                    break;
                case 'tables':
                    window.poker.tables = data.tables;
                    break;
            }
        }
    }
    if (data.hasOwnProperty('place_id')) {
        window.poker.place_id = data.place_id;
    }
    if (data.hasOwnProperty('place_key')) {
        window.poker.place_key = data.place_key;
    }
});
socket.getStatus(status => window.poker.connection = status);

window.poker = new Vue({
    data: {
        socket: socket,
        connection: false,
        modal_open: 0,
        chip_cost: 0,
        chip_currency: null,
        chip_precision: 4,
        bonus: 0,
        action_allow: true,
        game: getGame(null),
        places: getPlaces([]),
        last_places: [],
        table: getTable(null),
        connectionId: window.vars.connectionId,
        ccKey: window.vars.key,
        userId: window.vars.user_id,
        session: window.vars.session,
        page: window.vars.pageType,
        tableId: window.vars.tableId,
        tables: window.vars.tables,
        tables_url: window.vars.tables_url,
        place_id: window.vars.place_id,
        place_key: window.vars.place_key,
        lang: window.langALias,
        mobile: !!window.vars.type,
        android: !!window.vars.type,
        url: location.origin,
        volume: true
    },
    computed: {},
    watch: {
        tables(value) {
            if (this.android) {
                if (this.tableId) {
                    let table = value.find(el => el.id == this.tableId);
                    if (table) {
                        this.place_id = table.place_id;
                        this.place_key = table.place_key;
                    }
                }
                else {
                    this.place_id = null;
                    this.place_key = null;
                }
            }
        },
    },
    methods: {
        getText(key) {
            if (languages[this.lang]) return languages[this.lang][key];
            else return languages['en'][key];
        },
        request(params = {}) {
            if (this.android) {
                this.socket.request({
                    ...params,
                    user_id: this.userId,
                    key: this.user,
                    connectionId: this.connectionId,
                    lang: this.lang
                });
            }
            else {
                this.socket.request({
                    ...params,
                    user_id: this.userId,
                    key: this.ccKey,
                    connectionId: this.connectionId
                });
            }
        },
        initConnection() {
            this.request({
                action: 'tables'
            });

            this.request({
                action: 'rateChips'
            });

            if (this.tableId) {
                this.request({tableId: this.tableId});
            }
        }
    },
    render: h => h(App),
    mounted() {
        if (this.android) {
            let params = location.search.replace('?', '').split('&');
            params.forEach(el => {
                let t = el.split('=');
                this[t[0]] = t[1];
            });
            if (this.userId) {
                this.initConnection();
            }
        }
        else {
            if (this.userId) {
                this.request({
                    action: 'rateChips'
                });
            }
        }
    }
}).$mount('#poker');
