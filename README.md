# grav-plugin-cachepurge

> Grav CMS plugin — Automatically purges Cloudflare CDN cache when a page is saved in Grav admin.

## Installation

```bash
cp -r grav-plugin-cachepurge /var/www/grav/user/plugins/cachepurge
```

Then enable the plugin and configure your Cloudflare credentials in Grav Admin → Plugins → Cache Purge Cloudflare.

## Configuration

| Parameter | Default | Description |
|-----------|---------|-------------|
| `enabled` | `false` | Enable/disable the plugin |
| `zone_id` | — | Your Cloudflare Zone ID |
| `api_token` | — | Cloudflare API token with Cache Purge permission |

## Hooks

| Event | Description |
|-------|-------------|
| `onPluginsInitialized` | Registers the save hook |
| `onAdminAfterSave` | Purges the Cloudflare cache for the saved page URL |

## License

MIT — Jm Rohmer / [arleo.eu](https://arleo.eu)
