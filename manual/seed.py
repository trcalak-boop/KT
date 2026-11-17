#!/usr/bin/env python3
"""Smyšlená data CRM pro snímky manuálu (jen v ukázkovém prostředí msim). Výstup: sim/stav.json (id leadů)."""
import datetime as dt, json, os, sqlite3, sys, urllib.parse, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import msim
from msim import api

D = dt.date.today()
def den(n): return (D + dt.timedelta(days=n)).isoformat()
def kdy(n, h='10:00'): return '%s %s' % (den(n), h)


def must(r, m):
    if not r.get('ok'): print('CHYBA', m, json.dumps(r, ensure_ascii=False)[:400]); raise SystemExit(1)
    return r


def lead(**k):
    k.setdefault('priority', 'standardni'); k['force'] = True
    return must(api('crm_lead_ulozit', k), 'lead ' + k['name'])['id']


def kontakt(l, typ, vys, n, notes, nxt=None, uid=1, **x):
    b = {'lead_id': l, 'contact_type': typ, 'contact_result': vys, 'contacted_at': kdy(n, '09:30'), 'notes': notes}
    if nxt: b['next'] = nxt
    b.update(x); return must(api('crm_kontakt', b, uid), 'kontakt')


def main():
    msim.start()
    try:
        api('crm_init')
        db = sqlite3.connect(os.path.join(msim.DATA, 'crm.sqlite'))
        print(db.execute('SELECT id, slug, name FROM users').fetchall())
        db.execute("UPDATE users SET email = 'krystof@tasstudio.example' WHERE id = 1"); db.execute("UPDATE users SET email = 'matous@tasstudio.example' WHERE id = 2")
        db.commit(); db.close()
        S = {}
        # 1) poptávka z webového formuláře (stejně jako ji odešle návštěvník)
        form = {'name': 'Jana Nováková', 'company': 'Pekárna U Zlatého klasu', 'email': 'jana.novakova@example.test', 'phone': '+420 600 111 222',
                'website': 'https://pekarna-klas.example.test', 'sluzby[]': ['Web', 'Branding'], 'msg': 'Dobrý den,\nchtěli bychom nový web pro naši rodinnou pekárnu, ideálně s objednávkou dortů předem. Současný web je zastaralý a na mobilu se špatně čte.\nDěkuji, Jana Nováková',
                'budget': '50–100 tis. Kč', 'when': 'Do 3 měsíců', 'pref': 'E-mail', 'lang': 'cs', 'consent': '1', 't': '45000',
                'landing_page': '/cs/', 'source_page': '/cs/sluzby-web.html', 'submission_page': '/cs/kontakt.html', 'utm_source': 'google', 'utm_medium': 'organic'}
        data = urllib.parse.urlencode(form, doseq=True).encode()
        r = urllib.request.Request(msim.BASE + '/poptavka.php', data=data, headers={'Origin': msim.BASE, 'Referer': msim.BASE + '/cs/kontakt.html'})
        print('poptávka', urllib.request.urlopen(r, timeout=60).read()[:200])
        S['pekarna'] = sqlite3.connect(os.path.join(msim.DATA, 'crm.sqlite')).execute("SELECT id FROM leads WHERE name LIKE '%Klasu%' OR name LIKE '%Nováková%'").fetchone()[0]

        S['autoservis'] = lead(name='Autoservis Rychlík s.r.o.', email='servis@rychlik.example.test', phone='+420 777 000 111', website='https://rychlik.example.test',
                               assigned_user_id=1, outreach_reason='Zastaralý web bez objednávky servisu, viděli jsme ho při hledání autoservisů ve Zlíně.')
        kontakt(S['autoservis'], 'telefon', 'zajem', -2, 'Majitel pan Rychlík má zájem o nový web s online objednávkou servisu. Chce vidět podobnou referenci.',
                {'title': 'Poslat odkaz na referenci Motoria a orientační rozsah', 'due_date': den(1), 'due_time': '10:00'})

        S['kavarna'] = lead(name='Kavárna Modrý hrnek', email='ahoj@modryhrnek.example.test', phone='+420 605 222 333', website='https://modryhrnek.example.test',
                            assigned_user_id=2, priority='vysoka', outreach_reason='Doporučení od stávajícího klienta.')
        kontakt(S['kavarna'], 'schuzka', 'cenova_nabidka', -6, 'Osobní schůzka v kavárně. Chtějí jednostránkový web s menu a fotkami, rozpočet do 60 tis. Kč.',
                {'title': 'Připravit návrh webu a poslat ho klientce', 'due_date': den(2)}, uid=2)

        S['fyzio'] = lead(name='Fyzioterapie Pohyb', email='recepce@fyziopohyb.example.test', phone='+420 739 444 555', assigned_user_id=1,
                          outreach_reason='Poptávka po telefonu – rezervace termínů online.')
        kontakt(S['fyzio'], 'email', 'vice_informaci', -4, 'Poslali jsme ukázku rezervačního systému. Klient se ozve po poradě s kolegyněmi.')
        must(api('crm_cekani', {'id': S['fyzio'], 'waiting': True, 'waiting_note': 'Čekáme na rozhodnutí vedení ordinace'}), 'čekání')

        S['truhlar'] = lead(name='Truhlářství Dub', email='info@truhlarstvidub.example.test', phone='+420 731 666 777', website='https://truhlarstvidub.example.test',
                            assigned_user_id=1, outreach_reason='Dlouhodobý klient – nový katalog výrobků.')
        kontakt(S['truhlar'], 'telefon', 'zajem', -30, 'Objednávka webu potvrzena.')
        must(api('crm_stav', {'id': S['truhlar'], 'status': 'dokonceno', 'closure_reason': 'Web předán klientovi a spuštěn.'}), 'stav dokončeno')

        S['kvetiny'] = lead(name='Květinářství Lilie', email='lilie@example.test', assigned_user_id=2, priority='nizka', outreach_reason='Oslovení na veletrhu.')
        kontakt(S['kvetiny'], 'telefon', 'nezajem', -10, 'Teď nový web nechtějí, možná na jaře.', uid=2)
        must(api('crm_stav', {'id': S['kvetiny'], 'status': 'ukonceno', 'closure_reason': 'Nezájem – zkusit znovu na jaře.'}, 2), 'stav ukončeno')

        S['hotel'] = lead(name='Hotel Na Kopci', email='recepce@hotelnakopci.example.test', phone='+420 572 000 888', website='https://hotelnakopci.example.test', assigned_user_id=2,
                          outreach_reason='Hotel nemá rezervaci pokojů na webu.')
        must(api('crm_ukol', {'lead_id': S['hotel'], 'title': 'Zavolat recepci kvůli schůzce', 'due_date': den(-2), 'assigned_user_id': 2}, 2), 'úkol po termínu')
        must(api('crm_poznamka', {'lead_id': S['hotel'], 'title': 'Kontaktní osoba', 'content': 'Paní Horáková, provozní – volat dopoledne.'}, 2), 'poznámka')

        S['skola'] = lead(name='Jazyková škola Lingva', email='info@lingva.example.test', assigned_user_id=1, outreach_reason='Web v několika jazycích.')
        must(api('crm_ukol', {'lead_id': S['skola'], 'title': 'Připravit nabídku vícejazyčného webu', 'due_date': den(0), 'due_time': '14:00', 'assigned_user_id': 1}), 'úkol dnes')
        print(json.dumps(S))
        json.dump(S, open(os.path.join(msim.SIM, 'stav.json'), 'w'))
    finally:
        msim.stop()


if __name__ == '__main__':
    main()
