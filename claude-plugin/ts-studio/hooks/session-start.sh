#!/bin/sh
# Připomene na začátku relace, že existuje knihovna T&S Studio a co obsahuje (krátký výpis z registru).
REG="/Users/krystof/TS-Studio/ts-library/registry/components.json"
echo "T&S Studio Library je k dispozici: /Users/krystof/TS-Studio/ts-library (skill /ts-studio:library)."
echo "Před psaním nového kódu webu (rezervace, administrace, formuláře, e-maily, PDF, doklady, přihlášení, CRM, e-shop, CMS, jazyky) nejdřív použij hotovou komponentu z knihovny přes tools/ts.py add; stav DEMO = jen ukázky/prototypy."
if [ -f "$REG" ]; then
  /usr/bin/python3 -I -c 'import json,sys; r=json.load(open(sys.argv[1])); print("Komponenty: " + ", ".join("%s %s (%s)" % (c["name"], c["version"], c["status"]) for c in r["components"]))' "$REG" 2>/dev/null
fi
