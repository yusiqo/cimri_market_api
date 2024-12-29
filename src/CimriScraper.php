<?php

class CimriScraper {
    private $baseUrl = 'https://www.cimri.com/market/arama';
    private $headers = [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'accept-language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: document',
        'sec-fetch-mode: navigate',
        'sec-fetch-site: same-origin',
        'sec-fetch-user: ?1',
        'upgrade-insecure-requests: 1',
        'cache-control: max-age=0'
    ];
    private $totalPages = 1;

    private $turkishChars = [
        'ş' => 's', 'Ş' => 'S',
        'ı' => 'i', 'İ' => 'I',
        'ğ' => 'g', 'Ğ' => 'G',
        'ü' => 'u', 'Ü' => 'U',
        'ö' => 'o', 'Ö' => 'O',
        'ç' => 'c', 'Ç' => 'C'
    ];

    private function convertTurkishChars($text) {
        return str_replace(
            array_keys($this->turkishChars),
            array_values($this->turkishChars),
            $text
        );
    }

    public function getProducts($query, $sort = '', $page = 1) {
        $url = $this->buildUrl($query, $sort, $page);
        $html = $this->fetchUrl($url);

        // Önce sayfalama bilgisini al
        $this->parsePagination($html);

        // Eğer istenen sayfa, toplam sayfa sayısından büyükse hata ver
        if ($page > $this->totalPages) {
            throw new Exception("Sayfa bulunamadı. Toplam sayfa sayısı: " . $this->totalPages);
        }

        return $this->parseProducts($html);
    }

    private function parsePagination($html) {
        // Önce mobil sayfalamadaki x/y formatını kontrol et
        if (preg_match('/<div[^>]*class="[^"]*Pagination_pagination[^"]*".*?<span>(\d+)\/(\d+)<\/span>/s', $html, $matches)) {
            $this->totalPages = (int)$matches[2];
            return;
        }

        // Eğer mobil format bulunamazsa, normal sayfalama bölümünü dene
        if (preg_match('/<div[^>]*class="[^"]*Pagination_pagination[^"]*"[^>]*>.*?<ul>(.*?)<\/ul>/s', $html, $matches)) {
            $paginationHtml = $matches[1];

            // Tüm sayfa numaralarını bul
            if (preg_match_all('/page=(\d+)["\s]/', $paginationHtml, $pageMatches)) {
                if (!empty($pageMatches[1])) {
                    // En büyük sayfa numarasını al
                    $this->totalPages = max(array_map('intval', $pageMatches[1]));
                    return;
                }
            }
        }

        // Hiçbir yöntem işe yaramazsa, tek sayfa vardır
        $this->totalPages = 1;
    }

    private function buildUrl($query, $sort, $page) {
        // Türkçe karakterleri dönüştür
        $query = $this->convertTurkishChars($query);

        $params = [
            'q' => $query
        ];

        if (!empty($sort)) {
            $params['sort'] = $sort;
        }

        if ($page > 1) {
            $params['page'] = $page;
        }

        return $this->baseUrl . '?' . http_build_query($params);
    }

    private function fetchUrl($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl hatası: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new Exception("HTTP Hata Kodu: " . $httpCode);
        }

