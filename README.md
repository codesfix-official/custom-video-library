# Custom Video Library

A content library plugin for WordPress supporting both video and audio, with flexible WooCommerce monetization.

## Features

### Content Types
- **Video & Audio** — a single `video` CPT handles both; media type is set via an ACF field (`_cvl_media_type`)
- **Taxonomies** — `video_genre` (Genre) and `video_category` (Category) with REST API support
- **Custom image size** — `cvl-card` (720 × 1080, hard-cropped) registered for consistent card thumbnails
- **Archive slug** — `/library/`; single items at `/library/{slug}/`

### Video Playback
- YouTube embed (ID or full URL accepted in the `_cvl_youtube_id` field)
- Vimeo embed (ID or full URL accepted in the `_cvl_vimeo_id` field)
- Self-hosted / direct URL via HTML5 `<video>`

### Audio Playback
- HTML5 `<audio>` element with an interactive waveform visualizer (Web Audio API canvas)
- **Upload** — attach a file from the WordPress Media Library (`_cvl_audio_file`)
- **External URL** — any direct HTTPS audio link (`_cvl_video_url`)
- **Cloud storage proxy** — Google Drive and Dropbox share links are CORS-blocked by browsers; the plugin routes them through a server-side streaming proxy (`?cvl_audio=POST_ID&_cvl_nonce=…`) that forwards `Range` headers so seeking works

### Access Control
Rules are evaluated in order; the first match grants access:

| Check | Detail |
|---|---|
| Admin / editor | `edit_videos` capability |
| Free flag | `_is_free_video = 1` — public, no login required |
| No paid gate | No product, subscription, or membership assigned — treated as public |
| Post author | The user who published the item |
| WooCommerce purchase | `_wc_product_id` — any completed order containing the product |
| WC Subscription | `_wc_subscription_id` — one or more product IDs (multi-value ACF) |
| WC Memberships | `_woo_membership_plan` — one or more plan slugs/IDs (multi-value ACF) |

Guest users can always watch **free** or **ungated** content without logging in.

### Shortcodes
| Shortcode | Description |
|---|---|
| `[cvl_video_player id="123"]` | Renders the player for a single item (paywall shown to locked users) |
| `[cvl_free_library]` | Paginated card grid of all free items; filterable by `?cvl_cat=slug` |
| `[cvl_paid_library]` | Paginated card grid of all paid items; filterable by `?cvl_cat=slug` |
| `[cvl_private_library]` | Logged-in user's personal library: wishlist, purchased, in-progress |

All shortcodes accept `posts_per_page` and `paged` attributes.

### Playback Progress Tracking
- Progress saved via AJAX (`cvl_save_progress`) on `timeupdate` for HTML5 media and via polling for YouTube/Vimeo
- Resume position stored per user; player starts from `data-resume-seconds` on page load
- Progress stored in the custom `{prefix}cvl_user_progress` table

### Wishlist
- Logged-in users can add/remove items via the heart button; stored in user meta
- Guest users see a "Log in to Wishlist" link instead

### Admin
- **Settings** page — `Content Library > Settings`
- **Analytics** page — `Content Library > Analytics`
- All metadata managed by ACF; no custom metaboxes

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Advanced Custom Fields (ACF)
- WooCommerce 5.0+
- WooCommerce Subscriptions
- WooCommerce Memberships

## Installation

1. Clone this repository into `wp-content/plugins/`
2. Run `composer install`
3. Activate the plugin in WordPress admin
4. Flush permalinks: **Settings → Permalinks → Save Changes**
5. Create and configure ACF field groups for the `video` post type using the meta keys listed below

## ACF Field Reference

| Field key | Type | Description |
|---|---|---|
| `_cvl_media_type` | Select | `video` or `audio` |
| `_cvl_video_url` | URL | Direct video/audio URL or external link |
| `_cvl_youtube_id` | Text | YouTube video ID or full URL |
| `_cvl_vimeo_id` | Text | Vimeo video ID or full URL |
| `_cvl_audio_source` | Select | `upload` or `url` |
| `_cvl_audio_file` | File | WordPress media attachment ID |
| `_cvl_duration` | Number | Duration in seconds |
| `_cvl_preview_url` | URL | Public preview clip URL |
| `_cvl_age_rating` | Text | e.g. G, PG, PG-13, R |
| `_cvl_release_year` | Number | Four-digit year |
| `_is_free_video` | True/False | `1` = free for everyone |
| `_wc_product_id` | Number | WooCommerce product ID for individual purchase |
| `_wc_subscription_id` | Repeater/Number | WC Subscription product ID(s) |
| `_woo_membership_plan` | Repeater/Text | WC Membership plan slug(s) or ID(s) |

## License

GPL-2.0-or-later
