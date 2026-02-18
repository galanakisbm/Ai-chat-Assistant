<?php
/**
 * OpticWeb AI Chat - Intelligent AJAX Controller
 * KNOWLEDGE BASE VERSION: Products, Variants, Features & CMS Pages (Policies)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Optic_AiChatAjaxModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();

        $userMessage = Tools::getValue('message');
        $historyJson = Tools::getValue('history'); 
        $apiKey = Configuration::get('OPTIC_AICHAT_API_KEY');
        
        if (empty($apiKey)) {
            $this->returnJson(['status' => 'error', 'reply' => 'Configuration Error: API Key missing.']);
        }

        if (empty($userMessage)) {
            $this->returnJson(['status' => 'error', 'reply' => 'Empty message.']);
        }

        $response = $this->handleOpenAIConversation($userMessage, $historyJson, $apiKey);

        $this->returnJson([
            'status' => 'success',
            'reply' => $response
        ]);
    }

    private function returnJson($data)
    {
        header('Content-Type: application/json');
        die(json_encode($data));
    }

    private function handleOpenAIConversation($userMessage, $historyJson, $apiKey)
    {
        $history = json_decode(html_entity_decode($historyJson), true);
        $id_lang = Context::getContext()->language->id;

        // Ορισμός εργαλείων (Tools)
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search products. Use broad keywords in English. Combine with context (e.g. "T-Shirt" + "White").',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'The keywords to search for']
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_cms_page_content',
                    'description' => 'Get the content of a store policy page (Shipping, Returns, About Us, Terms). Use this when user asks about store rules or info.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'cms_id' => ['type' => 'integer', 'description' => 'The ID of the CMS page to read']
                        ],
                        'required' => ['cms_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_orders',
                    'description' => 'Get recent orders.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_active_offers',
                    'description' => 'Get active vouchers.',
                    'parameters' => ['type' => 'object', 'properties' => (object)[]]
                ]
            ]
        ];

        // System Prompt - Εδώ δίνουμε στο AI τη λίστα με τις CMS σελίδες για να ξέρει ποια να ζητήσει
        $cmsPages = CMS::getCMSPages($id_lang);
        $cmsList = "";
        foreach ($cmsPages as $cp) {
            $cmsList .= "ID: " . $cp['id_cms'] . " - Title: " . $cp['meta_title'] . "\n";
        }

        $basePrompt = Configuration::get('OPTIC_AICHAT_SYSTEM_PROMPT') ?: "You are a helpful shopping assistant.";
        
        $systemInstruction = $basePrompt . 
        " You MUST answer in Greek (Ελληνικά). " .
        " You have access to store information through CMS pages. Available CMS pages:\n" . $cmsList .
        " If the user asks about shipping, returns, or store info, use 'get_cms_page_content' with the correct ID. " .
        " When listing products, use the specified HTML structure with images.";

        // Build History Context
        $messages = [['role' => 'system', 'content' => $systemInstruction]];
        if (!empty($history) && is_array($history)) {
            foreach ($history as $msg) {
                $role = (strpos($msg['class'], 'user-message') !== false) ? 'user' : 'assistant';
                $messages[] = ['role' => $role, 'content' => strip_tags($msg['text'])];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Step 1: OpenAI Call
        $result = $this->callOpenAI($messages, $tools, $apiKey);
        if (isset($result['error'])) return "OpenAI Error: " . $result['error']['message'];
        
        $message = $result['choices'][0]['message'];

        // Step 2: Handle Tool Calls
        if (isset($message['tool_calls'])) {
            $messages[] = $message;

            foreach ($message['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'], true);
                $toolOutput = "";

                if ($functionName === 'search_products') {
                    $toolOutput = json_encode($this->searchProducts($args['query']));
                } elseif ($functionName === 'get_cms_page_content') {
                    $toolOutput = json_encode($this->getCmsContent($args['cms_id']));
                } elseif ($functionName === 'get_my_orders') {
                    $toolOutput = json_encode($this->getLastOrder());
                } elseif ($functionName === 'get_active_offers') {
                    $toolOutput = json_encode($this->getActiveOffers());
                }

                $messages[] = ['role' => 'tool', 'tool_call_id' => $toolCall['id'], 'content' => $toolOutput];
            }

            // Step 3: Final Response
            $finalResult = $this->callOpenAI($messages, null, $apiKey); 
            return $finalResult['choices'][0]['message']['content'];
        }

        return $message['content'];
    }

    private function callOpenAI($messages, $tools, $apiKey)
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = ['model' => 'gpt-4o-mini', 'messages' => $messages, 'temperature' => 0.7];
        if ($tools) { $data['tools'] = $tools; $data['tool_choice'] = 'auto'; }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    // --- DATA FUNCTIONS ---

    /**
     * Λήψη Περιεχομένου CMS Σελίδας
     */
    private function getCmsContent($cms_id)
    {
        $id_lang = Context::getContext()->language->id;
        $cms = new CMS($cms_id, $id_lang);
        
        if (Validate::isLoadedObject($cms)) {
            return [
                'title' => $cms->meta_title,
                'content' => strip_tags($cms->content) // Καθαρίζουμε το HTML για οικονομία tokens
            ];
        }
        return "Η σελίδα δεν βρέθηκε.";
    }

    private function searchProducts($query)
    {
        $id_lang = Context::getContext()->language->id;
        $searchResults = Search::find($id_lang, $query, 1, 8, 'position', 'desc');
        
        $products = [];
        if (!empty($searchResults['result'])) {
            $link = Context::getContext()->link;
            foreach ($searchResults['result'] as $row) {
                $productObj = new Product($row['id_product'], false, $id_lang);
                $cover = Product::getCover($row['id_product']);
                $imagePath = $cover ? $link->getImageLink($row['link_rewrite'], $cover['id_image'], 'home_default') : '';
                
                // Features & Variants
                $features = $productObj->getFrontFeatures($id_lang);
                $feats = []; foreach ($features as $f) { $feats[] = $f['name'] . ': ' . $f['value']; }
                
                $combinations = $productObj->getAttributeCombinations($id_lang);
                $vars = []; foreach ($combinations as $c) { $vars[$c['group_name']][] = $c['attribute_name']; }
                $varsText = ""; foreach ($vars as $g => $a) { $varsText .= $g . ": [" . implode(', ', array_unique($a)) . "] "; }

                $products[] = [
                    'name' => $row['pname'],
                    'price' => Product::getPriceStatic($row['id_product']),
                    'link' => $link->getProductLink($row['id_product']),
                    'image' => $imagePath,
                    'description' => strip_tags($productObj->description_short), 
                    'features' => implode(", ", $feats),
                    'variants' => $variantsText,
                    'stock' => StockAvailable::getQuantityAvailableByProduct($row['id_product'], 0) > 0 ? 'In Stock' : 'Out of Stock'
                ];
            }
        }
        return empty($products) ? "Δεν βρέθηκαν προϊόντα." : $products;
    }

    private function getLastOrder()
    {
        $context = Context::getContext();
        if (!$context->customer->isLogged()) return "Ο χρήστης δεν είναι συνδεδεμένος.";
        $orders = Order::getCustomerOrders($context->customer->id);
        $recent = array_slice($orders, 0, 3);
        $data = [];
        foreach ($recent as $o) { $data[] = ['reference' => $o['reference'], 'date' => $o['date_add'], 'status' => $o['order_state']]; }
        return $data;
    }

    private function getActiveOffers()
    {
        $rules = CartRule::getCustomerCartRules(Context::getContext()->language->id, Context::getContext()->customer->id, true, true, true);
        $data = [];
        foreach ($rules as $r) { $data[] = ['name' => $r['name'], 'code' => $r['code'], 'reduction' => $r['reduction_percent'] > 0 ? $r['reduction_percent'].'%' : $r['reduction_amount'].'€']; }
        return $data;
    }
}