        curl_close($ch);
        return $response;
    }

    public function getTotalPages() {
        return $this->totalPages;
    }

    private function parseProducts($html) {
        $products = [];
        $merchantData = [];

        // Önce __NEXT_DATA__ script'inden merchant bilgilerini al
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $nextDataMatches)) {
            $nextData = json_decode($nextDataMatches[1], true);
            if (isset($nextData['props']['pageProps']['data']['productServiceSearchWithSeoQuery']['products'])) {
                foreach ($nextData['props']['pageProps']['data']['productServiceSearchWithSeoQuery']['products'] as $product) {
                    if (isset($product['id'])) {
                        $merchantData[$product['id']] = [
                            'merchantId' => isset($product['topOffers'][0]['merchantId']) ? $product['topOffers'][0]['merchantId'] : null,
                            'price' => isset($product['topOffers'][0]['price']) ? $product['topOffers'][0]['price'] : 0,
                            'unitPrice' => isset($product['topOffers'][0]['unitPrice']['displayUnitPrice'])
                                ? $product['topOffers'][0]['unitPrice']['displayUnitPrice']
                                : 0,
                            'brand' => isset($product['brandSummary']['name']) ? $product['brandSummary']['name'] : ''
                        ];
                    }
                }
            }
        }

        // JSON-LD script etiketlerini bul
        if (preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $jsonData = json_decode($jsonStr, true);

                // ItemList tipindeki JSON-LD'yi bul
                if (isset($jsonData['@type']) && $jsonData['@type'] === 'ItemList' && isset($jsonData['itemListElement'])) {
                    foreach ($jsonData['itemListElement'] as $item) {
                        if (isset($item['item'])) {
                            $productData = $item['item'];

                            // URL'den ID'yi çıkar
                            $urlParts = explode(',', $productData['url']);
                            $id = end($urlParts);

                            // Temel bilgiler
                            $name = $productData['name'];
                            $brand = ''; // Varsayılan boş değer

                            // Resim URL'sini parse et
                            $image = null;
                            if (isset($productData['image'])) {
                                $image = basename($productData['image']);
                            }

                            // Merchant ve fiyat bilgilerini al
                            $price = isset($productData['offers']['lowPrice']) ? $productData['offers']['lowPrice'] : 0;
                            $unitPrice = 0;
                            $merchantId = null;

                            // Merchant ve diğer bilgileri ekle
                            if (isset($merchantData[$id])) {
                                $merchantId = $merchantData[$id]['merchantId'];
                                $price = $merchantData[$id]['price'];
                                $unitPrice = $merchantData[$id]['unitPrice'];
                                $brand = $merchantData[$id]['brand'];
                            }

                            // Miktar ve birim bilgilerini isimden çıkar
                            preg_match('/(\d+)\s*(?:kg|gr|g|adet|lt|l)/i', $name, $unitMatch);
                            $quantity = '';
                            $unit = '';

                            if (!empty($unitMatch)) {
                                $quantity = $unitMatch[1];
                                if (preg_match('/(kg|gr|g|adet|lt|l)/i', $unitMatch[0], $unitTypeMatch)) {
                                    $unit = strtolower($unitTypeMatch[1]);
                                    // Birim standardizasyonu
                                    switch ($unit) {
                                        case 'g':
                                            $unit = 'gr';
                                            break;
                                        case 'l':
                                            $unit = 'lt';
                                            break;
                                    }
                                }
                            }

                            $products[] = [
                                'id' => $id,
                                'name' => $name,
                                'brand' => $brand,
                                'price' => $price,
                                'unit_price' => $unitPrice,
                                'quantity' => $quantity,
                                'unit' => $unit,
                                'image' => $image,
                                'url' => $productData['url'],
                                'topOffers' => [
                                    [
                                        'merchantId' => $merchantId
                                    ]
                                ]
                            ];
                        }
                    }
                }
            }
        }

        if (empty($products)) {
            throw new Exception('Ürünler bulunamadı veya parse edilemedi.');
        }

        return $products;
    }

    public function getProductDetails($path) {
        $url = 'https://www.cimri.com/market/' . $path;
        $html = $this->fetchUrl($url);

        // __NEXT_DATA__ script'ini bul
        if (!preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            throw new Exception('Ürün detayları bulunamadı.');
        }

        $nextData = json_decode($matches[1], true);
        if (!$nextData) {
            throw new Exception('Ürün detayları parse edilemedi.');
        }

        // Debug: priceHistory verilerini kontrol et
        $priceHistoryData = $nextData['query']['data']['data']['priceHistory'] ?? null;
        if ($priceHistoryData === null) {
            error_log('Price history data not found in response');
        } else {
            error_log('Price history data found: ' . json_encode($priceHistoryData));
        }

        // Temel bilgileri topla
        $product = [
            'id' => $path,
            'name' => $nextData['props']['pageProps']['seo']['meta']['h1'],
            'description' => $nextData['props']['pageProps']['seo']['bullet'],
            'specs' => [], // Ürün özellikleri
            'priceHistory' => [], // Fiyat geçmişi
            'offers' => [] // Diğer market teklifleri
        ];

        // Ürün özelliklerini ekle
        if (isset($nextData['props']['pageProps']['specGroups'])) {
            foreach ($nextData['props']['pageProps']['specGroups'] as $group) {
                $specs = [];
                foreach ($group['rows'] as $row) {
                    $specs[] = [
                        'name' => $row['specName']['name'],
                        'value' => $row['specValue']['name']
                    ];
                }
                $product['specs'][] = [
                    'group' => $group['specGroup']['name'],
                    'items' => $specs
                ];
            }
        }

        // Fiyat geçmişini ekle
        if (isset($nextData['query']['data']['data']['priceHistory']['success'])) {
            $product['priceHistory'] = array_values(array_filter(array_map(function($item) {
                $price = floatval($item['minPrice']);
                if ($price <= 0) {
                    return null;
                }
                return [
                    'date' => date('Y-m-d', strtotime($item['date'])),
                    'price' => $price
                ];
            }, $nextData['query']['data']['data']['priceHistory']['success']), function($item) {
                return $item !== null;
            }));

            // Debug: Dönüştürülen price history verilerini kontrol et
            error_log('Converted price history: ' . json_encode($product['priceHistory']));
        } else {
            error_log('Price history success array not found');
        }

        // Sadece offline market tekliflerini ekle
        if (isset($nextData['props']['pageProps']['product']['offersOfflineGlobal'])) {
            $product['offers'] = array_map(function($offer) {
                return [
                    'merchantId' => $offer['merchantId'],
                    'merchantName' => $offer['merchantData']['name'],
                    'price' => $offer['price'],
                    'unitPrice' => isset($offer['unitPrice']['displayUnitPrice']) ? $offer['unitPrice']['displayUnitPrice'] : null
                ];
            }, $nextData['props']['pageProps']['product']['offersOfflineGlobal']);
        }

        return $product;
    }
}
