/* Autosalon Dynamo – vlastní kód projektu: konfigurace + oborové pravidlo jako rozšíření. Rezervační logika je jen v knihovně. */
/* Oborové pravidlo bez zásahu do jádra: elektromobily se v sobotu po 11:00 nepůjčují (nabíjení). */
var nabijeni = { name: 'nabijeni', check: function (c) { return c.resource && c.resource.id === 'id4' && TSBooking.date.wd(c.date) === 6 && c.t >= 660 ? 'nabijeni' : ''; } };
fetch('ts-config/ts-booking-core/autosalon-dynamo.json').then(function (r) { return r.json(); }).then(function (config) {
  var errors = TSBooking.validate(config); if (errors.length) { console.error('Konfigurace rezervací:', errors); return; }
  document.getElementById('vozy-seznam').innerHTML = config.resources.map(function (r) { return '<li><b>' + r.name + '</b><span>' + (r.services === '*' ? 'jízda i prohlídka' : 'jen testovací jízda') + '</span></li>'; }).join('');
  var store = TSBooking.stores.demoLocal('autosalon-dynamo', { bookings: [], blocks: [] });
  TSBookingWidget.mount('#widget', { config: config, store: store, extensions: [nabijeni],
    ui: { lang: 'cs', serviceLabel: 'Typ návštěvy', resourceLabel: 'Vůz', allowAny: false, showPrices: false, days: 21, fields: ['name', 'email', 'phone'],
          labels: { done: 'Testovací jízda je zarezervovaná', doneHint: 'Řidičský průkaz s sebou. V ostrém provozu by přišlo potvrzení e-mailem.', submit: 'Rezervovat jízdu' },
          location: 'Autosalon Dynamo, Hradecká 40, Brno', eventTitle: 'Dynamo: ', icsName: 'testovaci-jizda' } });
});
