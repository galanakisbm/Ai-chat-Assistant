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
        $pageContextJson = Tools::getValue('page_context');
        $apiKey = Configuration::get('OPTIC_AICHAT_API_KEY');
        
        if (empty($apiKey)) {
            $this->returnJson(['status' => 'error', 'reply' => 'Configuration Error: API Key missing.']);
        }

        if (empty($userMessage)) {
            $this->returnJson(['status' => 'error', 'reply' => 'Empty message.']);
        }

        $startTime = microtime(true);
        $response = $this->handleOpenAIConversation($userMessage, $historyJson, $pageContextJson, $apiKey);
        $responseTime = microtime(true) - $startTime;

        // Log the conversation
        $this->logConversation($userMessage, $response, $pageContextJson, $responseTime);

        // Parse AI response to structured format
        $parsedResponse = $this->parseAIResponse($response);

        $this->returnJson([
            'status' => 'success',
            'reply' => $parsedResponse
        ]);
    }

    private function parseAIResponse($response)
    {
        // First try to parse markdown format (###...![](image)...Τιμή...€...[](url))
        $markdownPattern = '/###\s*(.+?)\n.*?\!\[.*?\]\((https?:\/\/[^\)]+)\).*?Τιμή[:\*\s]+([\d,\.]+)€.*?\[.*?\]\((https?:\/\/[^\)]+)\)/s';
        preg_match_all($markdownPattern, $response, $mdMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        
        if (!empty($mdMatches)) {
            // Markdown format detected
            $result = [
                'type' => 'mixed',
                'content' => []
            ];
            
            // Add intro text if exists
            $firstProductPos = $mdMatches[0][0][1];
            if ($firstProductPos > 0) {
                $introText = trim(substr($response, 0, $firstProductPos));
                if ($introText) {
                    $result['content'][] = [
                        'type' => 'text',
                        'text' => $introText
                    ];
                }
            }
            
            // Add products from markdown
            $lastPos = 0;
            foreach ($mdMatches as $match) {
                $result['content'][] = [
                    'type' => 'product',
                    'name' => trim($match[1][0]),
                    'image' => $match[2][0],
                    'price' => $match[3][0],
                    'url' => $match[4][0]
                ];
                $lastPos = $match[0][1] + strlen($match[0][0]);
            }
            
            // Add outro text if exists
            $outroText = trim(substr($response, $lastPos));
            // Remove common suffixes
            $outroText = preg_replace('/^[\.\s]*/', '', $outroText);
            if ($outroText && strlen($outroText) > 10) {
                $result['content'][] = [
                    'type' => 'text',
                    'text' => $outroText
                ];
            }
            
            return $result;
        }
        
        // Then try [PRODUCT:...] format (using pipe | as delimiter)
        $pattern = '/\[PRODUCT:([^|]+)\|([^|]+)\|([^|]+)\|([^|]+)\|([^\]]+)\]/';
        preg_match_all($pattern, $response, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        
        if (!empty($matches)) {
            $result = [
                'type' => 'mixed',
                'content' => []
            ];
            
            $lastPos = 0;
            foreach ($matches as $match) {
                // Add text before product
                $textBefore = substr($response, $lastPos, $match[0][1] - $lastPos);
                if (trim($textBefore)) {
                    $result['content'][] = [
                        'type' => 'text',
                        'text' => trim($textBefore)
                    ];
                }
                
                // Add product card
                $result['content'][] = [
                    'type' => 'product',
                    'id' => $match[1][0],
                    'name' => $match[2][0],
                    'price' => $match[3][0],
                    'image' => $match[4][0],
                    'url' => $match[5][0]
                ];
                
                $lastPos = $match[0][1] + strlen($match[0][0]);
            }
            
            // Add remaining text
            $textAfter = substr($response, $lastPos);
            if (trim($textAfter)) {
                $result['content'][] = [
                    'type' => 'text',
                    'text' => trim($textAfter)
                ];
            }
            
            return $result;
        }
        
        // No products found, return as simple text
        return [
            'type' => 'text',
            'content' => $response
        ];
    }

    private function returnJson($data)
    {
        header('Content-Type: application/json');
        die(json_encode($data));
    }

    private function handleOpenAIConversation($userMessage, $historyJson, $pageContextJson, $apiKey)
    {
        $history = json_decode(html_entity_decode($historyJson), true);
        $id_lang = Context::getContext()->language->id;

        // Handle Page Context
        $pageContext = '';
        if (Configuration::get('OPTIC_AICHAT_ENABLE_PAGE_CONTEXT') && !empty($pageContextJson)) {
            $pageData = json_decode($pageContextJson, true);
            if ($pageData) {
                $pageContext = "\n\n=== CURRENT PAGE INFORMATION ===\n";
                $pageContext .= "Page Type: " . $pageData['type'] . "\n";
                $pageContext .= "Page URL: " . $pageData['url'] . "\n";
                $pageContext .= "Page Title: " . $pageData['title'] . "\n";
                
                if (isset($pageData['productName'])) {
                    $pageContext .= "Product: " . $pageData['productName'] . "\n";
                    $pageContext .= "Price: " . $pageData['productPrice'] . "\n";
                }
                if (isset($pageData['categoryName'])) {
                    $pageContext .= "Category: " . $pageData['categoryName'] . "\n";
                }
                
                $pageContext .= "User is currently viewing this page. Use this context to provide relevant assistance.\n";
                $pageContext .= "===================================\n";
            }
        }

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

        // System Prompt - Enhanced with better instructions
        $cmsPages = CMS::getCMSPages($id_lang);
        $cmsList = "";
        foreach ($cmsPages as $cp) {
            $cmsList .= "ID: " . $cp['id_cms'] . " - Title: " . $cp['meta_title'] . "\n";
        }

        $basePrompt = Configuration::get('OPTIC_AICHAT_SYSTEM_PROMPT') ?: "You are a helpful shopping assistant.";
        
        $systemInstruction = $basePrompt . 
        " You MUST answer in Greek (Ελληνικά). " .
        " You are a professional e-commerce assistant. Be friendly, concise, and helpful. " .
        " IMPORTANT RESPONSE FORMAT RULES:\n" .
        " - When recommending products from search_products results, you MUST format them as:\n" .
        "   [PRODUCT:id|name|price|image|url]\n" .
        "   Use pipe | as separator (NOT colon). Use exact values from search results.\n" .
        "   Example: [PRODUCT:19|Προσαρμόσιμη Κούπα|17.24|https://example.com/img.jpg|https://example.com/product]\n" .
        " - You can include multiple products, one per line\n" .
        " - When available, mention product details like sizes, dimensions, composition, and stock status in your text\n" .
        " - Add friendly Greek text before/after products to provide context\n" .
        " - For non-product responses, use simple, friendly Greek text\n" .
        " - Available tools: search_products, get_cms_page_content, get_my_orders, get_active_offers\n" .
        " - Keep responses concise and helpful\n" .
        " Available CMS pages:\n" . $cmsList .
        ($pageContext ? "\n\nCURRENT PAGE CONTEXT:\n" . $pageContext : "");

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
        
        // Try XML first (faster)
        $xmlProducts = $this->searchProductsFromXML($query);
        
        if (!empty($xmlProducts)) {
            return $xmlProducts;
        }
        
        // Fallback to database
        $searchResults = Search::find($id_lang, $query, 1, 8, 'position', 'desc');
        
        $products = [];
        if (!empty($searchResults['result'])) {
            $link = Context::getContext()->link;
            foreach ($searchResults['result'] as $row) {
                $productObj = new Product($row['id_product'], false, $id_lang);
                $cover = Product::getCover($row['id_product']);
                $imagePath = $cover ? $link->getImageLink($row['link_rewrite'], $cover['id_image'], 'home_default') : '';
                $productUrl = $link->getProductLink($row['id_product']);
                $price = Product::getPriceStatic($row['id_product']);
                
                // Features & Variants
                $features = $productObj->getFrontFeatures($id_lang);
                $feats = []; foreach ($features as $f) { $feats[] = $f['name'] . ': ' . $f['value']; }
                
                $combinations = $productObj->getAttributeCombinations($id_lang);
                $vars = []; foreach ($combinations as $c) { $vars[$c['group_name']][] = $c['attribute_name']; }
                $varsText = ""; foreach ($vars as $g => $a) { $varsText .= $g . ": [" . implode(', ', array_unique($a)) . "] "; }

                $products[] = [
                    'id' => $row['id_product'],
                    'name' => $row['pname'],
                    'price' => number_format($price, 2),
                    'image' => $imagePath,
                    'url' => $productUrl,
                    'description' => strip_tags($productObj->description_short), 
                    'features' => implode(", ", $feats),
                    'variants' => $varsText,
                    'stock' => StockAvailable::getQuantityAvailableByProduct($row['id_product'], 0) > 0 ? 'In Stock' : 'Out of Stock'
                ];
            }
        }
        return empty($products) ? "Δεν βρέθηκαν προϊόντα." : $products;
    }

    /**
     * Search products from XML cache
     */
    private function searchProductsFromXML($query)
    {
        $cacheFile = _PS_MODULE_DIR_ . 'optic_aichat/uploads/products_cache.json';
        
        if (!file_exists($cacheFile)) {
            return [];
        }
        
        $jsonContent = file_get_contents($cacheFile);
        if ($jsonContent === false) {
            error_log('OpticAiChat: Failed to read products cache file');
            return [];
        }
        
        $products = json_decode($jsonContent, true);
        if ($products === null || !is_array($products)) {
            error_log('OpticAiChat: Failed to parse products cache JSON');
            return [];
        }
        
        $results = [];
        $queryLower = mb_strtolower($query);
        
        foreach ($products as $product) {
            // Search in multiple fields
            $searchFields = array_filter([
                $product['title'],
                $product['description'],
                $product['short_description'],
                $product['category'],
                $product['sizes'] ?? '',
                $product['composition'] ?? '',
                $product['dimensions'] ?? ''
            ]);
            $searchableText = mb_strtolower(implode(' ', $searchFields));
            
            // Fuzzy search
            if (strpos($searchableText, $queryLower) !== false) {
                // Format for AI response with rich data
                $results[] = [
                    'id' => $product['product_id'],
                    'name' => $product['title'],
                    'description' => $product['short_description'] ?: $product['description'],
                    'price' => $product['price_sale'],
                    'regular_price' => $product['price_regular'],
                    'image' => $product['image'],
                    'url' => $product['url'],
                    'category' => $product['category'],
                    'onsale' => $product['onsale'] ?? '',
                    'sizes' => $product['sizes'] ?? '',
                    'composition' => $product['composition'] ?? '',
                    'dimensions' => $product['dimensions'] ?? '',
                    'instock' => $product['instock'] ?? '',
                ];
            }
            
            if (count($results) >= 5) break;
        }
        
        return $results;
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

    /**
     * Log conversation to database
     */
    private function logConversation($message, $response, $pageContextJson, $responseTime)
    {
        $context = Context::getContext();
        $pageData = json_decode($pageContextJson, true);
        
        $sessionId = session_id();
        if (empty($sessionId)) {
            $sessionId = md5(uniqid(rand(), true));
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'optic_aichat_logs` 
                (`id_customer`, `message`, `response`, `page_url`, `page_context`, `session_id`, `date_add`, `response_time`)
                VALUES (
                    ' . ($context->customer->isLogged() ? (int)$context->customer->id : 'NULL') . ',
                    "' . pSQL($message) . '",
                    "' . pSQL(strip_tags($response)) . '",
                    "' . pSQL($pageData['url'] ?? '') . '",
                    "' . pSQL(json_encode($pageData)) . '",
                    "' . pSQL($sessionId) . '",
                    NOW(),
                    ' . (float)$responseTime . '
                )';
        
        Db::getInstance()->execute($sql);
    }
}