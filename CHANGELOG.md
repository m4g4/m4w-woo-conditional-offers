# Changelog

All notable changes to the M4W Woo Conditional Offers plugin are documented in this file.

## [1.1.1] - 2026-09-01

### Fixed
- **Multiple cart items only received a discount on one** - WooCommerce derives a fee ID from its name, so every `Cart::add_fee( 'Conditional Offer Discount', ... )` call collided and only the first was kept. All matching discounts are now aggregated into a single fee.
- **Fixed discounts ignored quantity** - A fixed-amount discount applied once per entire cart line instead of once per item. It is now scaled by quantity (capped at the line total).
- **`<del>` and `<ins>` stripped from Custom Content** - The custom-content sanitizer removed strike-through/inserted pricing markup because those tags were missing from the allowed HTML list. They are now permitted.

## [1.1.0] - 2026-09-01

### Fixed
- **Discounted price not shown on the offering panel** - The popup, toast, and inline offer now display the discounted price (original price struck through alongside the sale price) when a rule has an automatic discount enabled for the offer product. Previously only the original price was shown.
- **Popup checkbox could not be unchecked** - When editing a rule, unchecking the "Popup" checkbox and saving left it enabled. Root cause: the legacy `display_mode` setting persisted in saved rules and re-forced `show_popup`/`show_toast` to `true` on every read. The migration now runs only once and the `display_mode` field is removed from stored rules.
- **Toast notification never displayed** - Three causes fixed:
  - The `renderToast()` frontend method referenced `self.i18n` without defining `self`, so it threw a `TypeError` and aborted before the toast could be added to the page. `var self = this;` is now declared at the top of the function.
  - The "Popup Once Per Session" check blocked the toast as well as the popup, so a toast would never appear after the first popup in a session. The once-per-session limit now applies only to the popup.
  - The toast styling relied on undefined CSS variables (`--space-16`, `--space-12`, `--space-8`), which invalidated its `top` position and rendered it off-screen. These were replaced with concrete values.

### Changed
- Checkbox fields (`show_popup`, `show_toast`, `popup_once_per_session`, `discount_enabled`) now always send their state on save via hidden inputs, and the admin JavaScript explicitly submits `1`/`0` for each one. Unchecked checkboxes are now saved correctly.
- Server-side saving now checks for the literal `1` value instead of relying on PHP truthiness of the posted field.
- Added styling for the discounted price elements (`<del>` original price, `<ins>` sale price) in the offering panel.

### Added
- Discounted price calculation for the offer panel (`get_discounted_price()`, `get_price_html_with_discount()`), supporting both percentage and fixed-amount discounts applied to the offer product (`offer_only` / `both`).
- The `m4w_wco_get_offer_product` AJAX endpoint accepts an optional `rule_id` so the returned price reflects any applicable conditional discount.

## [1.0.0] - 2026-08-28

### Added
- Initial release.
- Conditional offer rules based on trigger products in the cart.
- Popup, toast, and inline (`[m4w_wco_offer]` shortcode) offer displays.
- Automatic cart discounts (percentage or fixed) when both trigger and offer products are in the cart.
- Admin settings screen under WooCommerce with rule management.
- Slovak (SK) translations.