/* Salon Vlna – vlastní kód projektu: jen načte konfiguraci a připojí knihovní rezervace. Žádná rezervační logika tu není. */
fetch('ts-config/ts-booking-core/salon-vlna.json').then(function (r) { return r.json(); }).then(function (config) {
  var errors = TSBooking.validate(config); if (errors.length) { console.error('Konfigurace rezervací:', errors); return; }
  document.getElementById('cenik').innerHTML = config.services.map(function (s) {
    var lo = Math.min.apply(null, s.prices), hi = Math.max.apply(null, s.prices);
    return '<li><span>' + s.name + '</span><b>' + (lo === hi ? lo : 'od ' + lo) + ' Kč</b></li>';
  }).join('');
  var store = TSBooking.stores.demoLocal('salon-vlna', { bookings: [], blocks: [] });
  TSBookingWidget.mount('#widget', { config: config, store: store, ui: { lang: 'cs', resourceLabel: 'Stylista', variantLabel: 'Délka vlasů', serviceLabel: 'Služba', location: 'Salon Vlna, Veveří 12, Brno', eventTitle: 'Salon Vlna: ', icsName: 'salon-vlna' } });
});
