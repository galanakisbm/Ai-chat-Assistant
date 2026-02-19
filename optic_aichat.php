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
            Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', $defaultMappings);
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

        // Step 1: Handle XML Upload First
        if (Tools::isSubmit('uploadXML')) {
            if (isset($_FILES['OPTIC_AICHAT_PRODUCT_FEED']) && $_FILES['OPTIC_AICHAT_PRODUCT_FEED']['size'] > 0) {
                $uploadResult = $this->handleXMLUpload($_FILES['OPTIC_AICHAT_PRODUCT_FEED']);
                
                if ($uploadResult['success']) {
                    // Auto-suggest mappings
                    $autoSuggestions = $this->autoSuggestMapping($uploadResult['available_fields']);
                    
                    // Save auto-suggestions as initial mapping
                    Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', json_encode($autoSuggestions));
                    
                    // Show success with details
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

        // Step 2: Handle Settings and Field Mapping Save
        if (Tools::isSubmit('submitOpticAiChat')) {
            $apiKey = Tools::getValue('OPTIC_AICHAT_API_KEY');
            $prompt = Tools::getValue('OPTIC_AICHAT_SYSTEM_PROMPT');
            $title = Tools::getValue('OPTIC_AICHAT_WIDGET_TITLE');
            $enablePageContext = Tools::getValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
            $pageContextTemplate = Tools::getValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
            
            // Color values
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

                // Remove empty mappings
                $fieldMappings = array_filter($fieldMappings);

                // Validate required mappings
                $requiredFields = ['product_id', 'title', 'price_sale', 'url', 'image'];
                $missingFields = [];

                foreach ($requiredFields as $required) {
                    if (empty($fieldMappings[$required])) {
                        $missingFields[] = $required;
                    }
                }

                if (!empty($missingFields)) {
                    $output .= $this->displayWarning(
                        $this->l('Warning: Required fields not mapped:') . ' ' . 
                        implode(', ', $missingFields)
                    );
                }

                Configuration::updateValue('OPTIC_AICHAT_XML_FIELD_MAPPING', json_encode($fieldMappings));

                // Re-index products with new mapping
                $xmlPath = Configuration::get('OPTIC_AICHAT_XML_PATH');
                if ($xmlPath && file_exists($xmlPath)) {
                    $this->indexXMLProducts($xmlPath);
                    $productsIndexed = Configuration::get('OPTIC_AICHAT_PRODUCTS_INDEXED');
                    $output .= $this->displayConfirmation(
                        $this->l('Settings saved!') . '<br>' .
                        $this->l('Products indexed:') . ' ' . $productsIndexed
                    );
                } else {
                    $output .= $this->displayConfirmation($this->l('Οι ρυθμίσεις αποθηκεύτηκαν.'));
                }
            }
        }

        return $output . $this->renderForm();
    }

    /**
     * Δημιουργία της φόρμας με HelperForm (Standard PrestaShop UI)
     */
    public function renderForm()
    {
        // Get available XML fields (if XML uploaded)
        $availableFields = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELDS'), true) ?: [];
        $sampleProduct = json_decode(Configuration::get('OPTIC_AICHAT_XML_SAMPLE'), true) ?: [];
        $currentMapping = json_decode(Configuration::get('OPTIC_AICHAT_XML_FIELD_MAPPING'), true) ?: [];

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
        if (!empty($currentMapping)) {
            foreach ($currentMapping as $key => $value) {
                $helper->fields_value['XML_FIELD_' . $key] = $value;
            }
        }

        // XML Upload Form (Separate)
        $xml_upload_html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-upload"></i> ' . $this->l('Step 1: Upload Product XML Feed') . '
            </div>
            <div class="panel-body">
                <p>' . $this->l('Upload your products XML file. The system will automatically detect available fields.') . '</p>
                <form method="post" enctype="multipart/form-data" action="' . AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules') . '">
                    <div class="form-group">
                        <label>' . $this->l('Select XML File') . '</label>
                        <input type="file" name="OPTIC_AICHAT_PRODUCT_FEED" accept=".xml" required>
                    </div>
                    <button type="submit" name="uploadXML" class="btn btn-primary">
                        <i class="icon-upload"></i> ' . $this->l('Upload & Detect Fields') . '
                    </button>
                </form>
            </div>
        </div>';

        // Preview panel
        $preview_html = '';
        if (!empty($sampleProduct)) {
            $preview_html = '
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-eye"></i> ' . $this->l('Preview: First Product') . '
                </div>
                <div class="panel-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>' . $this->l('XML Tag') . '</th>
                                <th>' . $this->l('Value') . '</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($sampleProduct as $tag => $value) {
                $preview_html .= '
                            <tr>
                                <td><code>&lt;' . htmlspecialchars($tag) . '&gt;</code></td>
                                <td>' . htmlspecialchars(mb_substr($value, 0, 100)) . (mb_strlen($value) > 100 ? '...' : '') . '</td>
                            </tr>';
            }
            
            $preview_html .= '
                        </tbody>
                    </table>
                </div>
            </div>';
        }

        // Field Mapping Form (only show if XML uploaded)
        $mapping_form_html = '';
        
        if (!empty($availableFields)) {
            // Create dropdown options
            $fieldOptions = [['value' => '', 'label' => $this->l('-- Select Field --')]];
            foreach ($availableFields as $field) {
                $fieldOptions[] = [
                    'value' => $field,
                    'label' => $field
                ];
            }

            $mapping_form = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Step 2: Map XML Fields'),
                        'icon' => 'icon-list',
                    ],
                    'description' => sprintf(
                        $this->l('Found %d products with %d available fields. Map your XML tags to module fields below.'),
                        (int)Configuration::get('OPTIC_AICHAT_PRODUCTS_COUNT'),
                        count($availableFields)
                    ),
                    'input' => [
                        [
                            'type' => 'select',
                            'label' => $this->l('Product ID') . ' <span style="color:red;">*</span>',
                            'name' => 'XML_FIELD_product_id',
                            'desc' => $this->l('Unique product identifier'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Title') . ' <span style="color:red;">*</span>',
                            'name' => 'XML_FIELD_title',
                            'desc' => $this->l('Product name/title'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Description'),
                            'name' => 'XML_FIELD_description',
                            'desc' => $this->l('Full product description'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Short Description'),
                            'name' => 'XML_FIELD_short_description',
                            'desc' => $this->l('Brief product description'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Category'),
                            'name' => 'XML_FIELD_category',
                            'desc' => $this->l('Product category'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Price (with discount)') . ' <span style="color:red;">*</span>',
                            'name' => 'XML_FIELD_price_sale',
                            'desc' => $this->l('Current selling price'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Price (without discount)'),
                            'name' => 'XML_FIELD_price_regular',
                            'desc' => $this->l('Original/regular price'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('On Sale (1/0 or Y/N)'),
                            'name' => 'XML_FIELD_onsale',
                            'desc' => $this->l('Is product on sale?'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Sizes (comma-separated)'),
                            'name' => 'XML_FIELD_sizes',
                            'desc' => $this->l('Available sizes (S,M,L,XL)'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Composition/Material'),
                            'name' => 'XML_FIELD_composition',
                            'desc' => $this->l('Product material/composition'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Dimensions'),
                            'name' => 'XML_FIELD_dimensions',
                            'desc' => $this->l('Product dimensions'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('In Stock (Y/N or 1/0)'),
                            'name' => 'XML_FIELD_instock',
                            'desc' => $this->l('Stock availability'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Product URL') . ' <span style="color:red;">*</span>',
                            'name' => 'XML_FIELD_url',
                            'desc' => $this->l('Link to product page'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Image URL') . ' <span style="color:red;">*</span>',
                            'name' => 'XML_FIELD_image',
                            'desc' => $this->l('Product image URL'),
                            'options' => [
                                'query' => $fieldOptions,
                                'id' => 'value',
                                'name' => 'label'
                            ],
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save Mapping & Index Products'),
                    ],
                ],
            ];

            $mappingForm = $helper->generateForm([$mapping_form]);
        }

        $mainForm = $helper->generateForm([$fields_form]);
        
        if (!empty($availableFields)) {
            return $mainForm . $xml_upload_html . $preview_html . $mappingForm;
        } else {
            return $mainForm . $xml_upload_html . '
            <div class="alert alert-info">
                <i class="icon-info"></i> ' . $this->l('Upload an XML file to enable field mapping.') . '
            </div>';
        }
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
            'optic_chat_welcome_message' => Configuration::get('OPTIC_AICHAT_WELCOME_MESSAGE') ?: 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊'
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
            'welcome_message' => Configuration::get('OPTIC_AICHAT_WELCOME_MESSAGE') ?: 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊',
            'shop_logo' => $shopLogo,
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
}