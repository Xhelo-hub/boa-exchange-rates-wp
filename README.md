# BoA Exchange Rates WordPress Plugin

Display Bank of Albania official exchange rates on your WordPress site with automatic daily updates.

## Features

- **Direct scraping** from Bank of Albania website (no external API needed)
- **Automatic updates** at midday with smart retry logic
- **Customizable display** with table, cards, or compact modes
- **Icon customization** - choose style, color, and size using Iconify
- **Shortcode support** with flexible options
- **Admin panel** for easy management

## Installation

1. Upload the `boa-exchange-rates-wp` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **BoA Rates** in the admin menu to configure settings

## Usage

### Basic Shortcode

```
[boa_exchange_rates]
```

### Shortcode Options

| Attribute | Description | Example |
|-----------|-------------|---------|
| `currencies` | Comma-separated list of currencies | `currencies="USD,EUR,GBP"` |
| `mode` | Display mode: table, cards, compact | `mode="cards"` |
| `show_icons` | Show/hide icons: yes/no | `show_icons="yes"` |
| `show_date` | Show/hide update date: yes/no | `show_date="yes"` |
| `icon_color` | Custom icon color (hex) | `icon_color="#0066cc"` |
| `icon_size` | Icon size in pixels | `icon_size="32"` |

### Full Example

```
[boa_exchange_rates currencies="USD,EUR,GBP,CHF" mode="table" show_icons="yes" show_date="yes" icon_size="24"]
```

## Supported Currencies

| Code | Currency |
|------|----------|
| USD | US Dollar |
| EUR | Euro |
| GBP | British Pound |
| CHF | Swiss Franc |
| JPY | Japanese Yen (per 100) |
| AUD | Australian Dollar |
| CAD | Canadian Dollar |
| SEK | Swedish Krona |
| NOK | Norwegian Krone |
| DKK | Danish Krone |
| TRY | Turkish Lira |
| CNY | Chinese Yuan |
| BGN | Bulgarian Lev |
| HUF | Hungarian Forint (per 100) |
| RUB | Russian Ruble (per 100) |
| CZK | Czech Koruna |
| PLN | Polish Zloty |
| RON | Romanian Leu |
| MKD | Macedonian Denar |
| XAU | Gold (per oz) |
| XAG | Silver (per oz) |
| SDR | Special Drawing Rights |

## Automatic Updates

The plugin uses smart update scheduling:

1. **Daily check at 12:00** (noon) when BoA typically publishes new rates
2. **5-minute retry** if rates haven't been updated yet
3. **Stops checking** once rates match today's date
4. **Manual refresh** available in admin panel

## Icon Styles

Choose from three icon styles:

1. **Circle Flags** - Circular colored country flags
2. **Rectangular Flags** - Standard 4:3 ratio flags
3. **Monochrome** - Single-color icons (customizable)

Icons are powered by [Iconify](https://iconify.design/) - loaded from CDN with no local files needed.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Internet connection (for scraping BoA website)

## Data Source

Exchange rates are scraped from the official Bank of Albania website:
https://www.bankofalbania.org/Tregjet/Kursi_zyrtar_i_kembimit/

The rates follow BoA Regulation No. 1/2021 for official exchange rate fixing (fiksi).

## License

GPL v2 or later

## Support

For issues or feature requests, please open an issue on GitHub.
