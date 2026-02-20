<?php
/**
 * OpticWeb AI Chat - Main Module File
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Optic_AiChat extends Module
{
    /**
     * Get default XML field mappings
     */
    private function getDefaultFieldMappings()
    {
        return [
            'title' => 'name',
            'description' => 'description',
            'short_description' => 'short_description',
            'category' => 'category',
            'price_sale' => 'price',
            'price_regular' => 'regular_price',
            'onsale' => 'onsale',
            'sizes' => 'size',
            'composition' => 'composition',
            'dimensions' => 'dimension',
            'instock' => 'instock',
            'url' => 'url',
            'image' => 'image',
            'product_id' => 'id',
        ];
    }

    public function __construct()
    {
        $this->name = 'optic_aichat';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Opticweb';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('OpticWeb AI Chat');
        $this->description = $this->l('AI Live Chat for Products, Offers, and Orders.');
    }

    public function install()
    {
        // Δημιουργία πίνακα για chat logs
        $sql = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."optic_aichat_logs` (
            `id_chat_log` INT(11) NOT NULL AUTO_INCREMENT,
            `id_customer` INT(11) DEFAULT NULL,
            `message` TEXT NOT NULL,
            `response` TEXT NOT NULL,
            `page_url` VARCHAR(255) DEFAULT NULL,
            `page_context` TEXT DEFAULT NULL,
            `session_id` VARCHAR(100) DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `response_time` FLOAT DEFAULT NULL,
            PRIMARY KEY (`id_chat_log`),
            KEY `id_customer` (`id_customer`),
            KEY `session_id` (`session_id`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";
        
        Db::getInstance()->execute($sql);
        
        // Δημιουργία πίνακα για analytics
        $sql_analytics = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."optic_aichat_analytics` (
            `id_conversation` INT AUTO_INCREMENT PRIMARY KEY,
            `id_customer` INT DEFAULT NULL,
            `user_message` TEXT,
            `bot_response` MEDIUMTEXT,
            `products_mentioned` VARCHAR(255) DEFAULT NULL,
            `response_time` FLOAT DEFAULT 0,
            `detected_language` VARCHAR(5) DEFAULT NULL,
            `date_add` DATETIME,
            INDEX `date_add` (`date_add`),
            INDEX `id_customer` (`id_customer`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8mb4;";
        
        Db::getInstance()->execute($sql_analytics);
        
        // Δημιουργία φακέλου για uploads
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Install default field mappings
        $defaultMappings = json_encode($this->getDefaultFieldMappings());
        
        // Ορισμός default ρυθμίσεων κατά την εγκατάσταση
        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayFooter') &&
            Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', 'OpticWeb Assistant') &&
            Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', 'Είσαι ένας ευγενικός βοηθός για το κατάστημά μας. Απάντησε σύντομα και στα Ελληνικά.') &&
            Configuration::updateValue('OPTIC_AICHAT_WELCOME_MESSAGE', 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊') &&
            Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', 1) &&
            Configuration::updateValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE', '') &&
            Configuration::updateValue('OPTIC_AICHAT_PRIMARY_COLOR', '#268CCD') &&
            Configuration::updateValue('OPTIC_AICHAT_SECONDARY_COLOR', '#1a6ba3') &&
            Configuration::updateValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR', '#ffffff') &&
            Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', $defaultMappings) &&
            Configuration::updateValue('OPTIC_AICHAT_AUTO_LANGUAGE', 1) &&
            Configuration::updateValue('OPTIC_AICHAT_FALLBACK_LANG', 'el') &&
            Configuration::updateValue('OPTIC_AICHAT_CUSTOM_ICON', '') &&
            Configuration::updateValue('OPTIC_AICHAT_CUSTOM_LOGO', '');
    }

    public function uninstall()
    {
        // Καθαρισμός ρυθμίσεων κατά την απεγκατάσταση
        Configuration::deleteByName('OPTIC_AICHAT_API_KEY');
        Configuration::deleteByName('OPTIC_AICHAT_SYSTEM_PROMPT');
        Configuration::deleteByName('OPTIC_AICHAT_WIDGET_TITLE');
        Configuration::deleteByName('OPTIC_AICHAT_WELCOME_MESSAGE');
        Configuration::deleteByName('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        Configuration::deleteByName('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
        Configuration::deleteByName('OPTIC_AICHAT_PRIMARY_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_SECONDARY_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_BUTTON_TEXT_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_XML_PATH');
        Configuration::deleteByName('OPTIC_AICHAT_PRODUCTS_INDEXED');
        Configuration::deleteByName('OPTIC_AICHAT_XML_FIELD_MAPPING');
        Configuration::deleteByName('OPTIC_AICHAT_XML_FIELDS');
        Configuration::deleteByName('OPTIC_AICHAT_XML_SAMPLE');
        Configuration::deleteByName('OPTIC_AICHAT_PRODUCTS_COUNT');
        Configuration::deleteByName('OPTIC_AICHAT_AUTO_LANGUAGE');
        Configuration::deleteByName('OPTIC_AICHAT_FALLBACK_LANG');
        Configuration::deleteByName('OPTIC_AICHAT_INCLUDE_SALES');
        Configuration::deleteByName('OPTIC_AICHAT_INCLUDE_COUPONS');
        Configuration::deleteByName('OPTIC_AICHAT_INCLUDE_STOCK');
        Configuration::deleteByName('OPTIC_AICHAT_INCLUDE_CATEGORIES');
        Configuration::deleteByName('OPTIC_AICHAT_INCLUDE_CMS');
        Configuration::deleteByName('OPTIC_AICHAT_STORE_POLICIES');
        Configuration::deleteByName('OPTIC_AICHAT_FAQ');
        Configuration::deleteByName('OPTIC_AICHAT_CUSTOM_ICON');
        Configuration::deleteByName('OPTIC_AICHAT_CUSTOM_LOGO');
        
        // Διαγραφή πίνακα chat logs
        Db::getInstance()->execute("DROP TABLE IF EXISTS `"._DB_PREFIX_."optic_aichat_logs`");
        
        // Διαγραφή πίνακα analytics
        Db::getInstance()->execute("DROP TABLE IF EXISTS `"._DB_PREFIX_."optic_aichat_analytics`");
        
        return parent::uninstall();
    }

    /**
     * Εμφάνιση της σελίδας ρυθμίσεων στο Back Office
     */
    public function getContent()
    {
        $output = '';

        // Handle Basic Settings (separate button)
        if (Tools::isSubmit('submitBasicSettings')) {
            $apiKey = trim(Tools::getValue('OPTIC_AICHAT_API_KEY'));
            
            if (empty($apiKey) || strlen($apiKey) < 20) {
                $output .= $this->displayError($this->l('Please enter a valid OpenAI API Key (minimum 20 characters).'));
            } else {
                Configuration::updateValue('OPTIC_AICHAT_API_KEY', $apiKey);
                Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', Tools::getValue('OPTIC_AICHAT_WIDGET_TITLE'));
                Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', Tools::getValue('OPTIC_AICHAT_SYSTEM_PROMPT'));
                Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', Tools::getValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT'));
                Configuration::updateValue('OPTIC_AICHAT_PRIMARY_COLOR', Tools::getValue('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD');
                Configuration::updateValue('OPTIC_AICHAT_SECONDARY_COLOR', Tools::getValue('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3');
                Configuration::updateValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR', Tools::getValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff');
                Configuration::updateValue('OPTIC_AICHAT_AUTO_LANGUAGE', Tools::getValue('OPTIC_AICHAT_AUTO_LANGUAGE'));
                Configuration::updateValue('OPTIC_AICHAT_FALLBACK_LANG', Tools::getValue('OPTIC_AICHAT_FALLBACK_LANG'));
                
                $output .= $this->displayConfirmation($this->l('Basic settings saved successfully!'));
            }
        }

        // Handle Icon Upload
        if (Tools::isSubmit('uploadChatIcon') && isset($_FILES['OPTIC_AICHAT_CUSTOM_ICON'])) {
            $result = $this->handleImageUpload($_FILES['OPTIC_AICHAT_CUSTOM_ICON'], 'chat_icon');
            if ($result['success']) {
                Configuration::updateValue('OPTIC_AICHAT_CUSTOM_ICON', $result['path']);
                $output .= $this->displayConfirmation($this->l('Chat icon uploaded successfully!'));
            } else {
                $output .= $this->displayError($result['error']);
            }
        }

        // Handle Logo Upload
        if (Tools::isSubmit('uploadChatLogo') && isset($_FILES['OPTIC_AICHAT_CUSTOM_LOGO'])) {
            $result = $this->handleImageUpload($_FILES['OPTIC_AICHAT_CUSTOM_LOGO'], 'chat_logo');
            if ($result['success']) {
                Configuration::updateValue('OPTIC_AICHAT_CUSTOM_LOGO', $result['path']);
                $output .= $this->displayConfirmation($this->l('Chat logo uploaded successfully!'));
            } else {
                $output .= $this->displayError($result['error']);
            }
        }

        // Handle Delete Icon
        if (Tools::isSubmit('deleteChatIcon')) {
            $iconPath = Configuration::get('OPTIC_AICHAT_CUSTOM_ICON');
            if ($iconPath && strpos($iconPath, '..') === false && strpos($iconPath, '/') !== 0) {
                $fullPath = _PS_MODULE_DIR_ . $this->name . '/' . $iconPath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            Configuration::updateValue('OPTIC_AICHAT_CUSTOM_ICON', '');
            $output .= $this->displayConfirmation($this->l('Chat icon deleted!'));
        }

        // Handle Delete Logo
        if (Tools::isSubmit('deleteChatLogo')) {
            $logoPath = Configuration::get('OPTIC_AICHAT_CUSTOM_LOGO');
            if ($logoPath && strpos($logoPath, '..') === false && strpos($logoPath, '/') !== 0) {
                $fullPath = _PS_MODULE_DIR_ . $this->name . '/' . $logoPath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            Configuration::updateValue('OPTIC_AICHAT_CUSTOM_LOGO', '');
            $output .= $this->displayConfirmation($this->l('Chat logo deleted!'));
        }
        
        // Handle XML Upload (separate button)
        if (Tools::isSubmit('uploadXML')) {
            if (isset($_FILES['OPTIC_AICHAT_PRODUCT_FEED']) && $_FILES['OPTIC_AICHAT_PRODUCT_FEED']['size'] > 0) {
                $uploadResult = $this->handleXMLUpload($_FILES['OPTIC_AICHAT_PRODUCT_FEED']);
                
                if ($uploadResult['success']) {
                    $autoSuggestions = $this->autoSuggestMapping($uploadResult['available_fields']);
                    Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', json_encode($autoSuggestions));
                    
                    $successMsg = '<strong>' . $this->l('XML uploaded successfully!') . '</strong><br>';
                    $successMsg .= '📊 ' . $this->l('Products found:') . ' <strong>' . $uploadResult['products_count'] . '</strong><br>';
                    $successMsg .= '🏷️ ' . $this->l('Fields detected:') . ' <strong>' . count($uploadResult['available_fields']) . '</strong><br>';
                    $successMsg .= '🤖 ' . $this->l('Auto-mapping applied:') . ' <strong>' . count($autoSuggestions) . ' fields</strong><br>';
                    $successMsg .= '👇 ' . $this->l('Please review the mapping below and click Save.');
                    
                    $output .= $this->displayConfirmation($successMsg);
                } else {
                    $output .= $this->displayError(
                        $this->l('Failed to parse XML:') . ' ' . 
                        ($uploadResult['error'] ?? 'Unknown error')
                    );
                }
            } else {
                $output .= $this->displayError($this->l('No file uploaded or file is empty.'));
            }
        }
        
        // Handle Field Mapping Save (separate button)
        if (Tools::isSubmit('submitFieldMapping')) {
            $fieldMappings = [
                'product_id' => Tools::getValue('XML_FIELD_product_id'),
                'title' => Tools::getValue('XML_FIELD_title'),
                'description' => Tools::getValue('XML_FIELD_description'),
                'short_description' => Tools::getValue('XML_FIELD_short_description'),
                'category' => Tools::getValue('XML_FIELD_category'),
                'price_sale' => Tools::getValue('XML_FIELD_price_sale'),
                'price_regular' => Tools::getValue('XML_FIELD_price_regular'),
                'onsale' => Tools::getValue('XML_FIELD_onsale'),
                'sizes' => Tools::getValue('XML_FIELD_sizes'),
                'composition' => Tools::getValue('XML_FIELD_composition'),
                'dimensions' => Tools::getValue('XML_FIELD_dimensions'),
                'instock' => Tools::getValue('XML_FIELD_instock'),
                'url' => Tools::getValue('XML_FIELD_url'),
                'image' => Tools::getValue('XML_FIELD_image'),
            ];

            $fieldMappings = array_filter($fieldMappings);
            Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', json_encode($fieldMappings));

            $xmlPath = Configuration::get('OPTIC_AICHAT_XML_PATH');
            if ($xmlPath && file_exists($xmlPath)) {
                $this->indexXMLProducts($xmlPath);
            }
            
            $output .= $this->displayConfirmation($this->l('Field mapping saved and products re-indexed!'));
        }
        
        // Handle Knowledge Base Save (separate button)
        if (Tools::isSubmit('submitKnowledgeBase')) {
            Configuration::updateValue('OPTIC_AICHAT_INCLUDE_SALES', Tools::getValue('OPTIC_AICHAT_INCLUDE_SALES'));
            Configuration::updateValue('OPTIC_AICHAT_INCLUDE_COUPONS', Tools::getValue('OPTIC_AICHAT_INCLUDE_COUPONS'));
            Configuration::updateValue('OPTIC_AICHAT_INCLUDE_STOCK', Tools::getValue('OPTIC_AICHAT_INCLUDE_STOCK'));
            Configuration::updateValue('OPTIC_AICHAT_INCLUDE_CATEGORIES', Tools::getValue('OPTIC_AICHAT_INCLUDE_CATEGORIES'));
            Configuration::updateValue('OPTIC_AICHAT_INCLUDE_CMS', Tools::getValue('OPTIC_AICHAT_INCLUDE_CMS'));
            Configuration::updateValue('OPTIC_AICHAT_STORE_POLICIES', Tools::getValue('OPTIC_AICHAT_STORE_POLICIES'));
            Configuration::updateValue('OPTIC_AICHAT_FAQ', Tools::getValue('OPTIC_AICHAT_FAQ'));
            
            $output .= $this->displayConfirmation($this->l('Knowledge Base saved successfully!'));
        }
        
        // Handle Delete XML (NEW)
        if (Tools::isSubmit('deleteXML')) {
            $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
            
            if (file_exists($uploadDir . 'products.xml')) {
                unlink($uploadDir . 'products.xml');
            }
            
            if (file_exists($uploadDir . 'products_cache.json')) {
                unlink($uploadDir . 'products_cache.json');
            }
            
            Configuration::deleteByName('OPTIC_AICHAT_XML_PATH');
            Configuration::deleteByName('OPTIC_AICHAT_XML_FIELDS');
            Configuration::deleteByName('OPTIC_AICHAT_XML_SAMPLE');
            Configuration::deleteByName('OPTIC_AICHAT_XML_FIELD_MAPPING');
            Configuration::deleteByName('OPTIC_AICHAT_PRODUCTS_COUNT');
            Configuration::deleteByName('OPTIC_AICHAT_PRODUCTS_INDEXED');
            
            $output .= $this->displayConfirmation($this->l('XML feed and cache deleted successfully!'));
        }
        
        // Handle Export Analytics
        if (Tools::isSubmit('exportAnalytics')) {
            $this->exportAnalyticsCSV();
            exit;
        }
        
        // Handle Clear Old Analytics
        if (Tools::isSubmit('clearOldAnalytics')) {
            Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'optic_aichat_analytics WHERE date_add < DATE_SUB(NOW(), INTERVAL 90 DAY)');
            $output .= $this->displayConfirmation($this->l('Old analytics data cleared successfully!'));
        }

        return $output . $this->renderTabbedForm();
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/chat.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/chat.js');
        
        // Προσθήκη DOMPurify για XSS protection
        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js');
        
        // Προσθήκη Markdown parser για rendering bot responses  
        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js');

        // Get custom colors and validate them
        $primaryColor = Configuration::get('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD';
        $secondaryColor = Configuration::get('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3';
        $buttonTextColor = Configuration::get('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff';
        
        // Validate color format (hex colors only)
        $colorPattern = '/^#[0-9A-Fa-f]{6}$/';
        if (!preg_match($colorPattern, $primaryColor)) {
            $primaryColor = '#268CCD';
        }
        if (!preg_match($colorPattern, $secondaryColor)) {
            $secondaryColor = '#1a6ba3';
        }
        if (!preg_match($colorPattern, $buttonTextColor)) {
            $buttonTextColor = '#ffffff';
        }

        // Inject CSS variables (colors are now validated)
        $customCSS = "
        <style>
        :root {
            --optic-chat-primary: " . htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') . ";
            --optic-chat-secondary: " . htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') . ";
            --optic-chat-button-text: " . htmlspecialchars($buttonTextColor, ENT_QUOTES, 'UTF-8') . ";
        }
        </style>
        ";
        
        $this->context->smarty->assign('optic_custom_css', $customCSS);

        Media::addJsDef([
            'optic_chat_ajax_url' => $this->context->link->getModuleLink('optic_aichat', 'ajax'),
            'optic_chat_welcome_message' => Configuration::get('OPTIC_AICHAT_WELCOME_MESSAGE') ?: 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊',
            'optic_chat_shop_domain' => preg_replace('/[^a-z0-9]/i', '_', Tools::getShopDomainSsl())
        ]);
    }

    public function hookDisplayFooter()
    {
        $shop = $this->context->shop;
        
        // Get custom icon and logo
        $customIcon = Configuration::get('OPTIC_AICHAT_CUSTOM_ICON');
        $customLogo = Configuration::get('OPTIC_AICHAT_CUSTOM_LOGO');
        
        $chatIcon = $customIcon ? $this->_path . $customIcon : '';
        $chatLogo = $customLogo ? $this->_path . $customLogo : '';
        
        // Fallback to shop logo if no custom logo uploaded
        if (!$chatLogo) {
            $logoPath = Configuration::get('PS_LOGO');
            $chatLogo = $this->context->link->getMediaLink(_PS_IMG_DIR_ . $logoPath);
        }
        
        $this->context->smarty->assign([
            'chat_title' => Configuration::get('OPTIC_AICHAT_WIDGET_TITLE') ?: 'AI Assistant',
            'welcome_message' => Configuration::get('OPTIC_AICHAT_WELCOME_MESSAGE') ?: 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊',
            'chat_icon' => $chatIcon,
            'chat_logo' => $chatLogo,
            'shop_name' => $shop->name,
            'optic_custom_css' => isset($this->context->smarty->tpl_vars['optic_custom_css']) 
                ? $this->context->smarty->tpl_vars['optic_custom_css']->value 
                : '',
        ]);
        
        return $this->display(__FILE__, 'views/templates/hook/chat_widget.tpl');
    }

    /**
     * Handle XML file upload with auto-detection
     */
    private function handleXMLUpload($file)
    {
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate XML
        if ($file['type'] !== 'text/xml' && $file['type'] !== 'application/xml') {
            return ['success' => false, 'error' => 'Invalid file type. Please upload an XML file.'];
        }

        $targetFile = $uploadDir . 'products.xml';
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            Configuration::updateValue('OPTIC_AICHAT_XML_PATH', $targetFile);
            
            // Auto-detect XML structure
            $detectionResult = $this->detectXMLStructure($targetFile);
            
            if ($detectionResult['success']) {
                return [
                    'success' => true,
                    'products_count' => $detectionResult['products_count'],
                    'available_fields' => $detectionResult['available_fields'],
                    'sample_product' => $detectionResult['sample_product'],
                ];
            } else {
                return ['success' => false, 'error' => 'Failed to parse XML structure.'];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to upload file.'];
    }

    /**
     * Detect XML structure and extract available fields
     */
    private function detectXMLStructure($xmlPath)
    {
        try {
            // Suppress warnings for malformed XML
            libxml_use_internal_errors(true);
            
            $xml = simplexml_load_file($xmlPath);
            
            if (!$xml) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return [
                    'success' => false, 
                    'error' => 'Invalid XML: ' . htmlspecialchars($errors[0]->message ?? 'Unknown error', ENT_QUOTES, 'UTF-8')
                ];
            }
            
            // Check if products exist
            if (!isset($xml->product)) {
                return [
                    'success' => false,
                    'error' => 'No <product> tags found in XML. Make sure XML structure is: <products><product>...</product></products>'
                ];
            }
            
            $productsCount = count($xml->product);
            $availableFields = [];
            $sampleProduct = [];
            
            // Get first product to detect structure
            if ($productsCount > 0) {
                $firstProduct = $xml->product[0];
                
                // Extract all child tags (skip root-level metadata like <created_at>)
                foreach ($firstProduct->children() as $tag => $value) {
                    // Add to available fields (for dropdown)
                    if (!in_array($tag, $availableFields)) {
                        $availableFields[] = $tag;
                    }
                    
                    // Store sample (even if empty, to show structure) - handles CDATA automatically
                    $sampleProduct[$tag] = trim((string)$value);
                }
            }
            
            // Sort fields alphabetically for better UX
            sort($availableFields);
            
            // Save detection results
            Configuration::updateValue('OPTIC_AICHAT_XML_FIELDS', json_encode($availableFields));
            Configuration::updateValue('OPTIC_AICHAT_XML_SAMPLE', json_encode($sampleProduct));
            Configuration::updateValue('OPTIC_AICHAT_PRODUCTS_COUNT', $productsCount);
            
            return [
                'success' => true,
                'products_count' => $productsCount,
                'available_fields' => $availableFields,
                'sample_product' => $sampleProduct,
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false, 
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Auto-suggest field mappings using smart matching
     */
    private function autoSuggestMapping($availableFields)
    {
        $suggestions = [];
        
        // Create lowercase map for case-insensitive matching
        $fieldMap = [];
        foreach ($availableFields as $field) {
            $fieldMap[strtolower($field)] = $field;
        }
        
        // Smart matching rules (case-insensitive)
        $rules = [
            'product_id' => ['id', 'product_id', 'sku', 'product_sku', 'item_id', 'mpn'],
            'title' => ['name', 'title', 'product_name', 'product_title', 'item_name'],
            'description' => ['description', 'desc', 'full_description', 'long_desc', 'details'],
            'short_description' => ['short_description', 'short_desc', 'summary', 'brief', 'property'],
            'category' => ['category', 'categories', 'cat', 'product_category', 'main_category'],
            'price_sale' => ['price_with_vat', 'price', 'sale_price', 'current_price', 'selling_price', 'final_price'],
            'price_regular' => ['retail_price', 'regular_price', 'original_price', 'list_price', 'msrp', 'rrp'],
            'onsale' => ['on_sale', 'onsale', 'is_sale', 'sale', 'discount_active'],
            'sizes' => ['size', 'sizes', 'available_sizes', 'size_options'],
            'composition' => ['composition', 'material', 'materials', 'fabric'],
            'dimensions' => ['dimension', 'dimensions', 'size_dimensions', 'measurements'],
            'instock' => ['instock', 'in_stock', 'stock', 'availability', 'available'],
            'url' => ['url', 'link', 'product_url', 'product_link', 'permalink'],
            'image' => ['image', 'img', 'picture', 'photo', 'image_url', 'main_image'],
        ];
        
        foreach ($rules as $moduleField => $possibleTags) {
            foreach ($possibleTags as $tag) {
                $tagLower = strtolower($tag);
                if (isset($fieldMap[$tagLower])) {
                    // Use the actual case from XML
                    $suggestions[$moduleField] = $fieldMap[$tagLower];
                    break;
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Index XML products to JSON cache
     */
    private function indexXMLProducts($xmlPath)
    {
        try {
            libxml_use_internal_errors(true);
            
            $xml = simplexml_load_file($xmlPath);
            
            if (!$xml || !isset($xml->product)) {
                return false;
            }

            // Get field mappings
            $mappings = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELD_MAPPING'), true);
            
            if (!$mappings || empty($mappings)) {
                // Use auto-suggestions as fallback
                $availableFields = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELDS'), true);
                $mappings = $this->autoSuggestMapping($availableFields);
            }

            $products = [];
            
            foreach ($xml->product as $xmlProduct) {
                $product = [];
                
                // Map each field dynamically
                foreach ($mappings as $moduleField => $xmlTag) {
                    if (isset($xmlProduct->$xmlTag)) {
                        // Trim and handle CDATA
                        $value = trim((string)$xmlProduct->$xmlTag);
                        $product[$moduleField] = $value;
                    } else {
                        $product[$moduleField] = '';
                    }
                }
                
                // Only add products with at least ID and name
                if (!empty($product['product_id']) && !empty($product['title'])) {
                    $products[] = $product;
                }
            }
            
            // Cache as JSON for faster access
            $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $cacheFile = $uploadDir . 'products_cache.json';
            $result = file_put_contents($cacheFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if ($result === false) {
                error_log('OpticAiChat: Failed to write products cache file: ' . $cacheFile);
                return false;
            }
            
            Configuration::updateValue('OPTIC_AICHAT_PRODUCTS_INDEXED', count($products));
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Handle image uploads (Icon/Logo)
     */
    private function handleImageUpload($file, $prefix)
    {
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Validate file type against allowlist
        $allowedTypes = ['image/png', 'image/svg+xml', 'image/jpeg', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => $this->l('Invalid file type. Only PNG, SVG, JPG allowed.')];
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => $this->l('File too large. Maximum 2MB.')];
        }

        // Validate actual file content for non-SVG files
        if ($file['type'] !== 'image/svg+xml') {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return ['success' => false, 'error' => $this->l('Invalid image file.')];
            }
        }

        // Sanitize extension against allowlist
        $allowedExtensions = ['png', 'svg', 'jpg', 'jpeg'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'error' => $this->l('Invalid file extension.')];
        }

        $filename = $prefix . '_' . time() . '.' . $extension;
        $targetFile = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return [
                'success' => true,
                'path' => 'uploads/' . $filename
            ];
        }
        
        return ['success' => false, 'error' => $this->l('Failed to upload file.')];
    }

    /**
     * Build dynamic context from Knowledge Base settings
     */
    public function buildDynamicContext()
    {
        $context = "";
        
        // 1. On-Sale Products
        if (Configuration::get('OPTIC_AICHAT_INCLUDE_SALES')) {
            $onSale = $this->getOnSaleProducts();
            if (!empty($onSale)) {
                $context .= "\n\n=== CURRENT PROMOTIONS (ΕΚΠΤΩΣΕΙΣ) ===\n";
                $context .= "When user asks about 'εκπτώσεις', 'προσφορές', 'sales', mention these:\n";
                foreach ($onSale as $product) {
                    $discount = $product['old_price'] - $product['price'];
                    $context .= sprintf(
                        "- %s: NOW %s€ (was %s€) - Save %.2f€!\n",
                        $product['name'],
                        $product['price'],
                        $product['old_price'],
                        $discount
                    );
                }
            }
        }
        
        // 2. Active Coupons
        if (Configuration::get('OPTIC_AICHAT_INCLUDE_COUPONS')) {
            $coupons = $this->getActiveCoupons();
            if (!empty($coupons)) {
                $context .= "\n\n=== ACTIVE DISCOUNT CODES (ΚΩΔΙΚΟΙ ΕΚΠΤΩΣΗΣ) ===\n";
                foreach ($coupons as $coupon) {
                    $context .= "- Code: '{$coupon['code']}' - {$coupon['description']}\n";
                }
            }
        }
        
        // 3. Low Stock Alerts
        if (Configuration::get('OPTIC_AICHAT_INCLUDE_STOCK')) {
            $lowStock = $this->getLowStockProducts();
            if (!empty($lowStock)) {
                $context .= "\n\n=== LOW STOCK (mention urgency!) ===\n";
                foreach ($lowStock as $product) {
                    $context .= "- {$product['name']}: Only {$product['quantity']} left in stock!\n";
                }
            }
        }
        
        // 4. Category Structure
        if (Configuration::get('OPTIC_AICHAT_INCLUDE_CATEGORIES')) {
            $categories = $this->getCategoryStructure();
            if (!empty($categories)) {
                $context .= "\n\n=== CATALOG CATEGORIES ===\n";
                $context .= $categories;
            }
        }
        
        // 5. CMS Pages
        if (Configuration::get('OPTIC_AICHAT_INCLUDE_CMS')) {
            $cms = $this->getCMSContent();
            if (!empty($cms)) {
                $context .= "\n\n=== STORE INFORMATION ===\n";
                $context .= $cms;
            }
        }
        
        // 6. Store Policies
        $policies = Configuration::get('OPTIC_AICHAT_STORE_POLICIES');
        if (!empty($policies)) {
            $context .= "\n\n=== STORE POLICIES (Shipping, Returns, Payment) ===\n";
            $context .= trim($policies) . "\n";
        }
        
        // 7. FAQ
        $faq = Configuration::get('OPTIC_AICHAT_FAQ');
        if (!empty($faq)) {
            $context .= "\n\n=== FREQUENTLY ASKED QUESTIONS ===\n";
            $context .= trim($faq) . "\n";
        }
        
        return $context;
    }

    private function getOnSaleProducts()
    {
        $cacheFile = _PS_MODULE_DIR_ . $this->name . '/uploads/products_cache.json';
        
        if (!file_exists($cacheFile)) {
            return [];
        }
        
        $products = json_decode(file_get_contents($cacheFile), true);
        if (!$products) {
            return [];
        }
        
        $onSale = [];
        
        foreach ($products as $product) {
            $isOnSale = ($product['onsale'] == '1' || strtolower($product['onsale']) == 'y');
            $hasDiscount = !empty($product['price_regular']) && $product['price_sale'] < $product['price_regular'];
            
            if ($isOnSale || $hasDiscount) {
                $onSale[] = [
                    'name' => $product['title'],
                    'price' => $product['price_sale'],
                    'old_price' => $product['price_regular'] ?: $product['price_sale'],
                    'url' => $product['url'],
                ];
            }
        }
        
        return $onSale;
    }

    private function getActiveCoupons()
    {
        $idLang = (int)Context::getContext()->language->id;
        
        $sql = 'SELECT cr.code, crl.name as description
                FROM ' . _DB_PREFIX_ . 'cart_rule cr
                LEFT JOIN ' . _DB_PREFIX_ . 'cart_rule_lang crl ON cr.id_cart_rule = crl.id_cart_rule
                WHERE cr.active = 1
                AND crl.id_lang = ' . $idLang . '
                AND (cr.date_to >= NOW() OR cr.date_to = "0000-00-00 00:00:00")
                AND (cr.quantity > 0 OR cr.quantity = 0)
                LIMIT 10';
        
        return Db::getInstance()->executeS($sql) ?: [];
    }

    private function getLowStockProducts()
    {
        $cacheFile = _PS_MODULE_DIR_ . $this->name . '/uploads/products_cache.json';
        
        if (!file_exists($cacheFile)) {
            return [];
        }
        
        $products = json_decode(file_get_contents($cacheFile), true);
        if (!$products) {
            return [];
        }
        
        $lowStock = [];
        
        // For XML-based products, we rely on instock flag
        // In real scenarios, you'd query the database for actual quantities
        foreach ($products as $product) {
            if (strtolower($product['instock']) == 'n' || strtolower($product['instock']) == 'no') {
                $lowStock[] = [
                    'name' => $product['title'],
                    'quantity' => 0
                ];
            }
        }
        
        return $lowStock;
    }

    private function getCategoryStructure()
    {
        $cacheFile = _PS_MODULE_DIR_ . $this->name . '/uploads/products_cache.json';
        
        if (!file_exists($cacheFile)) {
            return '';
        }
        
        $products = json_decode(file_get_contents($cacheFile), true);
        if (!$products) {
            return '';
        }
        
        $categories = [];
        
        foreach ($products as $product) {
            $cat = $product['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = 0;
            }
            $categories[$cat]++;
        }
        
        $output = '';
        foreach ($categories as $cat => $count) {
            $output .= "- {$cat} ({$count} products)\n";
        }
        
        return $output;
    }

    private function getCMSContent()
    {
        $idLang = (int)Context::getContext()->language->id;
        
        $sql = 'SELECT cl.meta_title, cl.content
                FROM ' . _DB_PREFIX_ . 'cms_lang cl
                INNER JOIN ' . _DB_PREFIX_ . 'cms c ON cl.id_cms = c.id_cms
                WHERE cl.id_lang = ' . $idLang . '
                AND c.active = 1
                LIMIT 5';
        
        $pages = Db::getInstance()->executeS($sql);
        if (!$pages) {
            return '';
        }
        
        $output = '';
        foreach ($pages as $page) {
            $summary = strip_tags($page['content']);
            $summary = mb_substr($summary, 0, 200) . '...';
            $output .= "- {$page['meta_title']}: {$summary}\n";
        }
        
        return $output;
    }

    /**
     * Analytics methods
     */
    private function renderAnalyticsDashboard()
    {
        // Check if analytics table exists
        $tableExists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "optic_aichat_analytics'");

        if (empty($tableExists)) {
            return '<div class="alert alert-warning">
                        <i class="icon-warning"></i> ' .
                        $this->l('Analytics database not yet created. It will be created automatically when conversations start.') .
                    '</div>';
        }

        $stats = $this->getAnalyticsStats();
        $topQuestions = $this->getTopQuestions(10);
        $productMentions = $this->getTopProductMentions(10);
        
        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-bar-chart"></i> ' . $this->l('Analytics Dashboard') . '
                <span class="badge badge-success pull-right">' . $this->l('Last 30 Days') . '</span>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="alert alert-info">
                            <h4><i class="icon-comments"></i> ' . number_format($stats['total_conversations']) . '</h4>
                            <p>' . $this->l('Total Conversations') . '</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-success">
                            <h4><i class="icon-comment"></i> ' . number_format($stats['total_messages']) . '</h4>
                            <p>' . $this->l('Total Messages') . '</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-warning">
                            <h4><i class="icon-time"></i> ' . number_format($stats['avg_response_time'], 2) . 's</h4>
                            <p>' . $this->l('Avg Response Time') . '</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="alert alert-danger">
                            <h4><i class="icon-exchange"></i> ' . number_format($stats['avg_messages_per_day'], 1) . '</h4>
                            <p>' . $this->l('Avg Messages/Day') . '</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h4><i class="icon-question"></i> ' . $this->l('Most Asked Questions') . '</h4>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>' . $this->l('Question') . '</th>
                                    <th>' . $this->l('Count') . '</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        $i = 1;
        foreach ($topQuestions as $q) {
            $html .= '<tr>
                        <td>' . $i++ . '</td>
                        <td>' . htmlspecialchars(mb_substr($q['user_message'], 0, 60)) . '...</td>
                        <td><span class="badge badge-primary">' . $q['count'] . '</span></td>
                      </tr>';
        }
        
        $html .= '
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h4><i class="icon-search"></i> ' . $this->l('Popular Search Terms') . '</h4>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>' . $this->l('Keyword') . '</th>
                                    <th>' . $this->l('Mentions') . '</th>
                                </tr>
                            </thead>
                            <tbody>';
        
        $i = 1;
        foreach ($productMentions as $pm) {
            $html .= '<tr>
                        <td>' . $i++ . '</td>
                        <td><strong>' . htmlspecialchars($pm['keyword']) . '</strong></td>
                        <td><span class="badge badge-success">' . $pm['count'] . '</span></td>
                      </tr>';
        }
        
        $html .= '
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-12">
                        <form method="post" action="' . AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '" style="display:inline;">
                            <button type="submit" name="exportAnalytics" class="btn btn-primary">
                                <i class="icon-download"></i> ' . $this->l('Export to CSV') . '
                            </button>
                        </form>
                        
                        <form method="post" action="' . AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '" style="display:inline;" onsubmit="return confirm(\'' . $this->l('Delete all analytics data older than 90 days?') . '\');">
                            <button type="submit" name="clearOldAnalytics" class="btn btn-warning">
                                <i class="icon-trash"></i> ' . $this->l('Clear Old Data (>90 days)') . '
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
        
        return $html;
    }

    private function getAnalyticsStats()
    {
        $sql = 'SELECT 
                    COUNT(DISTINCT id_customer) as total_conversations,
                    COUNT(*) as total_messages,
                    AVG(response_time) as avg_response_time,
                    COUNT(*) / GREATEST(COUNT(DISTINCT DATE(date_add)), 1) as avg_messages_per_day
                FROM ' . _DB_PREFIX_ . 'optic_aichat_analytics
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        
        $result = Db::getInstance()->getRow($sql);
        
        return [
            'total_conversations' => $result['total_conversations'] ?: 0,
            'total_messages' => $result['total_messages'] ?: 0,
            'avg_response_time' => $result['avg_response_time'] ?: 0,
            'avg_messages_per_day' => $result['avg_messages_per_day'] ?: 0,
        ];
    }

    private function getTopQuestions($limit = 10)
    {
        $sql = 'SELECT user_message, COUNT(*) as count
                FROM ' . _DB_PREFIX_ . 'optic_aichat_analytics
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND user_message != ""
                GROUP BY user_message
                ORDER BY count DESC
                LIMIT ' . (int)$limit;
        
        return Db::getInstance()->executeS($sql) ?: [];
    }

    private function getTopProductMentions($limit = 10)
    {
        $sql = 'SELECT products_mentioned, COUNT(*) as count
                FROM ' . _DB_PREFIX_ . 'optic_aichat_analytics
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND products_mentioned IS NOT NULL
                AND products_mentioned != ""
                GROUP BY products_mentioned
                ORDER BY count DESC
                LIMIT ' . (int)$limit;
        
        $results = Db::getInstance()->executeS($sql) ?: [];
        
        // Flatten comma-separated keywords
        $keywords = [];
        foreach ($results as $row) {
            $parts = explode(',', $row['products_mentioned']);
            foreach ($parts as $keyword) {
                $keyword = trim($keyword);
                if (!isset($keywords[$keyword])) {
                    $keywords[$keyword] = 0;
                }
                $keywords[$keyword] += $row['count'];
            }
        }
        
        arsort($keywords);
        
        $output = [];
        $i = 0;
        foreach ($keywords as $keyword => $count) {
            if ($i++ >= $limit) break;
            $output[] = ['keyword' => $keyword, 'count' => $count];
        }
        
        return $output;
    }

    private function exportAnalyticsCSV()
    {
        $sql = 'SELECT user_message, bot_response, products_mentioned, response_time, detected_language, date_add
                FROM ' . _DB_PREFIX_ . 'optic_aichat_analytics
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY date_add DESC';
        
        $data = Db::getInstance()->executeS($sql);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=analytics_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['Date', 'User Message', 'Bot Response', 'Keywords', 'Response Time', 'Language']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['date_add'],
                mb_substr($row['user_message'], 0, 200),
                mb_substr($row['bot_response'], 0, 200),
                $row['products_mentioned'],
                $row['response_time'] . 's',
                $row['detected_language'],
            ]);
        }
        
        fclose($output);
    }

    /**
     * Tabbed UI methods
     */
    private function renderTabbedForm()
    {
        $currentTab = Tools::getValue('section', 'basic');

        // Validate section value
        $validTabs = ['basic', 'xml', 'knowledge', 'analytics'];
        if (!in_array($currentTab, $validTabs)) {
            $currentTab = 'basic';
        }

        $tabs = [
            'basic' => $this->l('Basic Settings'),
            'xml' => $this->l('XML Product Feed'),
            'knowledge' => $this->l('Knowledge Base'),
            'analytics' => $this->l('Analytics'),
        ];

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-cogs"></i> ' . $this->l('OpticWeb AI Chat Configuration') . '</div>';
        $html .= '<ul class="nav nav-tabs" role="tablist">';

        foreach ($tabs as $key => $label) {
            $activeClass = ($currentTab == $key) ? 'active' : '';
            $url = AdminController::$currentIndex .
                   '&configure=' . $this->name .
                   '&token=' . Tools::getAdminTokenLite('AdminModules') .
                   '&section=' . $key;
            $html .= '<li class="' . $activeClass . '" role="presentation">
                        <a href="' . $url . '" role="tab">
                            ' . $label . '
                        </a>
                      </li>';
        }

        $html .= '</ul>';
        $html .= '<div class="tab-content" style="padding: 20px;">';

        switch ($currentTab) {
            case 'basic':
                $html .= $this->renderBasicSettingsForm();
                break;
            case 'xml':
                $html .= $this->renderXMLFeedForm();
                break;
            case 'knowledge':
                $html .= $this->renderKnowledgeBaseForm();
                break;
            case 'analytics':
                $html .= $this->renderAnalyticsDashboard();
                break;
            default:
                $html .= $this->renderBasicSettingsForm();
        }

        $html .= '</div>'; // tab-content
        $html .= '</div>'; // panel

        return $html;
    }

    private function renderBasicSettingsForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Basic Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Chat Widget Title'),
                        'name' => 'OPTIC_AICHAT_WIDGET_TITLE',
                        'desc' => $this->l('The title displayed at the top of the chat window.'),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('OpenAI API Key'),
                        'name' => 'OPTIC_AICHAT_API_KEY',
                        'desc' => $this->l('Enter your key from platform.openai.com'),
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('System Prompt (AI Instructions)'),
                        'name' => 'OPTIC_AICHAT_SYSTEM_PROMPT',
                        'rows' => 5,
                        'desc' => $this->l('Give instructions to the bot'),
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Page Context'),
                        'name' => 'OPTIC_AICHAT_ENABLE_PAGE_CONTEXT',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                        'desc' => $this->l('Allow AI to read page-specific information')
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Primary Color'),
                        'name' => 'OPTIC_AICHAT_PRIMARY_COLOR',
                        'desc' => $this->l('Main chat color (buttons, header)'),
                        'size' => 20,
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Secondary Color'),
                        'name' => 'OPTIC_AICHAT_SECONDARY_COLOR',
                        'desc' => $this->l('Secondary accent color'),
                        'size' => 20,
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Button Text Color'),
                        'name' => 'OPTIC_AICHAT_BUTTON_TEXT_COLOR',
                        'desc' => $this->l('Text color on buttons'),
                        'size' => 20,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Auto Language Detection'),
                        'name' => 'OPTIC_AICHAT_AUTO_LANGUAGE',
                        'desc' => $this->l('Automatically detect user language and respond accordingly'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Fallback Language'),
                        'name' => 'OPTIC_AICHAT_FALLBACK_LANG',
                        'desc' => $this->l('Default language when auto-detection is off or fails'),
                        'options' => [
                            'query' => [
                                ['id' => 'el', 'name' => 'Ελληνικά (Greek)'],
                                ['id' => 'en', 'name' => 'English'],
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Custom Chat Icon'),
                        'name' => 'OPTIC_AICHAT_CUSTOM_ICON_HTML',
                        'html_content' => $this->renderIconUploadField(),
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Custom Chat Logo'),
                        'name' => 'OPTIC_AICHAT_CUSTOM_LOGO_HTML',
                        'html_content' => $this->renderLogoUploadField(),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Basic Settings'),
                    'name' => 'submitBasicSettings',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBasicSettings';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&section=basic';
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['OPTIC_AICHAT_WIDGET_TITLE'] = Configuration::get('OPTIC_AICHAT_WIDGET_TITLE');
        $helper->fields_value['OPTIC_AICHAT_API_KEY'] = Configuration::get('OPTIC_AICHAT_API_KEY');
        $helper->fields_value['OPTIC_AICHAT_SYSTEM_PROMPT'] = Configuration::get('OPTIC_AICHAT_SYSTEM_PROMPT');
        $helper->fields_value['OPTIC_AICHAT_ENABLE_PAGE_CONTEXT'] = Configuration::get('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        $helper->fields_value['OPTIC_AICHAT_PRIMARY_COLOR'] = Configuration::get('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD';
        $helper->fields_value['OPTIC_AICHAT_SECONDARY_COLOR'] = Configuration::get('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3';
        $helper->fields_value['OPTIC_AICHAT_BUTTON_TEXT_COLOR'] = Configuration::get('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff';
        $helper->fields_value['OPTIC_AICHAT_AUTO_LANGUAGE'] = Configuration::get('OPTIC_AICHAT_AUTO_LANGUAGE');
        $helper->fields_value['OPTIC_AICHAT_FALLBACK_LANG'] = Configuration::get('OPTIC_AICHAT_FALLBACK_LANG') ?: 'el';

        return $helper->generateForm([$fields_form]);
    }

    private function renderXMLFeedForm()
    {
        $html = '';

        // Upload Section
        $html .= '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-upload"></i> ' . $this->l('Step 1: Upload Product XML Feed') . '</div>';
        $html .= '<div class="panel-body">';
        $html .= '<p>' . $this->l('Upload your products XML file. The system will automatically detect available fields.') . '</p>';
        $html .= '<form method="post" enctype="multipart/form-data" action="' . AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&section=xml">';
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Select XML File') . '</label>';
        $html .= '<input type="file" name="OPTIC_AICHAT_PRODUCT_FEED" accept=".xml" required>';
        $html .= '</div>';
        $html .= '<button type="submit" name="uploadXML" class="btn btn-primary">';
        $html .= '<i class="icon-upload"></i> ' . $this->l('Upload & Detect Fields');
        $html .= '</button>';

        // Show current XML status
        $xmlPath = Configuration::get('OPTIC_AICHAT_XML_PATH');
        if ($xmlPath && file_exists($xmlPath)) {
            $productsCount = Configuration::get('OPTIC_AICHAT_PRODUCTS_COUNT') ?: 0;
            $html .= '<div class="alert alert-info" style="margin-top: 15px;">';
            $html .= '<i class="icon-info"></i> ' . $this->l('Current XML:') . ' <strong>products.xml</strong> (' . $productsCount . ' ' . $this->l('products') . ')';
            $html .= '</div>';

            // Delete XML button
            $html .= '<button type="submit" name="deleteXML" class="btn btn-warning" onclick="return confirm(\'' . addslashes($this->l('Delete XML and clear all product data?')) . '\');">';
            $html .= '<i class="icon-trash"></i> ' . $this->l('Delete XML & Clear Cache');
            $html .= '</button>';
        }

        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';

        // Field Mapping Section (only if XML uploaded)
        $availableFields = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELDS'), true);

        if (!empty($availableFields)) {
            $html .= $this->renderFieldMappingForm($availableFields);
        } else {
            $html .= '<div class="alert alert-info">';
            $html .= '<i class="icon-info"></i> ' . $this->l('Upload an XML file to enable field mapping.');
            $html .= '</div>';
        }

        return $html;
    }

    private function renderFieldMappingForm($availableFields)
    {
        $currentMapping = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELD_MAPPING'), true) ?: [];

        $fieldOptions = [['value' => '', 'label' => $this->l('-- Select Field --')]];
        foreach ($availableFields as $field) {
            $fieldOptions[] = ['value' => $field, 'label' => $field];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Step 2: Map XML Fields'),
                    'icon' => 'icon-list',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Product ID') . ' <span style="color:red;">*</span>',
                        'name' => 'XML_FIELD_product_id',
                        'desc' => $this->l('Unique product identifier'),
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Title') . ' <span style="color:red;">*</span>',
                        'name' => 'XML_FIELD_title',
                        'desc' => $this->l('Product name/title'),
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Description'),
                        'name' => 'XML_FIELD_description',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Short Description'),
                        'name' => 'XML_FIELD_short_description',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Category'),
                        'name' => 'XML_FIELD_category',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Price (with discount)') . ' <span style="color:red;">*</span>',
                        'name' => 'XML_FIELD_price_sale',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Price (without discount)'),
                        'name' => 'XML_FIELD_price_regular',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('On Sale'),
                        'name' => 'XML_FIELD_onsale',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Sizes'),
                        'name' => 'XML_FIELD_sizes',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Composition'),
                        'name' => 'XML_FIELD_composition',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Dimensions'),
                        'name' => 'XML_FIELD_dimensions',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('In Stock'),
                        'name' => 'XML_FIELD_instock',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Product URL') . ' <span style="color:red;">*</span>',
                        'name' => 'XML_FIELD_url',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Image URL') . ' <span style="color:red;">*</span>',
                        'name' => 'XML_FIELD_image',
                        'options' => ['query' => $fieldOptions, 'id' => 'value', 'name' => 'label'],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Mapping & Re-Index Products'),
                    'name' => 'submitFieldMapping',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&section=xml';
        $helper->submit_action = 'submitFieldMapping';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');

        // Initialize ALL fields with empty values first
        $allFields = [
            'product_id', 'title', 'description', 'short_description',
            'category', 'price_sale', 'price_regular', 'onsale',
            'sizes', 'composition', 'dimensions', 'instock',
            'url', 'image'
        ];

        foreach ($allFields as $field) {
            $helper->fields_value['XML_FIELD_' . $field] = '';
        }

        // Then override with actual mapping values
        foreach ($currentMapping as $key => $value) {
            $helper->fields_value['XML_FIELD_' . $key] = $value;
        }

        return $helper->generateForm([$fields_form]);
    }

    private function renderKnowledgeBaseForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Knowledge Base Settings'),
                    'icon' => 'icon-book',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include On-Sale Products'),
                        'name' => 'OPTIC_AICHAT_INCLUDE_SALES',
                        'desc' => $this->l('Show current promotions when users ask about sales'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include Active Coupons'),
                        'name' => 'OPTIC_AICHAT_INCLUDE_COUPONS',
                        'desc' => $this->l('Show available discount codes'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include Stock Information'),
                        'name' => 'OPTIC_AICHAT_INCLUDE_STOCK',
                        'desc' => $this->l('Mention low stock warnings'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include Category Structure'),
                        'name' => 'OPTIC_AICHAT_INCLUDE_CATEGORIES',
                        'desc' => $this->l('Share catalog organization'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Include CMS Pages'),
                        'name' => 'OPTIC_AICHAT_INCLUDE_CMS',
                        'desc' => $this->l('Include store information pages'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')]
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Store Policies'),
                        'name' => 'OPTIC_AICHAT_STORE_POLICIES',
                        'rows' => 8,
                        'desc' => $this->l('Shipping, returns, payment information'),
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('FAQ'),
                        'name' => 'OPTIC_AICHAT_FAQ',
                        'rows' => 8,
                        'desc' => $this->l('Frequently asked questions'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Knowledge Base'),
                    'name' => 'submitKnowledgeBase',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKnowledgeBase';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&section=knowledge';
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['OPTIC_AICHAT_INCLUDE_SALES'] = Configuration::get('OPTIC_AICHAT_INCLUDE_SALES');
        $helper->fields_value['OPTIC_AICHAT_INCLUDE_COUPONS'] = Configuration::get('OPTIC_AICHAT_INCLUDE_COUPONS');
        $helper->fields_value['OPTIC_AICHAT_INCLUDE_STOCK'] = Configuration::get('OPTIC_AICHAT_INCLUDE_STOCK');
        $helper->fields_value['OPTIC_AICHAT_INCLUDE_CATEGORIES'] = Configuration::get('OPTIC_AICHAT_INCLUDE_CATEGORIES');
        $helper->fields_value['OPTIC_AICHAT_INCLUDE_CMS'] = Configuration::get('OPTIC_AICHAT_INCLUDE_CMS');
        $helper->fields_value['OPTIC_AICHAT_STORE_POLICIES'] = Configuration::get('OPTIC_AICHAT_STORE_POLICIES');
        $helper->fields_value['OPTIC_AICHAT_FAQ'] = Configuration::get('OPTIC_AICHAT_FAQ');

        return $helper->generateForm([$fields_form]);
    }

    private function renderIconUploadField()
    {
        $currentIcon = Configuration::get('OPTIC_AICHAT_CUSTOM_ICON');
        $actionUrl = htmlspecialchars(AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&section=basic', ENT_QUOTES, 'UTF-8');
        $html = '<form method="post" enctype="multipart/form-data" action="' . $actionUrl . '">';
        
        if ($currentIcon && strpos($currentIcon, '..') === false && file_exists(_PS_MODULE_DIR_ . $this->name . '/' . $currentIcon)) {
            $iconUrl = htmlspecialchars($this->_path . $currentIcon, ENT_QUOTES, 'UTF-8');
            $html .= '<div style="margin-bottom: 10px;">';
            $html .= '<img src="' . $iconUrl . '" style="max-width: 50px; max-height: 50px; border: 1px solid #ccc; padding: 5px;">';
            $html .= '<button type="submit" name="deleteChatIcon" class="btn btn-danger btn-sm" style="margin-left: 10px;">';
            $html .= '<i class="icon-trash"></i> ' . $this->l('Delete');
            $html .= '</button></div>';
        }
        
        $html .= '<input type="file" name="OPTIC_AICHAT_CUSTOM_ICON" accept=".png,.svg,.jpg,.jpeg">';
        $html .= '<button type="submit" name="uploadChatIcon" class="btn btn-primary btn-sm" style="margin-top: 5px;">';
        $html .= '<i class="icon-upload"></i> ' . $this->l('Upload Icon');
        $html .= '</button>';
        $html .= '<p class="help-block">' . $this->l('PNG, SVG, or JPG. Max 2MB. Recommended: 64x64px') . '</p>';
        $html .= '</form>';
        
        return $html;
    }

    private function renderLogoUploadField()
    {
        $currentLogo = Configuration::get('OPTIC_AICHAT_CUSTOM_LOGO');
        $actionUrl = htmlspecialchars(AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&section=basic', ENT_QUOTES, 'UTF-8');
        $html = '<form method="post" enctype="multipart/form-data" action="' . $actionUrl . '">';
        
        if ($currentLogo && strpos($currentLogo, '..') === false && file_exists(_PS_MODULE_DIR_ . $this->name . '/' . $currentLogo)) {
            $logoUrl = htmlspecialchars($this->_path . $currentLogo, ENT_QUOTES, 'UTF-8');
            $html .= '<div style="margin-bottom: 10px;">';
            $html .= '<img src="' . $logoUrl . '" style="max-width: 200px; max-height: 60px; border: 1px solid #ccc; padding: 5px;">';
            $html .= '<button type="submit" name="deleteChatLogo" class="btn btn-danger btn-sm" style="margin-left: 10px;">';
            $html .= '<i class="icon-trash"></i> ' . $this->l('Delete');
            $html .= '</button></div>';
        }
        
        $html .= '<input type="file" name="OPTIC_AICHAT_CUSTOM_LOGO" accept=".png,.svg,.jpg,.jpeg">';
        $html .= '<button type="submit" name="uploadChatLogo" class="btn btn-primary btn-sm" style="margin-top: 5px;">';
        $html .= '<i class="icon-upload"></i> ' . $this->l('Upload Logo');
        $html .= '</button>';
        $html .= '<p class="help-block">' . $this->l('PNG, SVG, or JPG. Max 2MB. Recommended: 200x60px') . '</p>';
        $html .= '</form>';
        
        return $html;
    }
}