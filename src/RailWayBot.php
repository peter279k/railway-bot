<?php
    namespace railway\RailWayBot;

    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Psr7;
    use GuzzleHttp\Client;
    use GuzzleHttp\Cookie\CookieJar;
    use Symfony\Component\DomCrawler\Crawler;

    final class RailWayBot
    {
        private $filePath;

        private $strs = [];

        private $client;

        private $headers = [
            'Host' => 'railway.hinet.net',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en,en-US;q=0.7,zh-TW;q=0.3',
            'Referer' => 'http://railway.hinet.net/check_ctno1.htm',
            'Connection' => 'keep-alive',
        ];

        public function __construct($filePath)
        {
            if (file_exists($filePath)) {
                $this->filePath = $filePath;
            } else {
                $this->filePath = null;
            }
        }

        public function checkCtnnumber()
        {
            $jar = new CookieJar;
            $this->client = new Client(['cookies' => $jar]);

            if ($this->filePath === null) {
                return 'setting file not found';
            }

            $data = json_decode(file_get_contents($this->filePath), true);

            if (is_array($data) === false) {
                return 'the setting file is not a JSON format'.PHP_EOL;
            }

            $method = 'POST';
            $formData = [
                'form_params' => $data,
                'headers' => $this->headers
            ];
            $uri = 'http://railway.hinet.net/check_ctno1.jsp';

            $response = $this->request($method, $formData, $uri, $this->client);

            if($response === false) {
                return 'something error happen'.PHP_EOL;
            }
            else {
                $this->getImageUrl($response);
                $method = 'GET';
                $uri = 'http://railway.hinet.net/'.$this->strs['img_str'];
                $imgResource = fopen(__DIR__.'/../captcha.jpg', 'w');
                $stream = \GuzzleHttp\Psr7\stream_for($imgResource);
                $data = ['save_to' => $stream];
                $this->request($method, $data, $uri, $this->client);
                return 'please checkout the captcha.jpg and input the recaptcha code: '.PHP_EOL;
            }
        }

        public function bookTicket($captchaCode)
        {
            $data = json_decode(file_get_contents($this->filePath), true);
            $data['randInput'] = trim($captchaCode);
            $uri = 'http://railway.hinet.net/order_no1.jsp?';
            $queryStr = '';
            foreach($data as $key => $value) {
                if($key === 'getin_date') {
                    $key = urlencode($key);
                }
                $queryStr .= $key .'='.$value.'&';
            }
            $queryStr .= $this->strs['img_str'];
            $uri .= $queryStr;
            $method = 'GET';
            
            $this->headers['Referer'] = 'http://railway.hinet.net/check_ctno1.jsp';
            $headers = ['headers' => $this->headers,];
            $response = $this->request($method, $headers, $uri, $this->client);

            return $response;
        }

        public function getAvailableDate()
        {
            $jar = new CookieJar;
            $this->client = new Client(['cookies' => $jar]);
            $this->headers['Referer'] = '';
            $headers = ['headers' => $this->headers,];
            $response = $this->request('GET', $headers, 'http://railway.hinet.net/ctno1.htm', $this->client);
            if($response === false) {
                return 'failed to get dates lists';
            }
            $availableDates = [];
            $index = 0;
            $crawler = new Crawler($response);
            $dateTag = $crawler->filter('select[id="getin_date"]');
            foreach($dateTag as $key => $value) {
                $crawler = new Crawler($value);
                $dates = $crawler->filter('option');
                foreach($dates as $datKey => $datVal) {
                    $crawler = new Crawler($datVal);
                    $availableDates[$index] = $crawler->filter('option')->attr('value');
                    $index++;
                }
            }

            return $availableDates;
        }

        public function getStationCode()
        {
            //output the file: station_code.json
            $stations = [];
            $jar = new CookieJar();
            $this->client = new Client(['cookies' => $jar]);
            $this->headers['Referer'] = '';
            $headers = ['headers' => $this->headers,];
            $response = $this->request('GET', $headers, 'http://railway.hinet.net/station_code.htm', $this->client);

            $crawler = new Crawler($response);
            $trs = $crawler->filter('tr');
            foreach($trs as $key => $value) {
                $crawler = new Crawler($value);
                $stationName = $crawler->filter('td');
                $index = 0;
                $cName = '';
                foreach($stationName as $stationKey => $stationValue) {
                    $crawler = new Crawler($stationValue);
                    $str = $crawler->filter('td')->text();
                    $str = trim($str, '　');
                    if($str !== '') {
                        if($index % 2 === 0) {
                            if(empty($stations[$str])) {
                                $stations[$str] = null;
                                $cName = $str;
                            }
                        } else {
                            $stations[$cName] = $str;
                        }
                    }
                    $index += 1;
                }
            }

            return $stations;
        }

        public function parseResult($contents)
        {
            $crawler = new Crawler($contents);
            $result = [];
            $index = 0;
            try {
                $resMsg = $crawler->filter('strong');
                foreach($resMsg as $key => $value) {
                    $crawler = new Crawler($value);
                    $strongTxt = $crawler->filter('strong')->text();
                    $result['message'] = $strongTxt;
                    switch($strongTxt) {
                        case '您的車票已訂到':
                            $spanArr = $crawler->filter('span');
                            foreach($spanArr as $spanKey => $spanValue) {
                                $crawler = new Crawler($spanValue);
                                $spanTxt = $crawler->filter('span')->text();
                                $result[$index] = $spanTxt;
                                $index += 1;
                            }
                            break;
                    }
                }
            } catch(Exception $e) {
                $resMsg = $crawler->filter('p');
                foreach($resMsg as $key => $value) {
                    $crawler = new Crawler($value);
                    $pTxt = $crawler->filter('p')->text();
                    $result['message'] = $pTxt;
                    $index += 1;
                }
            }

            return $result;
        }

        private function getImageUrl($contents)
        {
            $crawler = new Crawler($contents);
            $imgTag = $crawler->filter('img');
            foreach($imgTag as $key => $value) {
				$crawler = new Crawler($value);
				$texts = $crawler->filter('img') -> attr('src');
                if(stristr($texts, 'ImageOut.jsp') !== false) {
                    $this->strs['img_str'] = $texts;
                }
            }
        }

        private function request($method, array $data, $uri, Client $client)
        {
            try {
                if(count($data) === 0) {
                    $response = $client->request($method, $uri);
                } else {
                    $response = $client->request($method, $uri, $data);
                }

                return $response->getBody()->getContents();
            } catch(ClientException $e) {
                return file_put_contents(__DIR__.'/../error.log', Psr7\str($e->getResponse()));
                return false;
            }
        }
    }
