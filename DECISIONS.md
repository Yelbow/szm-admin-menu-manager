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
