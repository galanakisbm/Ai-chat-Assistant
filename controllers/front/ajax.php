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
        
        $result = $this->handleOpenAIConversation($userMessage, $historyJson, $pageContextJson, $apiKey);
        $responseTime = microtime(true) - $startTime;

        // Feature 6: result is now ['text' => ..., 'is_comparison' => bool]
        $responseText = is_array($result) ? ($result['text'] ?? '') : $result;
        $isComparison = is_array($result) ? ($result['is_comparison'] ?? false) : false;

        // Log the conversation to both tables
        $this->logConversation($userMessage, $responseText, $pageContextJson, $responseTime);
        $this->logAnalytics($userMessage, $responseText, $responseTime, $detectedLang);

        // Parse AI response to structured format
        $parsedResponse = $this->parseAIResponse($responseText, $isComparison);

        $this->returnJson([
            'status' => 'success',
            'reply' => $parsedResponse
        ]);
    }

    private function parseAIResponse($response, $isComparison = false)
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
            // Feature 6: if comparison query with exactly 2 products, return comparison type
            if ($isComparison && count($matches) === 2) {
                $products = [];
                $introText = trim(substr($response, 0, $matches[0][0][1]));
                foreach ($matches as $match) {
                    $products[] = [
                        'id'    => $match[1][0],
                        'name'  => $match[2][0],
                        'price' => $match[3][0],
                        'image' => $match[4][0],
                        'url'   => $match[5][0]
                    ];
                }
                return [
                    'type'     => 'comparison',
                    'text'     => $introText ?: null,
                    'products' => $products
                ];
            }

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

        // Feature 7: Build handoff info for system prompt
        $handoffInfo = '';
        if (Configuration::get('OPTIC_AICHAT_PHONE') || Configuration::get('OPTIC_AICHAT_WHATSAPP') || Configuration::get('OPTIC_AICHAT_EMAIL')) {
            $handoffInfo = "\n\nΣτοιχεία επικοινωνίας:\n";
            if (Configuration::get('OPTIC_AICHAT_PHONE')) {
                $handoffInfo .= "Τηλέφωνο: " . Configuration::get('OPTIC_AICHAT_PHONE') . "\n";
            }
            if (Configuration::get('OPTIC_AICHAT_WHATSAPP')) {
                $handoffInfo .= "WhatsApp: " . Configuration::get('OPTIC_AICHAT_WHATSAPP') . "\n";
            }
            if (Configuration::get('OPTIC_AICHAT_VIBER')) {
                $handoffInfo .= "Viber: " . Configuration::get('OPTIC_AICHAT_VIBER') . "\n";
            }
            if (Configuration::get('OPTIC_AICHAT_EMAIL')) {
                $handoffInfo .= "Email: " . Configuration::get('OPTIC_AICHAT_EMAIL') . "\n";
            }
            $withinHours = $this->isWithinBusinessHours();
            $handoffInfo .= $withinHours
                ? "Είμαστε τώρα διαθέσιμοι τηλεφωνικά.\n"
                : "Είμαστε εκτός ωραρίου. Προτείνετε στον χρήστη να στείλει μήνυμα WhatsApp/email ή να επικοινωνήσει την επόμενη εργάσιμη.\n";
        }

        $systemInstruction .= $handoffInfo;

        // Build History Context
        $messages = [['role' => 'system', 'content' => $systemInstruction]];
        if (!empty($history) && is_array($history)) {
            foreach (array_slice($history, -10) as $turn) {
                // Feature 5: support new {role, content} format
                if (isset($turn['role'], $turn['content'])) {
                    $messages[] = [
                        'role'    => in_array($turn['role'], ['user', 'assistant']) ? $turn['role'] : 'user',
                        'content' => mb_substr((string)$turn['content'], 0, 500)
                    ];
                } elseif (isset($turn['class'])) {
                    // Legacy {class, text} format
                    $role = (strpos($turn['class'], 'user-message') !== false) ? 'user' : 'assistant';
                    $messages[] = ['role' => $role, 'content' => mb_substr(strip_tags((string)$turn['text']), 0, 500)];
                }
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Feature 6: detect comparison query
        $isComparison = $this->isComparisonQuery($userMessage);

        // Step 1: OpenAI Call
        $result = $this->callOpenAI($messages, $tools, $apiKey);
        if (isset($result['error'])) return ['text' => "OpenAI Error: " . $result['error']['message'], 'is_comparison' => false];
        
        $message = $result['choices'][0]['message'];

        // Step 2: Handle Tool Calls
        if (isset($message['tool_calls'])) {
            $messages[] = $message;

            foreach ($message['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'], true);
                $toolOutput = "";

                if ($functionName === 'search_products') {
                    $toolOutput = json_encode($this->searchProducts($args['query'], $isComparison));
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
            return ['text' => $finalResult['choices'][0]['message']['content'], 'is_comparison' => $isComparison];
        }

        return ['text' => $message['content'], 'is_comparison' => $isComparison];
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

    private function searchProducts($query, $isComparison = false)
    {
        $id_lang = Context::getContext()->language->id;

        // Extract price filter before any search so both XML and DB paths use a clean query
        $priceExtracted = $this->extractPriceFilter($query);
        $searchQuery = $priceExtracted['query'];
        $priceMin    = $priceExtracted['min'];
        $priceMax    = $priceExtracted['max'];
        
        // Try XML first (faster) — XML path handles price extraction internally
        $xmlProducts = $this->searchProductsFromXML($query, $isComparison);
        
        if (!empty($xmlProducts)) {
            return $xmlProducts;
        }
        
        // Fallback to database — use cleaned query (without price text) for text search
        // If the entire query was a price expression, skip DB search (XML handles all-price queries)
        if ($searchQuery === '') {
            return "Δεν βρέθηκαν προϊόντα.";
        }
        $expandedQueries = $this->expandQueryWithSynonyms($searchQuery);
        $searchResults = Search::find($id_lang, $searchQuery, 1, 8, 'position', 'desc');
        if (empty($searchResults['result']) && count($expandedQueries) > 1) {
            foreach (array_slice($expandedQueries, 1) as $expandedQuery) {
                $searchResults = Search::find($id_lang, $expandedQuery, 1, 8, 'position', 'desc');
                if (!empty($searchResults['result'])) {
                    break;
                }
            }
        }
        
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

        // Apply price filter to DB results
        if (!empty($products) && ($priceMin !== null || $priceMax !== null)) {
            $products = array_values(array_filter($products, function ($product) use ($priceMin, $priceMax) {
                $price = null;
                foreach (['price_sale', 'price', 'price_regular'] as $field) {
                    if (isset($product[$field]) && $product[$field] !== '' && $product[$field] !== null) {
                        $price = (float) str_replace([',', '€', ' '], ['.', '', ''], (string)$product[$field]);
                        if ($price > 0) break;
                    }
                }
                if ($price === null || $price <= 0) return true;
                if ($priceMin !== null && $price < $priceMin) return false;
                if ($priceMax !== null && $price > $priceMax) return false;
                return true;
            }));
        }

        return empty($products) ? "Δεν βρέθηκαν προϊόντα." : $products;
    }

    /**
     * Search products from XML cache
     */
    /**
     * Parse a price constraint from a natural-language query.
     * Supports Greek and English: "μέχρι 30 ευρώ", "έως 50€", "κάτω από 25€",
     * "από 10 ευρώ", "πάνω από 20€", standalone "30€", etc.
     *
     * @param string $query
     * @return array|null  Associative array with optional keys 'min' and/or 'max' (floats), or null if no price found.
     */
    private function parsePriceFilter($query)
    {
        $filter = [];

        // Max price: "μέχρι/έως/κάτω από/under/up to X"
        if (preg_match('/(?:μέχρι|μεχρι|έως|εως|κάτω\s*από|κατω\s*απο|under|max|up\s*to)\s*([\d,\.]+)\s*(?:ευρώ|ευρω|€|euro|eur)?/ui', $query, $m)) {
            $filter['max'] = (float) str_replace(',', '.', $m[1]);
        }

        // Min price: "από/πάνω από/over/min/from X"
        if (preg_match('/(?:από|απο|πάνω\s*από|πανω\s*απο|over|min|from)\s*([\d,\.]+)\s*(?:ευρώ|ευρω|€|euro|eur)?/ui', $query, $m)) {
            $filter['min'] = (float) str_replace(',', '.', $m[1]);
        }

        // Standalone "X ευρώ" or "X€" with no direction keyword → treat as max
        if (empty($filter) && preg_match('/([\d,\.]+)\s*(?:ευρώ|ευρω|€)/ui', $query, $m)) {
            $filter['max'] = (float) str_replace(',', '.', $m[1]);
        }

        return empty($filter) ? null : $filter;
    }

    /**
     * Remove price-related terms from a query string so the remaining text
     * can be used for pure product-name / category text matching.
     *
     * @param string $query
     * @return string  Cleaned query with price phrases removed and trimmed.
     */
    private function stripPriceFromQuery($query)
    {
        // Remove direction keywords + number + optional currency
        $cleaned = preg_replace(
            '/\s*(?:μέχρι|μεχρι|έως|εως|κάτω\s*από|κατω\s*απο|under|max|up\s*to|από|απο|πάνω\s*από|πανω\s*απο|over|min|from)?\s*[\d,\.]+\s*(?:ευρώ|ευρω|€|euro|eur)/ui',
            ' ',
            $query
        );
        return trim(preg_replace('/\s{2,}/', ' ', $cleaned));
    }

    /**
     * Extract price range from a natural-language query string.
     * Combines price detection and query cleaning in a single call.
     *
     * @param string $query
     * @return array  ['min' => float|null, 'max' => float|null, 'query' => string_without_price_expression]
     */
    private function extractPriceFilter(string $query): array
    {
        $min = null;
        $max = null;

        // Ceiling: "μέχρι/έως/ως/κάτω από/under/below/up to/max" + number + optional currency
        $ceilingPattern = '/\b(μέχρι|μεχρι|έως|εως|ως|κάτω\s+από|κατω\s+απο|up\s+to|max(?:imum)?|under|below)\s*[€]?\s*(\d+(?:[.,]\d{1,2})?)\s*(?:ευρώ?|ευρω|euro?s?|eur|€)?(?=\s|$)/iu';
        if (preg_match($ceilingPattern, $query, $m)) {
            $max   = (float) str_replace(',', '.', $m[2]);
            $query = trim(preg_replace($ceilingPattern, '', $query));
        }

        // Floor: "από/πάνω από/τουλάχιστον/over/above/from/min" + number + optional currency
        $floorPattern = '/\b(από|απο|πάνω\s+από|πανω\s+απο|τουλάχιστον|from|over|above|min(?:imum)?)\s*[€]?\s*(\d+(?:[.,]\d{1,2})?)\s*(?:ευρώ?|ευρω|euro?s?|eur|€)?(?=\s|$)/iu';
        if (preg_match($floorPattern, $query, $m)) {
            $min   = (float) str_replace(',', '.', $m[2]);
            $query = trim(preg_replace($floorPattern, '', $query));
        }

        // Standalone "30€" / "€30" / "30 ευρω" with no direction keyword → treat as ceiling
        if ($min === null && $max === null) {
            $standalonePattern = '/(?:€\s*(\d+(?:[.,]\d{1,2})?)(?=\s|$))|(?:(\d+(?:[.,]\d{1,2})?)\s*(?:ευρώ?|ευρω|euro?s?|eur|€)(?=\s|$))/iu';
            if (preg_match($standalonePattern, $query, $m)) {
                $val   = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
                $max   = (float) str_replace(',', '.', $val);
                $query = trim(preg_replace($standalonePattern, '', $query, 1));
            }
        }

        $query = trim(preg_replace('/\s{2,}/', ' ', $query));

        return ['min' => $min, 'max' => $max, 'query' => $query];
    }

    private function searchProductsFromXML($query, $isComparison = false)
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

        // --- Price filter detection ---
        $priceExtracted = $this->extractPriceFilter($queryLower);
        $priceMin    = $priceExtracted['min'];
        $priceMax    = $priceExtracted['max'];
        $cleanQuery  = $priceExtracted['query'];
        $priceFilter = ($priceMin !== null || $priceMax !== null) ? ['min' => $priceMin, 'max' => $priceMax] : null;
        $expandedQueries = $this->expandQueryWithSynonyms($cleanQuery);

        // Feature 2: Color filter detection
        $colorFilter = $this->parseColorFilter($query);

        // Feature 3: Size query detection
        $isSizeQ = $this->isSizeQuery($query);

        // Feature 6: limit to 2 for comparison
        $maxResults = $isComparison ? 2 : 5;

        $results = [];

        foreach ($products as $product) {
            // --- Text matching (on clean query, without price phrases) ---
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

            $matched = false;
            foreach ($expandedQueries as $q) {
                if ($q === '' || strpos($searchableText, $q) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            // --- Price filter ---
            if ($priceFilter !== null) {
                $productPrice = (float) str_replace(',', '.', $product['price_sale'] ?? '0');
                if (isset($priceFilter['max']) && $productPrice > $priceFilter['max']) {
                    continue;
                }
                if (isset($priceFilter['min']) && $productPrice < $priceFilter['min']) {
                    continue;
                }
            }

            // Feature 2: Color filter
            if ($colorFilter !== null) {
                if (strpos($searchableText, $colorFilter) === false) {
                    continue;
                }
            }

            // Feature 3: Include full description when size query
            $description = $isSizeQ
                ? ($product['description'] ?: ($product['short_description'] ?? ''))
                : ($product['short_description'] ?: ($product['description'] ?? ''));

            $results[] = [
                'id'           => $product['product_id'],
                'name'         => $product['title'],
                'description'  => $description,
                'price'        => $product['price_sale'],
                'regular_price'=> $product['price_regular'] ?? '',
                'image'        => $product['image'],
                'url'          => $product['url'],
                'category'     => $product['category'] ?? '',
                'onsale'       => $product['onsale'] ?? '',
                'sizes'        => $product['sizes'] ?? '',
                'composition'  => $product['composition'] ?? '',
                'dimensions'   => $product['dimensions'] ?? '',
                'instock'      => $product['instock'] ?? '',
            ];

            // Feature 4: Append FBT suggestions
            $fbt = $this->getFrequentlyBoughtTogether($product['product_id'], $product['category'] ?? '');
            if (!empty($fbt)) {
                $results[count($results) - 1]['frequently_bought_together'] = $fbt;
            }

            if (count($results) >= $maxResults) {
                break;
            }
        }

        return $results;
    }

    /**
     * Expand a query with synonyms/units configured in back-office
     */
    private function expandQueryWithSynonyms($query)
    {
        $synonymsRaw = Configuration::get('OPTIC_AICHAT_SYNONYMS');
        if (empty($synonymsRaw)) {
            return [mb_strtolower(trim($query))];
        }

        $groups = json_decode($synonymsRaw, true);
        if (!$groups || !is_array($groups)) {
            return [mb_strtolower(trim($query))];
        }

        $queryLower = mb_strtolower(trim($query));
        // Use associative array as a set for O(1) existence checks
        $expandedSet = [$queryLower => true];

        foreach ($groups as $group) {
            // Break from inner loop after finding first match in this group;
            // continue to next group so multiple synonym groups can apply.
            foreach ($group as $term) {
                $termLower = mb_strtolower(trim($term));
                if (!empty($termLower) && strpos($queryLower, $termLower) !== false) {
                    foreach ($group as $synonym) {
                        $synLower = mb_strtolower(trim($synonym));
                        if (!empty($synLower) && !isset($expandedSet[$synLower])) {
                            $expandedSet[$synLower] = true;
                            $substituted = str_replace($termLower, $synLower, $queryLower);
                            if (!isset($expandedSet[$substituted])) {
                                $expandedSet[$substituted] = true;
                            }
                        }
                    }
                    break;
                }
            }
        }

        return array_keys($expandedSet);
    }

    /**
     * Feature 2: Parse color filter from query
     */
    private function parseColorFilter($query)
    {
        $colors = [
            'κόκκινο','κοκκινο','red','μπλε','blue','πράσινο','πρασινο','green',
            'μαύρο','μαυρο','black','άσπρο','ασπρο','λευκό','λευκο','white',
            'κίτρινο','κιτρινο','yellow','πορτοκαλί','πορτοκαλι','orange',
            'ροζ','pink','μωβ','purple','γκρι','grey','gray','καφέ','καφε','brown',
            'μπεζ','beige','χρυσό','χρυσο','gold','ασημί','ασημι','silver',
            'πετρόλ','petrol','χακί','khaki','εκρού','ecru'
        ];
        $queryLower = mb_strtolower($query);
        foreach ($colors as $color) {
            if (strpos($queryLower, $color) !== false) {
                return $color;
            }
        }
        return null;
    }

    /**
     * Feature 3: Detect size-related queries
     */
    private function isSizeQuery($query)
    {
        $sizeKeywords = ['μέγεθος','μεγεθος','νούμερο','νουμερο','size','μέση','μεση',
            'στήθος','στηθος','ισχία','ισχια','ταιριάζει','ταιριαζει','cm','χιλιοστά',
            'φαρδύ','φαρδυ','στενό','στενο','μεγαλύτερο','μεγαλυτερο'];
        $q = mb_strtolower($query);
        foreach ($sizeKeywords as $kw) {
            if (strpos($q, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Feature 4: Frequently bought together
     */
    private function getFrequentlyBoughtTogether($productId, $category)
    {
        $rules = json_decode(Configuration::get('OPTIC_AICHAT_FBT_RULES') ?: '{}', true);
        if (is_array($rules) && isset($rules[$productId])) {
            return $rules[$productId];
        }
        if (Configuration::get('OPTIC_AICHAT_FBT_AUTO')) {
            $cacheFile = _PS_MODULE_DIR_ . 'optic_aichat/uploads/products_cache.json';
            if (!file_exists($cacheFile)) return [];
            $allProducts = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($allProducts)) return [];
            $suggestions = [];
            foreach ($allProducts as $p) {
                if ((string)$p['product_id'] !== (string)$productId && ($p['category'] ?? '') === $category) {
                    $suggestions[] = $p['product_id'];
                    if (count($suggestions) >= 2) break;
                }
            }
            return $suggestions;
        }
        return [];
    }

    /**
     * Feature 6: Detect comparison queries
     */
    private function isComparisonQuery($query)
    {
        $keywords = ['σύγκριση','συγκριση','σύγκρινε','συγκρινε','compare',
                     'διαφορά','διαφορα','versus',' vs ','ή το','ή αυτό'];
        $q = mb_strtolower($query);
        foreach ($keywords as $kw) {
            if (strpos($q, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Feature 7: Check if current time is within business hours
     */
    private function isWithinBusinessHours()
    {
        $timezone = Configuration::get('OPTIC_AICHAT_TIMEZONE') ?: 'Europe/Athens';
        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception $e) {
            $tz = new DateTimeZone('Europe/Athens');
        }
        $now = new DateTime('now', $tz);
        $dow = (int)$now->format('N'); // 1=Mon ... 7=Sun

        if ($dow >= 1 && $dow <= 5) {
            $hoursStr = Configuration::get('OPTIC_AICHAT_HOURS_MON_FRI');
        } elseif ($dow === 6) {
            $hoursStr = Configuration::get('OPTIC_AICHAT_HOURS_SAT');
        } else {
            $hoursStr = Configuration::get('OPTIC_AICHAT_HOURS_SUN');
        }

        if (empty($hoursStr)) {
            return false; // closed this day
        }

        $parts = explode('-', $hoursStr);
        if (count($parts) !== 2) {
            return false;
        }

        $openTime  = DateTime::createFromFormat('H:i', trim($parts[0]), $tz);
        $closeTime = DateTime::createFromFormat('H:i', trim($parts[1]), $tz);
        if (!$openTime || !$closeTime) {
            return false;
        }

        $openTime->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
        $closeTime->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));

        return $now >= $openTime && $now <= $closeTime;
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