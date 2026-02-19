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
            Configuration::updateValue('OPTIC_AICHAT_BUTTON_TEXT_COLOR', '#ffffff');
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

                // Handle XML upload
                if (isset($_FILES['OPTIC_AICHAT_PRODUCT_FEED']) && $_FILES['OPTIC_AICHAT_PRODUCT_FEED']['size'] > 0) {
                    $uploadResult = $this->handleXMLUpload($_FILES['OPTIC_AICHAT_PRODUCT_FEED']);
                    if ($uploadResult) {
                        $output .= $this->displayConfirmation($this->l('XML file uploaded and indexed successfully.'));
                    } else {
                        $output .= $this->displayError($this->l('Failed to upload XML file. Please ensure it is a valid XML file.'));
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

        // Φόρτωση αποθηκευμένων τιμών
        $helper->fields_value['OPTIC_AICHAT_WIDGET_TITLE'] = Configuration::get('OPTIC_AICHAT_WIDGET_TITLE');
        $helper->fields_value['OPTIC_AICHAT_API_KEY'] = Configuration::get('OPTIC_AICHAT_API_KEY');
        $helper->fields_value['OPTIC_AICHAT_SYSTEM_PROMPT'] = Configuration::get('OPTIC_AICHAT_SYSTEM_PROMPT');
        $helper->fields_value['OPTIC_AICHAT_ENABLE_PAGE_CONTEXT'] = Configuration::get('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        $helper->fields_value['OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE'] = Configuration::get('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
        $helper->fields_value['OPTIC_AICHAT_PRIMARY_COLOR'] = Configuration::get('OPTIC_AICHAT_PRIMARY_COLOR') ?: '#268CCD';
        $helper->fields_value['OPTIC_AICHAT_SECONDARY_COLOR'] = Configuration::get('OPTIC_AICHAT_SECONDARY_COLOR') ?: '#1a6ba3';
        $helper->fields_value['OPTIC_AICHAT_BUTTON_TEXT_COLOR'] = Configuration::get('OPTIC_AICHAT_BUTTON_TEXT_COLOR') ?: '#ffffff';

        return $helper->generateForm([$fields_form]);
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
        // Περνάμε τον δυναμικό τίτλο στο TPL
        $this->context->smarty->assign([
            'chat_title' => Configuration::get('OPTIC_AICHAT_WIDGET_TITLE', 'OpticWeb Assistant'),
            'shop' => $this->context->shop,
            'optic_custom_css' => $this->context->smarty->getTemplateVars('optic_custom_css'),
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
            
            $products = [];
            
            foreach ($xml->product as $product) {
                $products[] = [
                    'id' => (string)$product->id,
                    'name' => (string)$product->name,
                    'price' => (string)$product->price,
                    'image' => (string)$product->image,
                    'url' => (string)$product->url,
                    'description' => (string)$product->description,
                    'availability' => (string)$product->availability,
                    'categories' => (string)$product->categories,
                ];
            }
            
            // Cache as JSON for faster access
            $cacheFile = _PS_MODULE_DIR_ . $this->name . '/uploads/products_cache.json';
            $result = file_put_contents($cacheFile, json_encode($products));
            
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