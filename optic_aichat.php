<?php
/**
 * OpticWeb AI Chat - Main Module File
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Optic_AiChat extends Module
{
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
        
        // Δημιουργία φακέλου για uploads
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Install default field mappings
        $defaultMappings = json_encode([
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
        ]);
        
        // Ορισμός default ρυθμίσεων κατά την εγκατάσταση
        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayFooter') &&
            Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', 'OpticWeb Assistant') &&
            Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', 'Είσαι ένας ευγενικός βοηθός για το κατάστημά μας. Απάντησε σύντομα και στα Ελληνικά.') &&
            Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', 1) &&
            Configuration::updateValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE', '') &&
            Configuration::updateValue('OPTIC_AICHAT_PRIMARY_COLOR', '#268CCD') &&
            Configuration::updateValue('OPTIC_AICHAT_SECONDARY_COLOR', '#1a6ba3') &&
            Configuration::updateValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR', '#ffffff') &&
            Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', $defaultMappings);
    }

    public function uninstall()
    {
        // Καθαρισμός ρυθμίσεων κατά την απεγκατάσταση
        Configuration::deleteByName('OPTIC_AICHAT_API_KEY');
        Configuration::deleteByName('OPTIC_AICHAT_SYSTEM_PROMPT');
        Configuration::deleteByName('OPTIC_AICHAT_WIDGET_TITLE');
        Configuration::deleteByName('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        Configuration::deleteByName('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
        Configuration::deleteByName('OPTIC_AICHAT_PRIMARY_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_SECONDARY_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_BUTTON_TEXT_COLOR');
        Configuration::deleteByName('OPTIC_AICHAT_XML_PATH');
        Configuration::deleteByName('OPTIC_AICHAT_PRODUCTS_INDEXED');
        Configuration::deleteByName('OPTIC_AICHAT_XML_FIELD_MAPPING');
        
        // Διαγραφή πίνακα chat logs
        $sql = "DROP TABLE IF EXISTS `"._DB_PREFIX_."optic_aichat_logs`";
        Db::getInstance()->execute($sql);
        
        return parent::uninstall();
    }

    /**
     * Εμφάνιση της σελίδας ρυθμίσεων στο Back Office
     */
    public function getContent()
    {
        $output = '';

        // Έλεγχος αν πατήθηκε το Save
        if (Tools::isSubmit('submitOpticAiChat')) {
            $apiKey = Tools::getValue('OPTIC_AICHAT_API_KEY');
            $prompt = Tools::getValue('OPTIC_AICHAT_SYSTEM_PROMPT');
            $title = Tools::getValue('OPTIC_AICHAT_WIDGET_TITLE');
            $enablePageContext = Tools::getValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
            $pageContextTemplate = Tools::getValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
            
            // NEW: Color values
            $primaryColor = Tools::getValue('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD';
            $secondaryColor = Tools::getValue('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3';
            $buttonTextColor = Tools::getValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff';

            if (!$apiKey || empty($apiKey)) {
                $output .= $this->displayError($this->l('Παρακαλώ εισάγετε το API Key.'));
            } else {
                Configuration::updateValue('OPTIC_AICHAT_API_KEY', $apiKey);
                Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', $prompt);
                Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', $title);
                Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', (int)$enablePageContext);
                Configuration::updateValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE', $pageContextTemplate);
                
                // Save colors
                Configuration::updateValue('OPTIC_AICHAT_PRIMARY_COLOR', $primaryColor);
                Configuration::updateValue('OPTIC_AICHAT_SECONDARY_COLOR', $secondaryColor);
                Configuration::updateValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR', $buttonTextColor);

                // Save field mappings
                $fieldMappings = [
                    'product_id' => Tools::getValue('XML_FIELD_product_id') ?: 'id',
                    'title' => Tools::getValue('XML_FIELD_title') ?: 'name',
                    'description' => Tools::getValue('XML_FIELD_description') ?: 'description',
                    'short_description' => Tools::getValue('XML_FIELD_short_description') ?: 'short_description',
                    'category' => Tools::getValue('XML_FIELD_category') ?: 'category',
                    'price_sale' => Tools::getValue('XML_FIELD_price_sale') ?: 'price',
                    'price_regular' => Tools::getValue('XML_FIELD_price_regular') ?: 'regular_price',
                    'onsale' => Tools::getValue('XML_FIELD_onsale') ?: 'onsale',
                    'sizes' => Tools::getValue('XML_FIELD_sizes') ?: 'size',
                    'composition' => Tools::getValue('XML_FIELD_composition') ?: 'composition',
                    'dimensions' => Tools::getValue('XML_FIELD_dimensions') ?: 'dimension',
                    'instock' => Tools::getValue('XML_FIELD_instock') ?: 'instock',
                    'url' => Tools::getValue('XML_FIELD_url') ?: 'url',
                    'image' => Tools::getValue('XML_FIELD_image') ?: 'image',
                ];

                Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', json_encode($fieldMappings));

                // Handle XML upload
                if (isset($_FILES['OPTIC_AICHAT_PRODUCT_FEED']) && $_FILES['OPTIC_AICHAT_PRODUCT_FEED']['size'] > 0) {
                    $uploadResult = $this->handleXMLUpload($_FILES['OPTIC_AICHAT_PRODUCT_FEED']);
                    if ($uploadResult) {
                        $productsIndexed = Configuration::get('OPTIC_AICHAT_PRODUCTS_INDEXED');
                        $output .= $this->displayConfirmation(
                            $this->l('XML uploaded and indexed successfully!') . ' ' . 
                            $productsIndexed . ' ' . $this->l('products found.')
                        );
                    } else {
                        $output .= $this->displayError($this->l('Failed to upload XML file. Please check file format.'));
                    }
                }
                
                $output .= $this->displayConfirmation($this->l('Οι ρυθμίσεις αποθηκεύτηκαν.'));
            }
        }

        return $output . $this->renderForm();
    }

    /**
     * Δημιουργία της φόρμας με HelperForm (Standard PrestaShop UI)
     */
    public function renderForm()
    {
        // Main settings form
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Ρυθμίσεις AI Chat'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Chat Widget Title'),
                        'name' => 'OPTIC_AICHAT_WIDGET_TITLE',
                        'desc' => $this->l('Ο τίτλος που εμφανίζεται στην κορυφή του chat window.'),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('OpenAI API Key'),
                        'name' => 'OPTIC_AICHAT_API_KEY',
                        'desc' => $this->l('Εισάγετε το κλειδί από το platform.openai.com'),
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('System Prompt (Οδηγίες AI)'),
                        'name' => 'OPTIC_AICHAT_SYSTEM_PROMPT',
                        'rows' => 5,
                        'desc' => $this->l('Δώσε οδηγίες στο bot (π.χ. "Είμαστε το κατάστημα Ρούχων Χ, έχουμε δωρεάν μεταφορικά άνω των 50€").'),
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
                        'type' => 'textarea',
                        'label' => $this->l('Page Context Template'),
                        'name' => 'OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE',
                        'rows' => 8,
                        'desc' => $this->l('Template for page information. Available variables: {PAGE_TITLE}, {PAGE_URL}, {PAGE_TYPE}, {PRODUCT_NAME}, {PRODUCT_PRICE}, {CATEGORY_NAME}'),
                        'required' => false
                    ],
                    // NEW: Color Customization
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
                        'desc' => $this->l('Text color on buttons (white/black)'),
                        'size' => 20,
                    ],
                    // NEW: XML Product Feed
                    [
                        'type' => 'file',
                        'label' => $this->l('Product Feed (XML)'),
                        'name' => 'OPTIC_AICHAT_PRODUCT_FEED',
                        'desc' => $this->l('Upload XML file with products for faster search (optional)'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Αποθήκευση'),
                ],
            ],
        ];

        // XML Field Mapping Form
        $mapping_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('XML Field Mapping'),
                    'icon' => 'icon-list',
                ],
                'description' => $this->l('Map your XML fields to module fields. This allows you to use any XML format.'),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Product ID'),
                        'name' => 'XML_FIELD_product_id',
                        'desc' => $this->l('XML tag for product ID'),
                        'placeholder' => 'id',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'XML_FIELD_title',
                        'desc' => $this->l('XML tag for product title'),
                        'placeholder' => 'name',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Description'),
                        'name' => 'XML_FIELD_description',
                        'desc' => $this->l('XML tag for full description'),
                        'placeholder' => 'description',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Short Description'),
                        'name' => 'XML_FIELD_short_description',
                        'desc' => $this->l('XML tag for short description'),
                        'placeholder' => 'short_description',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Main Category'),
                        'name' => 'XML_FIELD_category',
                        'desc' => $this->l('XML tag for category'),
                        'placeholder' => 'category',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Price (with discount)'),
                        'name' => 'XML_FIELD_price_sale',
                        'desc' => $this->l('XML tag for sale price'),
                        'placeholder' => 'price',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Price (without discount)'),
                        'name' => 'XML_FIELD_price_regular',
                        'desc' => $this->l('XML tag for regular price'),
                        'placeholder' => 'regular_price',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('On Sale (1/0 or Y/N)'),
                        'name' => 'XML_FIELD_onsale',
                        'desc' => $this->l('XML tag for on-sale status'),
                        'placeholder' => 'onsale',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Sizes (comma-separated)'),
                        'name' => 'XML_FIELD_sizes',
                        'desc' => $this->l('XML tag for sizes'),
                        'placeholder' => 'size',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Composition'),
                        'name' => 'XML_FIELD_composition',
                        'desc' => $this->l('XML tag for composition/material'),
                        'placeholder' => 'composition',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Dimensions (comma-separated)'),
                        'name' => 'XML_FIELD_dimensions',
                        'desc' => $this->l('XML tag for dimensions'),
                        'placeholder' => 'dimension',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('In Stock (Y/N or 1/0)'),
                        'name' => 'XML_FIELD_instock',
                        'desc' => $this->l('XML tag for stock status'),
                        'placeholder' => 'instock',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Product URL'),
                        'name' => 'XML_FIELD_url',
                        'desc' => $this->l('XML tag for product URL'),
                        'placeholder' => 'url',
                        'size' => 30,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Image URL'),
                        'name' => 'XML_FIELD_image',
                        'desc' => $this->l('XML tag for product image'),
                        'placeholder' => 'image',
                        'size' => 30,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Αποθήκευση'),
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
        $helper->submit_action = 'submitOpticAiChat';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        // Φόρτωση αποθηκευμένων τιμών για main form
        $helper->fields_value['OPTIC_AICHAT_WIDGET_TITLE'] = Configuration::get('OPTIC_AICHAT_WIDGET_TITLE');
        $helper->fields_value['OPTIC_AICHAT_API_KEY'] = Configuration::get('OPTIC_AICHAT_API_KEY');
        $helper->fields_value['OPTIC_AICHAT_SYSTEM_PROMPT'] = Configuration::get('OPTIC_AICHAT_SYSTEM_PROMPT');
        $helper->fields_value['OPTIC_AICHAT_ENABLE_PAGE_CONTEXT'] = Configuration::get('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        $helper->fields_value['OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE'] = Configuration::get('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
        $helper->fields_value['OPTIC_AICHAT_PRIMARY_COLOR'] = Configuration::get('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD';
        $helper->fields_value['OPTIC_AICHAT_SECONDARY_COLOR'] = Configuration::get('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3';
        $helper->fields_value['OPTIC_AICHAT_BUTTON_TEXT_COLOR'] = Configuration::get('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff';

        // Load field mappings
        $mappings = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELD_MAPPING'), true);
        if ($mappings) {
            foreach ($mappings as $key => $value) {
                $helper->fields_value['XML_FIELD_' . $key] = $value;
            }
        }

        return $helper->generateForm([$fields_form, $mapping_form]);
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
            'optic_chat_ajax_url' => $this->context->link->getModuleLink('optic_aichat', 'ajax')
        ]);
    }

    public function hookDisplayFooter()
    {
        $shop = $this->context->shop;
        
        // Get shop logo properly
        $logoPath = Configuration::get('PS_LOGO');
        $shopLogo = $this->context->link->getMediaLink(_PS_IMG_DIR_ . $logoPath);
        
        $this->context->smarty->assign([
            'chat_title' => Configuration::get('OPTIC_AICHAT_WIDGET_TITLE') ?: 'AI Assistant',
            'shop_logo' => $shopLogo,
            'shop_name' => $shop->name,
            'optic_custom_css' => isset($this->context->smarty->tpl_vars['optic_custom_css']) 
                ? $this->context->smarty->tpl_vars['optic_custom_css']->value 
                : '',
        ]);
        
        return $this->display(__FILE__, 'views/templates/hook/chat_widget.tpl');
    }

    /**
     * Handle XML file upload
     */
    private function handleXMLUpload($file)
    {
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($file['type'] === 'text/xml' || $file['type'] === 'application/xml') {
            $targetFile = $uploadDir . 'products.xml';
            
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                Configuration::updateValue('OPTIC_AICHAT_XML_PATH', $targetFile);
                
                // Parse and cache the XML
                $this->indexXMLProducts($targetFile);
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Index XML products to JSON cache
     */
    private function indexXMLProducts($xmlPath)
    {
        // Prevent XXE attacks
        $previousValue = libxml_disable_entity_loader(true);
        
        try {
            $xml = simplexml_load_file($xmlPath, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            if ($xml === false) {
                libxml_disable_entity_loader($previousValue);
                return false;
            }
            
            // Get field mappings
            $mappings = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELD_MAPPING'), true);
            if (!$mappings) {
                // Use defaults
                $mappings = [
                    'product_id' => 'id',
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
                ];
            }
            
            $products = [];
            
            foreach ($xml->product as $xmlProduct) {
                $product = [];
                
                // Map each field dynamically
                foreach ($mappings as $moduleField => $xmlTag) {
                    if (isset($xmlProduct->$xmlTag)) {
                        $product[$moduleField] = (string)$xmlProduct->$xmlTag;
                    } else {
                        $product[$moduleField] = '';
                    }
                }
                
                // Add to products array
                $products[] = $product;
            }
            
            // Cache as JSON for faster access
            $uploadDir = _PS_MODULE_DIR_ . $this->name . '/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $cacheFile = $uploadDir . 'products_cache.json';
            $result = file_put_contents($cacheFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if ($result === false) {
                error_log('OpticAiChat: Failed to write products cache file');
                libxml_disable_entity_loader($previousValue);
                return false;
            }
            
            Configuration::updateValue('OPTIC_AICHAT_PRODUCTS_INDEXED', count($products));
            
            libxml_disable_entity_loader($previousValue);
            return true;
        } catch (Exception $e) {
            error_log('OpticAiChat: Error indexing XML products - ' . $e->getMessage());
            libxml_disable_entity_loader($previousValue);
            return false;
        }
    }
}