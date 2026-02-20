=== Word AI Chat Assistant ===
Contributors: opticweb
Tags: chat, ai, openai, chatbot, woocommerce, customer support
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat assistant for WordPress & WooCommerce. Supports product search, order help, coupons, and more. Powered by OpenAI.

== Description ==

Word AI Chat Assistant adds an intelligent floating chat widget to your WordPress / WooCommerce store, powered by the OpenAI API (GPT-4o, GPT-4o-mini, GPT-3.5-turbo, and more).

**Key Features:**

* OpenAI GPT integration with model & temperature selection
* Dual product data source: WooCommerce (live) or XML file upload
* Full admin panel with 11 configuration tabs
* Synonyms & Frequently Bought Together (FBT) rules
* Quick reply buttons (chat trigger or external link)
* Page-context awareness (product / category / cart page detection)
* Proactive welcome message with configurable delay
* Contact links (Phone, Email, WhatsApp, Viber, Messenger, Facebook, Instagram)
* Analytics dashboard with CSV export
* Chat history viewer
* Conversation & message logging in the WordPress database
* Rate limiting per IP
* Shortcode `[word_ai_chat]` for manual placement
* Auto-add to footer option
* Fully customizable colors and CSS

== Installation ==

1. Upload the `word-ai-chat-assistant` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. Go to **AI Chat** in the WordPress admin menu.
4. Enter your **OpenAI API key** in the *AI Settings* tab.
5. Configure your data source (WooCommerce or XML) in the *Products* tab.
6. Customize the widget appearance in the *General Settings* tab.
7. The chat widget will appear automatically in the site footer (or use the `[word_ai_chat]` shortcode).

== Frequently Asked Questions ==

= Does the plugin require WooCommerce? =
No. WooCommerce is optional. If not installed, the plugin operates in XML mode only.

= Which OpenAI models are supported? =
gpt-4o, gpt-4o-mini, gpt-3.5-turbo, gpt-4-turbo.

= How do I use the shortcode? =
Add `[word_ai_chat]` to any page or post to embed the chat widget there instead of (or in addition to) the footer.

== Changelog ==

= 1.0.0 =
* Initial release – port from PrestaShop optic_aichat module.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
