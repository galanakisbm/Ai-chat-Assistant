<?php
/**
 * OpticWeb AI Chat - Admin Dashboard Controller
 * Displays chat history, analytics, and statistics
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminOpticAiChatController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'optic_aichat_logs';
        $this->className = 'OpticAiChatLog';
        $this->lang = false;
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();

        parent::__construct();

        $this->fields_list = [
            'id_chat_log' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'id_customer' => [
                'title' => $this->l('Customer ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'message' => [
                'title' => $this->l('Message'),
                'maxlength' => 100,
                'orderby' => false
            ],
            'response' => [
                'title' => $this->l('Response'),
                'maxlength' => 100,
                'orderby' => false
            ],
            'page_url' => [
                'title' => $this->l('Page URL'),
                'maxlength' => 50,
                'orderby' => false
            ],
            'session_id' => [
                'title' => $this->l('Session'),
                'align' => 'center',
                'class' => 'fixed-width-sm'
            ],
            'response_time' => [
                'title' => $this->l('Response Time (s)'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ],
            'date_add' => [
                'title' => $this->l('Date'),
                'type' => 'datetime',
                'align' => 'center',
                'class' => 'fixed-width-md'
            ]
        ];
    }

    public function renderList()
    {
        // Add statistics panel before the list
        $stats = $this->getStatistics();
        
        $this->context->smarty->assign([
            'total_conversations' => $stats['total_conversations'],
            'total_messages' => $stats['total_messages'],
            'avg_response_time' => $stats['avg_response_time'],
            'today_messages' => $stats['today_messages']
        ]);

        $statsHtml = '<div class="panel">
            <h3><i class="icon-bar-chart"></i> ' . $this->l('Analytics & Statistics') . '</h3>
            <div class="row">
                <div class="col-lg-3">
                    <div class="panel">
                        <div class="panel-body text-center">
                            <h4>' . $stats['total_conversations'] . '</h4>
                            <p>' . $this->l('Total Conversations') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel">
                        <div class="panel-body text-center">
                            <h4>' . $stats['total_messages'] . '</h4>
                            <p>' . $this->l('Total Messages') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel">
                        <div class="panel-body text-center">
                            <h4>' . number_format($stats['avg_response_time'], 2) . 's</h4>
                            <p>' . $this->l('Avg Response Time') . '</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="panel">
                        <div class="panel-body text-center">
                            <h4>' . $stats['today_messages'] . '</h4>
                            <p>' . $this->l('Today\'s Messages') . '</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $statsHtml . parent::renderList();
    }

    /**
     * Get statistics from database
     */
    private function getStatistics()
    {
        $sql = 'SELECT 
                    COUNT(DISTINCT session_id) as total_conversations,
                    COUNT(*) as total_messages,
                    AVG(response_time) as avg_response_time,
                    SUM(CASE WHEN DATE(date_add) = CURDATE() THEN 1 ELSE 0 END) as today_messages
                FROM `' . _DB_PREFIX_ . 'optic_aichat_logs`';
        
        $result = Db::getInstance()->getRow($sql);
        
        return [
            'total_conversations' => $result['total_conversations'] ?: 0,
            'total_messages' => $result['total_messages'] ?: 0,
            'avg_response_time' => $result['avg_response_time'] ?: 0,
            'today_messages' => $result['today_messages'] ?: 0
        ];
    }

    /**
     * Set page title and toolbar title
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->l('AI Chat History');
    }
}
