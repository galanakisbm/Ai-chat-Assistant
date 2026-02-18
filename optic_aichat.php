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
        
        // Ορισμός default ρυθμίσεων κατά την εγκατάσταση
        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayFooter') &&
            Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', 'OpticWeb Assistant') &&
            Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', 'Είσαι ένας ευγενικός βοηθός για το κατάστημά μας. Απάντησε σύντομα και στα Ελληνικά.') &&
            Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', 1) &&
            Configuration::updateValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE', '');
    }

    public function uninstall()
    {
        // Καθαρισμός ρυθμίσεων κατά την απεγκατάσταση
        Configuration::deleteByName('OPTIC_AICHAT_API_KEY');
        Configuration::deleteByName('OPTIC_AICHAT_SYSTEM_PROMPT');
        Configuration::deleteByName('OPTIC_AICHAT_WIDGET_TITLE');
        Configuration::deleteByName('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT');
        Configuration::deleteByName('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE');
        
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

            if (!$apiKey || empty($apiKey)) {
                $output .= $this->displayError($this->l('Παρακαλώ εισάγετε το API Key.'));
            } else {
                Configuration::updateValue('OPTIC_AICHAT_API_KEY', $apiKey);
                Configuration::updateValue('OPTIC_AICHAT_SYSTEM_PROMPT', $prompt);
                Configuration::updateValue('OPTIC_AICHAT_WIDGET_TITLE', $title);
                Configuration::updateValue('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT', (int)$enablePageContext);
                Configuration::updateValue('OPTIC_AICHAT_PAGE_CONTEXT_TEMPLATE', $pageContextTemplate);
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

        return $helper->generateForm([$fields_form]);
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/chat.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/chat.js');

        Media::addJsDef([
            'optic_chat_ajax_url' => $this->context->link->getModuleLink('optic_aichat', 'ajax')
        ]);
    }

    public function hookDisplayFooter()
    {
        // Περνάμε τον δυναμικό τίτλο στο TPL
        $this->context->smarty->assign([
            'chat_title' => Configuration::get('OPTIC_AICHAT_WIDGET_TITLE', 'OpticWeb Assistant')
        ]);
        
        return $this->display(__FILE__, 'views/templates/hook/chat_widget.tpl');
    }
}