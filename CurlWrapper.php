<?php
/**
 * 提供对curl扩展的封装
 * by alphali
 */
class CurlWrapper {
    const TIMEOUT=30;
    const HTTP_VERSION = 'CURL_HTTP_VERSION_1_1';
    const USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12F70 Safari/600.1.4';

    protected $_config;
    protected $_cookies;

    public function  __construct() {
        $this->_config = array('timeout'=>self::TIMEOUT, 'httpversion'=>self::HTTP_VERSION,
            'useragent'=>self::USER_AGENT);
    }

    /**
     * 设置cookies信息
     * @param Array $cookieMap 参数关联数组
     * @return $this
     */
    public function setCookie($cookieMap) {
        $cookies = '';
        foreach ($cookieMap as $k=>$v) {
            $cookies .= "$k=$v;";
        }
        $this->_cookies = $cookies;
        return $this;
    }

    /**
     * 配置client
     * timeout 设定超时以及curl的执行时间
     * httpversion 设定http协议版本，'1.0'或者'1.1'
     * useragent  设定User-Agent
     *
     * @param array $config
     * @return $this
     */
    public function setConfig($config) {
        $this->_config = array_merge($this->_config, $config);
        return $this;
    }

    /**
     * 简单的http访问，无需建立对象
     *
     * @param string $url
     * @param int $timeout 设定超时时间
     * @return data on success, false on fail
     */
    public static function get($url, $timeout=self::TIMEOUT) {
        if (empty($url)) return false;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, self::HTTP_VERSION);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * 支持设定hosts，支持GET和POST方式
     *
     * @param string $url, url
     * @param string $ip, hosts
     * @param array $postdata, 需要post的数据，如果设置就用POST方式，否则用GET方式
     * @return data on succ, false on fail
     */
    public function request($url, $ip='', $postdata='') {
        if (empty($url)) return false;
        
        $ch = curl_init();
        if ($ip) {
            $info = parse_url($url);
            $host = $info['host'];
            $start = strpos($url, $host);
            $url = substr($url, 0, $start) . $ip . substr($url, $start + strlen($host));

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $host));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_config['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, $this->_config['httpversion']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->_config['useragent']);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); // 必须为空，架构部 httpserver 不支持这个造成curl阻塞2s
        if ('' != $this->_cookies)
            curl_setopt($ch, CURLOPT_COOKIE, $this->_cookies);

        if ($postdata) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }

        $response = curl_exec($ch);
        /*var_dump($response);
        echo curl_errno($ch) . "\n";
        echo curl_error($ch) . "\n";*/
        curl_close($ch);

        return $response;
    }
    
    /**
     * curl多线程get请求
     * @param array $urlArr array(url1, url2,...);
     * @param int $timeout
     * @return Ambigous <array, false>
     */
    public function multiGet($urlArr, $timeout = self::TIMEOUT){
        $dataArray = array();
        foreach($urlArr as $url){
            $dataArray[] = array("url"=> $url);
        }
        return $this->multiRequest($dataArray, $timeout);
    }
    
    /**
     * curl多线程执行请求并返回结果数组, 支持GET和POST两种方式
     * @param array $dataArr 
     * //example: 
     *    array(
     *         array(
     *           "url"=>"http://xxxx.html",//GET方式时添加参数后缀
     *           "postData"=>array(  //postData参数可选
     *                "seg1"=>"aaa",
     *                "seg2"=> "bbbb",
     *                ...,
     *            ),//postData为空或者没有此时,使用GET方式请求
     *         ),
     *         array(...),
     *         ....,
     *    );
     * @param int $timeout
     * @return Ambigous <array, false>  data array of response when request successfully, otherwise false;
     */
    public function multiRequest($dataArray, $timeout = self::TIMEOUT) {
        $ch_array = array();
        $mh = curl_multi_init();
        foreach($dataArray as $param){
            if(empty($param['url'])){
                curl_multi_close($mh);
                return false;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $param['url']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, 'CURL_HTTP_VERSION_1_1');
            curl_setopt($ch, CURLOPT_USERAGENT, 'tcms_http_curl_1.1');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //FORCE IPV4
            if (!empty($param['postData'])) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param['postData']);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, 1);
            }
            curl_multi_add_handle($mh, $ch);
            $ch_array[] = $ch;
        }
        //collected request empty
        if(empty($ch_array)){
            return false;
        }
        //do multi-thread request
        $active = 0;
        do{
            do{
                $mrc = curl_multi_exec($mh, $active);
            }while($mrc == CURLM_CALL_MULTI_PERFORM);
        }while($mrc == CURLM_OK && $active && curl_multi_select($mh)!=-1);
        if($mrc != CURLM_OK){
            //request failed ,log
            return false;
        }
        //store response data and return
        $retDataArray = array();
        foreach($ch_array as $ch){
            $content = curl_multi_getcontent($ch);
            if(empty($content)){
                return false;
            }
            $retDataArray[] = $content;
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
        return $retDataArray;
    }

}

?>
