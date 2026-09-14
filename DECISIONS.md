# DECISIONS

- 2026-08-29: `minimale_editor` role bevat bewust `manage_options`,
  `edit_themes`, `edit_plugins`, `update_core`, `update_plugins`,
  `update_themes` — ondanks dat dit vrij vergaande (bijna-admin) rechten zijn.
  Reden: gebruiker gaf aan dat dit nodig is om bij FSE (header/footer
  bewerken) te kunnen — al is dat in werkelijkheid alleen afhankelijk van
  `edit_theme_options` (wat ook al in de lijst zit). Expliciet gevraagd om
  toch bij de originele, volledige capability-lijst te blijven; niet zelf
  gestript. Bijeffect: `szm_amm_user_is_restricted()` in
  `szm-admin-menu-manager.php` behandelt iedereen met `manage_options` als
  onbeperkt, dus de menu-allowlist van deze plugin geldt niet voor
  `minimale_editor`-gebruikers. Geaccepteerde trade-off, geen bug.
- 2026-08-29: rolcreatie hangt zowel aan `register_activation_hook` als aan
  `admin_init`. Reden: de self-updater (Plugin Update Checker) vervangt
  bestanden zonder de plugin te deactiveren/reactiveren, dus
  `register_activation_hook` alleen bereikt géén sites die deze feature via
  een update krijgen in plaats van een verse install. `get_role()`-check
  eerst voorkomt dat het bestaande, met de hand aangepaste rol overschrijft.
- 2026-09-13: settings herschreven van één platte config (`roles` +
  `allowed_menu_slugs` + ...) naar `profiles` — genoemde profielen, elk met
  eigen allow-list/renames/regroup, gekoppeld aan één of meer rollen. Een
  rol mag in meerdere profielen tegelijk zitten (zie SPEC.md). Bij overlap:
  `szm_amm_merge_profiles()` doet een union van allow-lists; bij conflict
  over titel/icoon/regroup-doel voor dezelfde slug wint het profiel met de
  minste rollen (meest specifiek), bij gelijke specificiteit wint het eerst
  genoemde profiel in de lijst. `hide_patterns`/`add_header_footer_menu`:
  aan zodra één matchend profiel het aan heeft (OR), niet specificiteit —
  een bewust aangezette feature in profiel A mag niet stilzwijgend
  uitgezet worden doordat rol ook in profiel B zit met de optie uit.
  Migratie: `szm_amm_get_settings()` zet een oude platte config eenmalig om
  in één profiel (`profile_migrated`) de eerste keer dat hij wordt gelezen
  na de update; niet getest op een site die van vóór 2.3.0 upgradet (zie
  TODO.md).
- 2026-09-13: Administrator kan alleen in een profiel zitten via de
  expliciete, standaard-uit toggle `allow_admin_editing` — bevestigd door
  gebruiker (zie SPEC.md). Zelfs dan blijven `index.php`, `profile.php`,
  `plugins.php` en `options-general.php` (nodig om bij deze instellingen-
  pagina te komen) hard geforceerd zichtbaar voor Administrators — geen
  instelling of filter kan dat uitzetten. Zelfde harde bescherming geldt
  voor de `hidden_submenu_slugs`-regel die naar deze plugin's eigen
  instellingenpagina zou wijzen (`szm_amm_is_admin_floor_submenu()`).
  Reden: expliciete gebruikerseis dat belangrijke dingen nooit — ook niet
  per ongeluk — weg te halen zijn, ook al mag admin-menu wél bewerkbaar
  worden gemaakt.
- 2026-09-13 (2.4.0, na eerste test op fse-test-wp): profielen-UI herzien
  op basis van gebruikersfeedback:
  - Profielen worden nu als tabs getoond (één tegelijk zichtbaar, klik om
    te wisselen) in plaats van alle profielen gestapeld onder elkaar.
    Puur presentatie — de opgeslagen datastructuur (`profiles` array) is
    ongewijzigd, alleen `szm_amm_render_settings_page()` en de bijbehorende
    JS zijn aangepast.
  - Nieuwe constante `SZM_AMM_ADMIN_PROFILE_KEY` ('profile_administrator'):
    een vast, altijd aanwezig profiel met rol hard-coded op
    `array('administrator')`. Reden: gebruiker kon voorheen geen
    admin-rol aanvinken omdat de "administrator"-checkbox pas verscheen
    ná het opslaan van de `allow_admin_editing`-toggle (verwarrende
    two-step flow) — en wilde sowieso liever een vast, gelockt
    admin-profiel in plaats van administrator als losse rol-optie tussen
    gewone profielen.
    - `szm_amm_get_settings()` injecteert dit profiel opnieuw als het ooit
      ontbreekt (upgrade van 2.3.0, of tampering).
    - `szm_amm_sanitize_settings()` negeert een ingezonden `label`/`roles`
      voor deze key volledig wat rollen betreft (altijd
      `array('administrator')`) en herstelt het profiel als het niet is
      meegestuurd — het is dus niet verwijderbaar via de UI.
    - Elk ánder profiel kan 'administrator' niet meer bevatten, punt —
      niet meer alleen gestript als de toggle uit staat; de rol-checkbox
      voor administrator is helemaal weg bij gewone profielen.
    - In de Administrator-tab tonen de floor-slugs
      (`szm_amm_always_visible_slugs( true )`) als aangevinkt +
      `disabled` in de item-tabel — puur visueel "locked"; de daadwerkelijke
      afdwinging blijft ongewijzigd runtime-side (los van wat hier bewaard
      wordt), zie `szm_amm_apply_menu_allowlist()`.
- 2026-09-13: `szm_amm_get_live_menu_items()` leest nu een snapshot van
  `$menu` die op `admin_menu`-prioriteit 998 wordt genomen (vlak vóór de
  eigen prune op 999), in plaats van het live `$menu` op rendermoment.
  Reden: zonder dit zou een Administrator die zijn eigen menu beperkt via
  `allow_admin_editing` een al-gepruned menu te zien krijgen in de
  instellingen-picker, en nooit een eerder verborgen item kunnen
  terugzetten — een self-lock-out-risico dat rechtstreeks ingaat tegen de
  eis in SPEC.md.
