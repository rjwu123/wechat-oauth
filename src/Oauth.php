<?php

namespace rjwu123\wechat;

/**
 * 微信授权
 * Version 1.0.0
 */
class Oauth {

    public $appid = null;
    public $appsecret = null;
    public $scope = 'snsapi_base';
    public $redirectUri = '';
    public $state = '';
    public $lang = 'zh_CN';
    public $refresh_token = ''; // 用户刷新access_token
    protected $urlAuthorize = 'https://open.weixin.qq.com/connect/oauth2/authorize'; // 第一步：用户同意授权，获取code
    protected $urlAccessToken = 'https://api.weixin.qq.com/sns/oauth2/access_token'; // 第二步：通过code换取网页授权access_token
    protected $urlRefreshAccessToken = 'https://api.weixin.qq.com/sns/oauth2/refresh_token'; // 第三步：刷新access_token
    protected $urlUserInfo = 'https://api.weixin.qq.com/sns/userinfo'; // 第四步：拉取用户信息
    protected $httpBuildEncType = 1;

    public function __construct($options = []) {
        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
        if (empty($this->appid)) {
            $this->returnMsg(['code' => 1, 'msg' => '缺少参数:appid']);
        }
        if (empty($this->appsecret)) {
            $this->returnMsg(['code' => 1, 'msg' => '缺少参数:appsecret']);
        }
    }

    /**
     * 第一步：用户同意授权，获取code
     * @param array $options
     * @return string 返回连接地址
     */
    public function getAuthorizationUrl($options = []) {
        $this->state = isset($options['state']) ? $options['state'] : md5(uniqid(rand(), true));

        $params = [
            'appid' => $this->appid,
            'redirect_uri' => $this->redirectUri,
            'response_type' => isset($options['response_type']) ? $options['response_type'] : 'code',
            'scope' => $this->scope,
            'state' => $this->state,
        ];

        return $this->urlAuthorize . '?' . $this->httpBuildQuery($params, '', '&') . '#wechat_redirect';
    }

    /**
     * 第二步：通过code换取网页授权access_token
     * 第三步：刷新access_token
     * @param string $grant grant类型
     * @param array $params url参数
     * @return array
     */
    public function getAccessToken($grant = 'authorization_code', $params = []) {
        if ($grant == 'authorization_code') {
            $defaultParams = [
                'appid' => $this->appid,
                'secret' => $this->appsecret,
                'grant_type' => $grant,
            ];
            $url = $this->urlAccessToken;
            if (!isset($params['code'])) {
                $this->returnMsg(['code' => 1, 'msg' => '缺少参数:code']);
            }
        } elseif ($grant == 'refresh_token') {
            $defaultParams = [
                'appid' => $this->appid,
                'grant_type' => $grant,
            ];
            $url = $this->urlRefreshAccessToken;
            if (!isset($params['refresh_token'])) {
                $this->returnMsg(['code' => 1, 'msg' => '缺少参数:refresh_token']);
            }
        } else {
            $this->returnMsg(['code' => 1, 'msg' => '缺少参数:grant']);
        }

        $requestParams = array_merge($defaultParams, $params);

        // 合成url
        $url = $url . '?' . $this->httpBuildQuery($requestParams, '', '&');

        return json_decode($this->httpsRequest($url), true);
    }

    /**
     * 第四步：拉取用户信息(需scope为 snsapi_userinfo)
     * @param array $token
     * @return array
     */
    public function getUserInfo($token = []) {
        $params = [
            'access_token' => $token['access_token'],
            'openid' => $token['openid'],
            'lang' => $this->lang
        ];
        $url = $this->urlUserInfo . '?' . $this->httpBuildQuery($params, '', '&');
        return $this->getReturn(json_decode($this->httpsRequest($url), true));
    }

    /**
     * 微信返回转默认返回
     * @param array $res
     */
    public function getReturn($res) {
        if (isset($res['errcode']) && $res['errcode'] > 0) {
            $this->returnMsg(['code' => $res['errcode'], 'msg' => $res['errmsg']]);
        } else {
            return $res;
        }
    }

    /**
     * 出错处理
     */
    public function returnMsg($array = []) {
        header('Content-type:text/json');
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 跳转连接
     * @param string $url 连接
     */
    public function redirect($url) {
        \header('Location: ' . $url);
        exit;
    }

    /**
     * curl请求数据
     * @param string $url
     */
    private function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    /**
     * @explain
     * 发送http请求，并返回数据
     * */
    public function httpsRequest($url, $data = null) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * Build HTTP the HTTP query, handling PHP version control options
     *
     * @param  array        $params
     * @param  integer      $numeric_prefix
     * @param  string       $arg_separator
     * @param  null|integer $enc_type
     *
     * @return string
     * @codeCoverageIgnoreStart
     */
    protected function httpBuildQuery($params, $numeric_prefix = 0, $arg_separator = '&', $enc_type = null) {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !defined('HHVM_VERSION')) {
            if ($enc_type === null) {
                $enc_type = $this->httpBuildEncType;
            }
            $url = http_build_query($params, $numeric_prefix, $arg_separator, $enc_type);
        } else {
            $url = http_build_query($params, $numeric_prefix, $arg_separator);
        }

        return $url;
    }

}
