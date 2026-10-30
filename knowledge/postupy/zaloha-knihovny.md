# Záloha knihovny a budoucí synchronizace

- Knihovna je lokální git (`~/TS-Studio/ts-library`, větev main). Vzdálený repozitář zatím **není** a nevytváří se automaticky.
- Lokální záloha: `git bundle create ~/TS-Studio/zalohy/ts-library-$(date +%Y%m%d).bundle --all`.
- Budoucí soukromý remote (po schválení): `git remote add origin <soukromá adresa>` → `git push -u origin main --tags`. Nikdy nepřidávat `~/TS-Studio/zalohy` ani data s osobními údaji.
