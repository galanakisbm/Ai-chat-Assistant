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
        
        // Detect language for analytics
        $autoDetect = Configuration::get('OPTIC_AICHAT_AUTO_LANGUAGE');
        $fallbackLang = Configuration::get('OPTIC_AICHAT_FALLBACK_LANG') ?: 'el';
        
        if ($autoDetect) {
            $detectedLang = $this->detectLanguage($userMessage);
        } else {
            $detectedLang = $fallbackLang;
        }
        
        $response = $this->handleOpenAIConversation($userMessage, $historyJson, $pageContextJson, $apiKey);
        $responseTime = microtime(true) - $startTime;

        // Log the conversation to both tables
        $this->logConversation($userMessage, $response, $pageContextJson, $responseTime);
        $this->logAnalytics($userMessage, $response, $responseTime, $detectedLang);

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
        // Detect [CONTACT_CARD] marker
        if (strpos($response, '[CONTACT_CARD]') !== false) {
            $textContent = trim(str_replace('[CONTACT_CARD]', '', $response));
            return [
                'type' => 'contact_card',
                'text' => $textContent ?: 'Μπορείτε να επικοινωνήσετε μαζί μας μέσω:'
            ];
        }

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

        // Detect language
        $autoDetect = Configuration::get('OPTIC_AICHAT_AUTO_LANGUAGE');
        $fallbackLang = Configuration::get('OPTIC_AICHAT_FALLBACK_LANG') ?: 'el';
        
        if ($autoDetect) {
            $detectedLang = $this->detectLanguage($userMessage);
        } else {
            $detectedLang = $fallbackLang;
        }

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

        // Build dynamic context from Knowledge Base
        $dynamicContext = $this->module->buildDynamicContext();
        
        // Build contact context for AI
        $contactParts = [];
        if (Configuration::get('OPTIC_AICHAT_CONTACT_PHONE'))     $contactParts[] = 'Phone: '     . Configuration::get('OPTIC_AICHAT_CONTACT_PHONE');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_EMAIL'))     $contactParts[] = 'Email: '     . Configuration::get('OPTIC_AICHAT_CONTACT_EMAIL');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_VIBER'))     $contactParts[] = 'Viber: '     . Configuration::get('OPTIC_AICHAT_CONTACT_VIBER');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_WHATSAPP'))  $contactParts[] = 'WhatsApp: '  . Configuration::get('OPTIC_AICHAT_CONTACT_WHATSAPP');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_MESSENGER')) $contactParts[] = 'Messenger: ' . Configuration::get('OPTIC_AICHAT_CONTACT_MESSENGER');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_TELEGRAM'))  $contactParts[] = 'Telegram: '  . Configuration::get('OPTIC_AICHAT_CONTACT_TELEGRAM');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_INSTAGRAM')) $contactParts[] = 'Instagram: ' . Configuration::get('OPTIC_AICHAT_CONTACT_INSTAGRAM');
        if (Configuration::get('OPTIC_AICHAT_CONTACT_FACEBOOK'))  $contactParts[] = 'Facebook: '  . Configuration::get('OPTIC_AICHAT_CONTACT_FACEBOOK');
        $contactContext = !empty($contactParts)
            ? "\n\nCONTACT INFO:\n" . implode("\n", $contactParts) . "\n- When user asks how to contact the store, respond with a friendly message followed by exactly [CONTACT_CARD] on its own line.\n"
            : '';

        // Get language instruction
        $languageInstruction = $this->getLanguageInstruction($detectedLang);

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
        " You are a professional e-commerce assistant. Be friendly, concise, and helpful. " .
        " CRITICAL PRODUCT DISPLAY RULES:\n" .
        " - When you receive search_products results, you MUST display them using the [PRODUCT:...] format\n" .
        " - NEVER give generic responses about product categories when search results are available\n" .
        " - ALWAYS use this exact format for EACH product:\n" .
        "   [PRODUCT:id|name|price|image|url]\n" .
        " - Use pipe | as separator (NOT colon or comma)\n" .
        " - Extract exact values from the search_products response\n" .
        " - Example: [PRODUCT:19|Προσαρμόσιμη Κούπα|17.24|https://example.com/img.jpg|https://example.com/product]\n\n" .
        " WORKFLOW:\n" .
        " 1. User asks about products (e.g., 'Δείξε μου ρούχα')\n" .
        " 2. You call search_products with the query\n" .
        " 3. You receive an array of products with id, name, price, image, url\n" .
        " 4. You format EACH product as [PRODUCT:id|name|price|image|url]\n" .
        " 5. You add friendly text before/after the product list\n\n" .
        " BAD RESPONSE (generic):\n" .
        " 'Στο κατάστημά μας έχουμε ρούχα για άνδρες και γυναίκες...'\n\n" .
        " GOOD RESPONSE (with actual products):\n" .
        " 'Βρήκα αυτά τα προϊόντα για εσάς:\n" .
        " [PRODUCT:123|T-shirt Βαμβακερό|25.00|https://example.com/img/123.jpg|https://example.com/product/123]\n" .
        " [PRODUCT:456|Φούστα Καλοκαιρινή|35.50|https://example.com/img/456.jpg|https://example.com/product/456]'\n\n" .
        " - If search returns empty array, then say: 'Δεν βρέθηκαν προϊόντα για αυτήν την αναζήτηση.'\n" .
        " - Include product details (sizes, stock, etc.) in your text if available\n" .
        " - Available tools: search_products, get_cms_page_content, get_my_orders, get_active_offers\n" .
        " - Keep responses concise and helpful\n" .
        " OFFERS/DISCOUNTS RULES:\n" .
        " - When get_active_offers returns sale_products array, display EACH sale product as [PRODUCT:id|name|price|image|url]\n" .
        " - When get_active_offers returns coupons, show the coupon code AND the product cards together\n" .
        " - NEVER show sale products as a plain bullet list - always use [PRODUCT:...] format\n" .
        " - Example offer response:\n" .
        " 'Ενεργές προσφορές:\n" .
        " [PRODUCT:1|T-shirt|23.71|https://example.com/img.jpg|https://example.com/product]\n" .
        " Κωδικός έκπτωσης: SUMMER10 (-10%)'.\n\n" .
        " Available CMS pages:\n" . $cmsList .
        ($pageContext ? "\n\nCURRENT PAGE CONTEXT:\n" . $pageContext : "") .
        ($dynamicContext ? "\n\nKNOWLEDGE BASE:\n" . $dynamicContext : "") .
        $contactContext .
        $languageInstruction;

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

    private function detectLanguage($message)
    {
        // Greek detection (ελληνικοί χαρακτήρες)
        if (preg_match('/[Α-Ωα-ωίϊΐόάέύϋΰήώ]/u', $message)) {
            return 'el';
        }
        
        // Add more languages if needed
        // For now, default to English
        return 'en';
    }

    private function getLanguageInstruction($lang)
    {
        switch ($lang) {
            case 'el':
                return "\n\nCRITICAL: You MUST respond in Greek (Ελληνικά). Use Greek characters and natural Greek language.";
            case 'en':
                return "\n\nCRITICAL: You MUST respond in English.";
            default:
                return "";
        }
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log('OpticAiChat cURL Error: ' . $curlError);
            return ['error' => ['message' => 'Connection to OpenAI failed. Please try again later.']];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('OpticAiChat JSON Error: ' . $response);
            return ['error' => ['message' => 'Invalid JSON response from OpenAI (HTTP ' . $httpCode . ')']];
        }

        return $decoded;
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
        
        // Detect "new arrivals" type queries — return first products from feed (newest first)
        $newArrivalsKeywords = ['νέα', 'νέο', 'νέος', 'νέες', 'new', 'arrivals', 'arrival', 'νεα', 'νεο',
                                'τελευταία', 'τελευταιες', 'τελευταίες', 'καινούρια', 'καινουρια',
                                'latest', 'recent', 'αφίξεις', 'αφιξεις'];
        $queryLower = mb_strtolower(trim($query));
        $isNewArrivalsQuery = false;
        foreach ($newArrivalsKeywords as $kw) {
            if (strpos($queryLower, $kw) !== false) {
                $isNewArrivalsQuery = true;
                break;
            }
        }

        if ($isNewArrivalsQuery) {
            // Return first 6 products (XML feed is typically newest-first)
            $results = [];
            foreach (array_slice($products, 0, 6) as $product) {
                $results[] = [
                    'id' => $product['product_id'],
                    'name' => $product['title'],
                    'description' => $product['short_description'] ?: ($product['description'] ?? ''),
                    'price' => $product['price_sale'],
                    'regular_price' => $product['price_regular'] ?? '',
                    'image' => $product['image'],
                    'url' => $product['url'],
                    'category' => $product['category'] ?? '',
                    'onsale' => $product['onsale'] ?? '',
                    'sizes' => $product['sizes'] ?? '',
                    'composition' => $product['composition'] ?? '',
                    'dimensions' => $product['dimensions'] ?? '',
                    'instock' => $product['instock'] ?? '',
                ];
            }
            return $results;
        }

        $results = [];

        foreach ($products as $product) {
            // Search in multiple fields
            $searchFields = array_filter([
                $product['title'] ?? '',
                $product['description'] ?? '',
                $product['short_description'] ?? '',
                $product['category'] ?? '',
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
                    'description' => $product['short_description'] ?: ($product['description'] ?? ''),
                    'price' => $product['price_sale'],
                    'regular_price' => $product['price_regular'] ?? '',
                    'image' => $product['image'],
                    'url' => $product['url'],
                    'category' => $product['category'] ?? '',
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
        $coupons = [];
        foreach ($rules as $r) {
            $coupons[] = [
                'name'      => $r['name'],
                'code'      => $r['code'],
                'reduction' => $r['reduction_percent'] > 0 ? $r['reduction_percent'].'%' : $r['reduction_amount'].'€'
            ];
        }

        $saleProducts = $this->getSaleProductsFromXML();

        return [
            'coupons'       => $coupons,
            'sale_products' => $saleProducts
        ];
    }

    private function getSaleProductsFromXML()
    {
        $cacheFile = _PS_MODULE_DIR_ . 'optic_aichat/uploads/products_cache.json';
        if (!file_exists($cacheFile)) return [];

        $products = json_decode(file_get_contents($cacheFile), true);
        if (!$products || !is_array($products)) return [];

        $results = [];
        foreach ($products as $product) {
            $isOnSale = !empty($product['onsale']) && $product['onsale'] !== 'false' && $product['onsale'] !== '0';
            $hasPriceReduction = !empty($product['price_regular']) && !empty($product['price_sale'])
                && (float)$product['price_sale'] < (float)$product['price_regular'];

            if ($isOnSale || $hasPriceReduction) {
                $results[] = [
                    'id'            => $product['product_id'] ?? '',
                    'name'          => $product['title'] ?? '',
                    'price'         => $product['price_sale'] ?? '',
                    'regular_price' => $product['price_regular'] ?? '',
                    'image'         => $product['image'] ?? '',
                    'url'           => $product['url'] ?? '',
                    'category'      => $product['category'] ?? '',
                ];
            }
            if (count($results) >= 6) break;
        }
        return $results;
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

    /**
     * Log conversation to analytics table
     */
    private function logAnalytics($userMessage, $botResponse, $responseTime, $detectedLang)
    {
        $idCustomer = Context::getContext()->customer->id ?: null;
        
        // Extract product mentions (simple keyword matching)
        $productsMentioned = $this->extractProductMentions($userMessage . ' ' . $botResponse);
        
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'optic_aichat_analytics`
                (id_customer, user_message, bot_response, products_mentioned, response_time, detected_language, date_add)
                VALUES (
                    ' . (int)$idCustomer . ',
                    "' . pSQL($userMessage, true) . '",
                    "' . pSQL($botResponse, true) . '",
                    "' . pSQL($productsMentioned) . '",
                    ' . (float)$responseTime . ',
                    "' . pSQL($detectedLang) . '",
                    NOW()
                )';
        
        Db::getInstance()->execute($sql);
    }

    private function extractProductMentions($text)
    {
        $found = [];
        $textLower = mb_strtolower($text);
        
        // Try to get keywords from products cache
        $cacheFile = _PS_MODULE_DIR_ . 'optic_aichat/uploads/products_cache.json';
        
        if (file_exists($cacheFile)) {
            $products = json_decode(file_get_contents($cacheFile), true);
            if ($products && is_array($products)) {
                foreach ($products as $product) {
                    // Check if product title appears in text
                    $titleLower = mb_strtolower($product['title']);
                    if (strpos($textLower, $titleLower) !== false) {
                        $found[] = $product['title'];
                    }
                    
                    // Check if category appears in text
                    if (!empty($product['category'])) {
                        $categoryLower = mb_strtolower($product['category']);
                        if (strpos($textLower, $categoryLower) !== false) {
                            $found[] = $product['category'];
                        }
                    }
                    
                    // Limit to avoid huge strings
                    if (count($found) >= 10) {
                        break;
                    }
                }
            }
        }
        
        // Fallback to common keywords if no products found
        if (empty($found)) {
            $keywords = ['μπλούζα', 't-shirt', 'shirt', 'κούπα', 'mug', 'notebook', 'έκπτωση', 'sale', 
                         'προσφορά', 'discount', 'ρούχα', 'clothes', 'παπούτσια', 'shoes', 
                         'τσάντα', 'bag', 'φόρεμα', 'dress'];
            
            foreach ($keywords as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    $found[] = $keyword;
                }
            }
        }
        
        return implode(',', array_unique($found));
    }
}