# 🤖 OpticWeb AI Chat Assistant - PrestaShop Module

[🇬🇷 Ελληνική Έκδοση](#ελληνική-έκδοση) | [🇬🇧 English Version](#english-version)

---

## 🇬🇷 Ελληνική Έκδοση

### 📋 Περιγραφή

Το **OpticWeb AI Chat Assistant** είναι ένα έξυπνο PrestaShop module που προσθέτει AI-powered live chat στο e-shop σας. Χρησιμοποιεί το OpenAI GPT-4o-mini για να παρέχει άμεσες, έξυπνες απαντήσεις στους πελάτες σας για προϊόντα, παραγγελίες, προσφορές και πολιτικές του καταστήματος.

### ✨ Χαρακτηριστικά

- 🤖 **OpenAI GPT-4o-mini Integration** - Χρήση της τελευταίας τεχνολογίας AI
- 🛍️ **Smart Product Search** - Αναζήτηση και προβολή προϊόντων με εικόνες
- 📦 **Order Tracking** - Παρακολούθηση παραγγελιών για συνδεδεμένους χρήστες
- 🎟️ **Voucher Information** - Ενημέρωση για ενεργά κουπόνια και προσφορές
- 📄 **CMS Pages Integration** - Πρόσβαση σε πολιτικές (αποστολές, επιστροφές κλπ)
- 📱 **Mobile Responsive** - Πλήρως προσαρμοσμένο για κινητά
- 🌐 **Multi-language** - Υποστήριξη Ελληνικών και Αγγλικών
- 📊 **Admin Dashboard & Analytics** - Στατιστικά και ιστορικό συνομιλιών
- 🎯 **Page Context Awareness** - Το AI γνωρίζει σε ποια σελίδα βρίσκεται ο χρήστης
- 💾 **Conversation History** - Αποθήκευση ιστορικού στο browser
- 🎨 **Modern UI** - Σύγχρονο, φιλικό interface με emoji

### 📦 Απαιτήσεις

- PrestaShop 8.0.0 ή νεότερο
- PHP 7.4 ή νεότερο
- OpenAI API Key (από το platform.openai.com)
- SSL Certificate (συνιστάται)

### 🚀 Εγκατάσταση

#### Μέθοδος 1: Μέσω PrestaShop Admin Panel

1. Κατεβάστε το module ως ZIP αρχείο
2. Πηγαίνετε στο **Modules → Module Manager** στο PrestaShop admin
3. Κάντε κλικ στο **Upload a module**
4. Επιλέξτε το ZIP αρχείο και κάντε upload
5. Κάντε κλικ στο **Install** μόλις εμφανιστεί
6. Κάντε κλικ στο **Configure** για να ρυθμίσετε το module

#### Μέθοδος 2: Χειροκίνητα μέσω FTP

1. Κατεβάστε και αποσυμπιέστε το module
2. Ανεβάστε το φάκελο `optic_aichat` στο `/modules/` του PrestaShop
3. Πηγαίνετε στο **Modules → Module Manager**
4. Αναζητήστε "OpticWeb AI Chat"
5. Κάντε κλικ στο **Install**
6. Κάντε κλικ στο **Configure**

### ⚙️ Ρυθμίσεις

#### 1. Λήψη OpenAI API Key

1. Πηγαίνετε στο [platform.openai.com](https://platform.openai.com)
2. Συνδεθείτε ή δημιουργήστε λογαριασμό
3. Πηγαίνετε στο **API Keys** από το μενού
4. Κάντε κλικ στο **Create new secret key**
5. Αντιγράψτε το key (θα εμφανιστεί μόνο μία φορά!)

#### 2. Διαμόρφωση Module

1. Πηγαίνετε στο **Modules → Module Manager → OpticWeb AI Chat → Configure**
2. Συμπληρώστε τα παρακάτω πεδία:

   - **Chat Widget Title**: Ο τίτλος που θα εμφανίζεται στο chat (π.χ. "Βοηθός Καταστήματος")
   - **OpenAI API Key**: Επικολλήστε το API key που πήρατε από το OpenAI
   - **System Prompt**: Οδηγίες για το AI (π.χ. "Είσαι βοηθός του e-shop XYZ. Έχουμε δωρεάν μεταφορικά άνω των 50€")
   - **Enable Page Context**: Ενεργοποιήστε για να βλέπει το AI σε ποια σελίδα είναι ο χρήστης
   - **Page Context Template**: Προαιρετικό template για τις πληροφορίες σελίδας

3. Κάντε κλικ στο **Αποθήκευση**

### 📊 Admin Dashboard

Το module περιλαμβάνει dashboard για παρακολούθηση:

1. Πηγαίνετε στο **PrestaShop Admin → Catalog** (ή αναζήτηση "AI Chat")
2. Δείτε στατιστικά:
   - Συνολικές συνομιλίες
   - Συνολικά μηνύματα
   - Μέσος χρόνος απάντησης
   - Σημερινά μηνύματα
3. Δείτε πλήρες ιστορικό συνομιλιών
4. Εξαγωγή δεδομένων σε CSV

### 🎨 Προσαρμογή

#### Χρώματα

Επεξεργαστείτε το αρχείο `/modules/optic_aichat/views/css/chat.css`:

```css
/* Αλλάξτε το κύριο χρώμα */
#optic-chat-toggle {
    background-color: #268CCD; /* Το δικό σας χρώμα */
}
```

#### Quick Reply Buttons

Επεξεργαστείτε το `/modules/optic_aichat/views/templates/hook/chat_widget.tpl`:

```smarty
<button class="quick-reply-btn" data-msg="Το μήνυμά σας">🏷️ Το κείμενο</button>
```

#### Θέση Widget

Στο CSS αρχείο, αλλάξτε το `bottom` και `right`:

```css
#optic-chat-toggle {
    bottom: 20px; /* Απόσταση από κάτω */
    right: 20px;  /* Απόσταση από δεξιά */
}
```

### 🐛 Αντιμετώπιση Προβλημάτων

#### Το chat δεν εμφανίζεται

1. Βεβαιωθείτε ότι το module είναι εγκατεστημένο και ενεργοποιημένο
2. Ελέγξτε ότι δεν υπάρχουν JavaScript errors στο console του browser
3. Καθαρίστε το cache: **Advanced Parameters → Performance → Clear cache**

#### "Configuration Error: API Key missing"

1. Βεβαιωθείτε ότι έχετε εισάγει το OpenAI API Key
2. Το key πρέπει να ξεκινά με `sk-`
3. Ελέγξτε ότι το key είναι έγκυρο στο platform.openai.com

#### "OpenAI Error: ..."

1. Ελέγξτε το υπόλοιπο στον λογαριασμό σας στο OpenAI
2. Βεβαιωθείτε ότι το API key έχει δικαιώματα για το GPT-4o-mini
3. Προσπαθήστε να δημιουργήσετε νέο API key

#### Αργές απαντήσεις

1. Το OpenAI μερικές φορές είναι αργό λόγω φόρτου
2. Ελέγξτε την ταχύτητα του internet σας
3. Βεβαιωθείτε ότι το System Prompt δεν είναι υπερβολικά μεγάλο

### 📝 Changelog

#### v1.0.0 (2026-02-18)

**Πρώτη Έκδοση:**
- ✅ OpenAI GPT-4o-mini integration
- ✅ Smart product search με εικόνες
- ✅ Order tracking για συνδεδεμένους χρήστες
- ✅ Voucher/offer information
- ✅ CMS pages integration (πολιτικές)
- ✅ Conversation history (localStorage)
- ✅ Page context awareness (καινοτομία!)
- ✅ Admin dashboard με analytics
- ✅ Mobile responsive design
- ✅ Greek & English translations
- ✅ Chat logging στη database
- ✅ Export λειτουργία

### 📄 Άδεια Χρήσης

Αυτό το module διατίθεται δωρεάν για χρήση. Δεν επιτρέπεται η μεταπώληση χωρίς άδεια.

### 💬 Υποστήριξη

Για υποστήριξη και ερωτήσεις:
- **Email**: support@opticweb.gr
- **GitHub Issues**: [github.com/galanakisbm/Ai-chat-Assistant/issues](https://github.com/galanakisbm/Ai-chat-Assistant/issues)

---

## 🇬🇧 English Version

### 📋 Description

**OpticWeb AI Chat Assistant** is an intelligent PrestaShop module that adds AI-powered live chat to your e-shop. It uses OpenAI GPT-4o-mini to provide instant, smart responses to your customers about products, orders, offers, and store policies.

### ✨ Features

- 🤖 **OpenAI GPT-4o-mini Integration** - Latest AI technology
- 🛍️ **Smart Product Search** - Search and display products with images
- 📦 **Order Tracking** - Track orders for logged-in users
- 🎟️ **Voucher Information** - Information about active coupons and offers
- 📄 **CMS Pages Integration** - Access to policies (shipping, returns, etc.)
- 📱 **Mobile Responsive** - Fully optimized for mobile devices
- 🌐 **Multi-language** - Support for Greek and English
- 📊 **Admin Dashboard & Analytics** - Statistics and conversation history
- 🎯 **Page Context Awareness** - AI knows which page the user is viewing
- 💾 **Conversation History** - Browser-based history storage
- 🎨 **Modern UI** - Contemporary, friendly interface with emojis

### 📦 Requirements

- PrestaShop 8.0.0 or newer
- PHP 7.4 or newer
- OpenAI API Key (from platform.openai.com)
- SSL Certificate (recommended)

### 🚀 Installation

#### Method 1: Via PrestaShop Admin Panel

1. Download the module as a ZIP file
2. Go to **Modules → Module Manager** in PrestaShop admin
3. Click **Upload a module**
4. Select the ZIP file and upload
5. Click **Install** when it appears
6. Click **Configure** to set up the module

#### Method 2: Manual Upload via FTP

1. Download and extract the module
2. Upload the `optic_aichat` folder to `/modules/` in PrestaShop
3. Go to **Modules → Module Manager**
4. Search for "OpticWeb AI Chat"
5. Click **Install**
6. Click **Configure**

### ⚙️ Configuration

#### 1. Get OpenAI API Key

1. Go to [platform.openai.com](https://platform.openai.com)
2. Log in or create an account
3. Go to **API Keys** from the menu
4. Click **Create new secret key**
5. Copy the key (it will only be shown once!)

#### 2. Configure Module

1. Go to **Modules → Module Manager → OpticWeb AI Chat → Configure**
2. Fill in the following fields:

   - **Chat Widget Title**: The title displayed in the chat (e.g., "Store Assistant")
   - **OpenAI API Key**: Paste the API key from OpenAI
   - **System Prompt**: Instructions for the AI (e.g., "You are an assistant for XYZ e-shop. We have free shipping over €50")
   - **Enable Page Context**: Enable to let the AI see which page the user is on
   - **Page Context Template**: Optional template for page information

3. Click **Save**

### 📊 Admin Dashboard

The module includes a dashboard for monitoring:

1. Go to **PrestaShop Admin → Catalog** (or search "AI Chat")
2. View statistics:
   - Total conversations
   - Total messages
   - Average response time
   - Today's messages
3. View complete conversation history
4. Export data to CSV

### 🎨 Customization

#### Colors

Edit `/modules/optic_aichat/views/css/chat.css`:

```css
/* Change the primary color */
#optic-chat-toggle {
    background-color: #268CCD; /* Your color */
}
```

#### Quick Reply Buttons

Edit `/modules/optic_aichat/views/templates/hook/chat_widget.tpl`:

```smarty
<button class="quick-reply-btn" data-msg="Your message">🏷️ Button text</button>
```

#### Widget Position

In the CSS file, change `bottom` and `right`:

```css
#optic-chat-toggle {
    bottom: 20px; /* Distance from bottom */
    right: 20px;  /* Distance from right */
}
```

### 🐛 Troubleshooting

#### Chat doesn't appear

1. Make sure the module is installed and enabled
2. Check for JavaScript errors in the browser console
3. Clear cache: **Advanced Parameters → Performance → Clear cache**

#### "Configuration Error: API Key missing"

1. Ensure you have entered the OpenAI API Key
2. The key should start with `sk-`
3. Verify the key is valid at platform.openai.com

#### "OpenAI Error: ..."

1. Check your account balance at OpenAI
2. Ensure the API key has permissions for GPT-4o-mini
3. Try creating a new API key

#### Slow responses

1. OpenAI can sometimes be slow due to load
2. Check your internet speed
3. Ensure the System Prompt isn't excessively long

### 📝 Changelog

#### v1.0.0 (2026-02-18)

**Initial Release:**
- ✅ OpenAI GPT-4o-mini integration
- ✅ Smart product search with images
- ✅ Order tracking for logged-in users
- ✅ Voucher/offer information
- ✅ CMS pages integration (policies)
- ✅ Conversation history (localStorage)
- ✅ Page context awareness (innovative!)
- ✅ Admin dashboard with analytics
- ✅ Mobile responsive design
- ✅ Greek & English translations
- ✅ Chat logging to database
- ✅ Export functionality

### 📄 License

This module is provided free for use. Resale is not permitted without license.

### 💬 Support

For support and questions:
- **Email**: support@opticweb.gr
- **GitHub Issues**: [github.com/galanakisbm/Ai-chat-Assistant/issues](https://github.com/galanakisbm/Ai-chat-Assistant/issues)
