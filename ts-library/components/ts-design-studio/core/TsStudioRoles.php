<?php
/* ==========================================================================
   ts-design-studio – role administrace (zadání oddíl 8) → oprávnění akcí Studia. Role se berou ze stávajících účtů (CRM users.role).
   'can' => fn (int $uid, string $akce, ?array $projekt) => TsStudioRoles::allows(roleUzivatele($uid), $akce)
   Neznámá role = jen prohlížení. Kontrola probíhá v jádře u každé akce (API i rozhraní), ne jen skrytím tlačítek.
   ========================================================================== */
final class TsStudioRoles
{
    const ROLES = ['admin', 'designer', 'sales', 'viewer'];
    const MATRIX = [
        'view'     => ['admin', 'designer', 'sales', 'viewer'],
        'edit'     => ['admin', 'designer'],             // úpravy ručně i AI, nový projekt, zamítnutí, návrat
        'accept'   => ['admin', 'designer'],             // přijetí verze
        'status'   => ['admin', 'designer'],             // stav konceptu včetně interního schválení
        'deliver'  => ['admin', 'sales'],                // sdílení, e-mail, odeslání, odkaz, odvolání
        'export'   => ['admin', 'designer', 'sales'],
        'settings' => ['admin'],
    ];
    public static function allows(string $role, string $action): bool
    {
        if (!in_array($role, self::ROLES, true)) $role = 'viewer';
        return in_array($role, self::MATRIX[$action] ?? [], true);
    }
    /* seznam povolených akcí pro rozhraní (jen kvůli zobrazení – rozhoduje server) */
    public static function actions(string $role): array { return array_values(array_filter(array_keys(self::MATRIX), fn ($a) => self::allows($role, $a))); }
}
