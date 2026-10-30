<?php
/* ==========================================================================
   ts-booking-server – e-mailová oznámení o rezervacích (přes frontu TsMailQueue z ts-mailer-php)
   Druhy: received (přijato, čeká na potvrzení), confirmed, rejected, cancelled (zákazníkovi), staff_new (provozovně).
   Každé oznámení má dedupe klíč booking-<id>-<druh>, takže se nikdy nepošle dvakrát.
   Texty: výchozí cs/en níže, přepsatelné v serverové konfiguraci notify.texts.
   ========================================================================== */
final class TsBookingNotifier
{
    private ?TsMailQueue $q; private array $n; private TsBookingEngine $e;

    public const TEXTS = [
        'cs' => [
            'received' => ['Rezervace přijata – {business}', "Dobrý den, {name},\n\nděkujeme za rezervaci. Potvrdíme ji co nejdřív.\n\n{summary}\n\nZrušit rezervaci: {cancel}\n\n{business}"],
            'confirmed' => ['Rezervace potvrzena – {business}', "Dobrý den, {name},\n\nvaše rezervace je potvrzená.\n\n{summary}\n\nZrušit rezervaci: {cancel}\n\n{business}"],
            'rejected' => ['Rezervaci nemůžeme přijmout – {business}', "Dobrý den, {name},\n\nomlouváme se, termín nemůžeme potvrdit. Vyberte prosím jiný.\n\n{summary}\n\n{business}"],
            'cancelled' => ['Rezervace zrušena – {business}', "Dobrý den, {name},\n\nrezervace byla zrušena.\n\n{summary}\n\n{business}"],
            'staff_new' => ['Nová rezervace: {when} – {service}', "Nová rezervace z webu.\n\n{summary}\nJméno: {name}\nE-mail: {email}\nTelefon: {phone}\nPoznámka: {note}\n\nSpráva: {admin}"],
            'labels' => ['service' => 'Služba', 'resource' => 'Kdo', 'when' => 'Kdy', 'units' => 'Počet osob', 'price' => 'Orientační cena'],
        ],
        'en' => [
            'received' => ['Booking received – {business}', "Hello {name},\n\nthank you for your booking. We will confirm it shortly.\n\n{summary}\n\nCancel booking: {cancel}\n\n{business}"],
            'confirmed' => ['Booking confirmed – {business}', "Hello {name},\n\nyour booking is confirmed.\n\n{summary}\n\nCancel booking: {cancel}\n\n{business}"],
            'rejected' => ['We cannot accept your booking – {business}', "Hello {name},\n\nwe are sorry, we cannot confirm this time. Please choose another one.\n\n{summary}\n\n{business}"],
            'cancelled' => ['Booking cancelled – {business}', "Hello {name},\n\nthe booking has been cancelled.\n\n{summary}\n\n{business}"],
            'staff_new' => ['New booking: {when} – {service}', "New booking from the website.\n\n{summary}\nName: {name}\nE-mail: {email}\nPhone: {phone}\nNote: {note}\n\nAdmin: {admin}"],
            'labels' => ['service' => 'Service', 'resource' => 'With', 'when' => 'When', 'units' => 'People', 'price' => 'Estimated price'],
        ],
    ];

    /* notify: business, staffEmail, cancelUrl (s {id} a {token}), adminUrl, currency, texts */
    public function __construct(?TsMailQueue $queue, array $notify, TsBookingEngine $engine)
    {
        $this->q = $queue; $this->n = $notify + ['business' => '', 'staffEmail' => null, 'cancelUrl' => '', 'adminUrl' => '', 'currency' => 'Kč', 'texts' => []]; $this->e = $engine;
    }

    public function vars(array $b, ?string $token): array
    {
        $lang = $b['lang'] ?? 'cs'; if (!in_array($lang, ['cs', 'en'], true)) $lang = 'cs';
        $L = self::TEXTS[$lang]['labels']; $s = $this->e->service($b['service']); $r = !empty($b['resource']) ? $this->e->resource($b['resource']) : null;
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $b['date']);
        $when = ($d ? $d->format($lang === 'en' ? 'j M Y' : 'j. n. Y') : $b['date']) . ' ' . TsBookingEngine::hm((int) $b['start']);
        $lines = [$L['service'] . ': ' . ($s['name'] ?? $b['service']), $L['when'] . ': ' . $when];
        if ($r) $lines[] = $L['resource'] . ': ' . ($r['name'] ?? $r['id']);
        if (!empty($b['units'])) $lines[] = $L['units'] . ': ' . $b['units'];
        if (!empty($b['price'])) $lines[] = $L['price'] . ': ' . number_format((float) $b['price'], 0, ',', ' ') . ' ' . $this->n['currency'];
        $cancel = $token && $this->n['cancelUrl'] ? strtr($this->n['cancelUrl'], ['{id}' => rawurlencode($b['id']), '{token}' => $token]) : '';
        return ['lang' => $lang, '{business}' => $this->n['business'], '{name}' => $b['name'] ?? '', '{email}' => $b['email'] ?? '', '{phone}' => $b['phone'] ?? '', '{note}' => $b['note'] ?? '',
                '{service}' => $s['name'] ?? $b['service'], '{when}' => $when, '{summary}' => implode("\n", $lines), '{cancel}' => $cancel ?: '—', '{admin}' => $this->n['adminUrl']];
    }

    /* naplánuje e-mail; vrací id ve frontě nebo null (bez fronty / bez adresy) */
    public function notify(string $kind, array $b, ?string $token = null): ?int
    {
        if (!$this->q) return null;
        $v = $this->vars($b, $token); $lang = $v['lang']; unset($v['lang']);
        $to = $kind === 'staff_new' ? $this->n['staffEmail'] : ($b['email'] ?? null);
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
        $tpl = $this->n['texts'][$lang][$kind] ?? self::TEXTS[$lang][$kind];
        $subject = strtr($tpl[0], $v); $text = strtr($tpl[1], $v);
        $html = '<div style="font:15px/1.55 system-ui,sans-serif;max-width:560px">' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>';
        $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $html);
        return $this->q->enqueue($to, $subject, $text, $html, ['dedupeKey' => 'booking-' . $b['id'] . '-' . $kind, 'kind' => 'booking_' . $kind,
            'replyTo' => $kind === 'staff_new' ? ($b['email'] ?? null) : ($this->n['staffEmail'] ?? null), 'replyName' => $kind === 'staff_new' ? ($b['name'] ?? '') : $this->n['business']]);
    }
}
