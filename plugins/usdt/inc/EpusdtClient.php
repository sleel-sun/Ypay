<?php

/**
 * Epusdt API客户端
 * 用于与Epusdt平台进行API交互
 */
class EpusdtClient
{
    private $apiUrl;
    private $merchantId;
    private $secretKey;
    private $network;
    
    /**
     * 构造函数
     * 
     * @param string $merchantId 商户ID
     * @param string $secretKey 商户密钥
     * @param string $apiUrl API接口地址
     * @param int $network 支付网络类型 0:TRC20, 1:ERC20
     */
    public function __construct($merchantId, $secretKey, $apiUrl, $network = 0)
    {
        $this->merchantId = $merchantId;
        $this->secretKey = $secretKey;
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->network = $network;
    }
    
    /**
     * 创建支付订单
     * 
     * @param float $amount 支付金额
     * @param string $orderId 订单号
     * @param string $notifyUrl 异步通知地址
     * @param string $redirectUrl 同步跳转地址
     * @return array 返回订单创建结果
     */
    public function createOrder($amount, $orderId, $notifyUrl, $redirectUrl)
    {
        $params = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'order_id' => $orderId,
            'notify_url' => $notifyUrl,
            'redirect_url' => $redirectUrl,
            'signature' => $this->generateSignature($this->merchantId, $amount, $orderId, $this->secretKey)
        ];
        
        if($this->network == 1) {
            $params['token_type'] = 'ERC20';
        }
        
        return $this->request('api/v1/order/create-transaction', $params);
    }
    
    /**
     * 查询订单状态
     * 
     * @param string $orderId 订单号
     * @return array 返回订单状态
     */
    public function queryOrder($orderId)
    {
        $params = [
            'merchant_id' => $this->merchantId,
            'order_id' => $orderId,
            'signature' => $this->generateSignature($this->merchantId, '', $orderId, $this->secretKey)
        ];
        
        return $this->request('api/v1/order/status', $params);
    }
    
    /**
     * 生成签名
     * 
     * @param string $merchantId 商户ID
     * @param float $amount 金额
     * @param string $orderId 订单号
     * @param string $key 密钥
     * @return string 签名
     */
    public function generateSignature($merchantId, $amount, $orderId, $key)
    {
        $data = $merchantId . $amount . $orderId . $key;
        return md5($data);
    }
    
    /**
     * 验证回调签名
     * 
     * @param array $data 回调数据
     * @return bool 验证结果
     */
    public function verifyNotify($data)
    {
        if(empty($data['merchant_id']) || empty($data['order_id']) || empty($data['amount']) || empty($data['signature'])) {
            return false;
        }
        
        $signature = $this->generateSignature($data['merchant_id'], $data['amount'], $data['order_id'], $this->secretKey);
        return $signature === $data['signature'];
    }
    
    /**
     * 发送API请求
     * 
     * @param string $endpoint API端点
     * @param array $params 请求参数
     * @return array 返回结果
     */
    private function request($endpoint, $params)
    {
        $url = $this->apiUrl . $endpoint;
        $response = $this->curl_post($url, $params);
        
        $result = json_decode($response, true);
        if(!$result) {
            throw new Exception('API响应解析失败');
        }
        
        return $result;
    }
    
    /**
     * 发送POST请求
     * 
     * @param string $url 请求地址
     * @param array $params 请求参数
     * @return string 响应内容
     */
    private function curl_post($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if($httpCode != 200) {
            throw new Exception('HTTP请求失败，状态码：' . $httpCode);
        }
        
        return $response;
    }
}