<?php
// Example: rename to config/openai.php and set your API key
class OpenAIClient {
    private $api_key; // e.g. 'sk-...'
    private $base_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = 'sk-your-openai-api-key';
    }

    public function generateMarketingContent($segment, $customerData = []) {
        $prompt = $this->buildPrompt($segment, $customerData);

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Kamu adalah ahli marketing untuk produk batik Indonesia. Buatkan konten yang menarik dan personal.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 300,
            'temperature' => 0.7
        ];

        return $this->callOpenAI($data);
    }

    private function buildPrompt($segment, $customerData) {
        $prompts = [
            'Champions' => 'Buatkan email marketing untuk pelanggan setia yang sering berbelanja batik. Fokus pada produk premium dan eksklusif.',
            'Loyal Customers' => 'Buatkan konten untuk pelanggan loyal. Tawarkan diskon khusus dan produk baru.',
            'Potential Loyalists' => 'Buatkan konten untuk menarik pelanggan potensial menjadi loyal. Fokus pada kualitas dan tradisi batik.',
            'At Risk' => 'Buatkan konten win-back untuk pelanggan yang mulai jarang berbelanja. Tawarkan promo menarik.',
            'Lost Customers' => 'Buatkan konten untuk merebut kembali pelanggan yang sudah lama tidak berbelanja.'
        ];
        return $prompts[$segment] ?? 'Buatkan konten marketing untuk produk batik yang menarik.';
    }

    private function callOpenAI($data) {
        if (!$this->api_key || strpos($this->api_key, 'sk-') !== 0) {
            throw new Exception('OpenAI API not configured. Set your API key in config/openai.php');
        }
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception('Network error: ' . $curl_error);
        }
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'content' => $result['choices'][0]['message']['content'],
                    'tokens_used' => $result['usage']['total_tokens'] ?? 0
                ];
            }
            throw new Exception('Invalid API response format');
        }
        $error_response = json_decode($response, true);
        $error_message = $error_response['error']['message'] ?? 'Unknown API error';
        throw new Exception('OpenAI API Error: ' . $error_message);
    }
}
?>

